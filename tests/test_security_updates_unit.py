"""Portable unit tests for the security proposal script's core contracts."""

import contextlib
import importlib.util
import io
import json
import subprocess
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

SCRIPT = (
    Path(__file__).resolve().parents[1] / ".github/scripts/prepare_security_updates.py"
)
SPEC = importlib.util.spec_from_file_location("security_updates", SCRIPT)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"Cannot load {SCRIPT}")
updates = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(updates)


class SecurityUpdatesTests(unittest.TestCase):
    """Check data handling and guards without Debian tools or network access."""

    def test_source_version_uses_debian_field_rules(self) -> None:
        """Read explicit source versions and both documented omission forms."""
        cases = [
            ({"Version": "2.1-1.0-1+b2", "Source": "audit-source (1.0-1)"}, "1.0-1"),
            ({"Version": "1:2.0-1+b1", "Source": "audit-source"}, "1:2.0-1+b1"),
            ({"Version": "1:2.0-1+b1"}, "1:2.0-1+b1"),
            ({"Version": "9", "Source": "audit-source (2:1.0~rc1-1)"}, "2:1.0~rc1-1"),
        ]
        for record, expected in cases:
            with self.subTest(record=record):
                self.assertEqual(updates.get_source_version(record), expected)

    def test_malformed_source_is_not_treated_as_an_omitted_field(self) -> None:
        """Malformed source metadata must fail instead of using the binary version."""
        for source in ("", " ", "audit ()", "audit (1 2)", "audit (1", "../audit (1)"):
            with self.subTest(source=source), self.assertRaises(RuntimeError):
                updates.get_source_version(
                    {"Package": "audit", "Version": "9", "Source": source}
                )

    def test_false_version_comparison_is_not_a_process_failure(self) -> None:
        """Only dpkg statuses 0 and 1 represent successful comparisons."""
        # Mock the operating-system boundary, not Debian's version ordering.
        # Real version comparisons are exercised in the integration suite.
        for status, expected in ((0, True), (1, False)):
            with (
                self.subTest(status=status),
                patch.object(
                    updates.subprocess,
                    "run",
                    return_value=subprocess.CompletedProcess(["dpkg"], status),
                ),
            ):
                self.assertIs(updates.compare("2", "gt", "1"), expected)
        for status in (2, 127, -9, -15):
            with (
                self.subTest(status=status),
                patch.object(
                    updates.subprocess,
                    "run",
                    return_value=subprocess.CompletedProcess(["dpkg"], status),
                ),
                self.assertRaises(RuntimeError),
            ):
                updates.compare("2", "gt", "1")

    def test_freepbx_runtime_limits(self) -> None:
        """Keep the supported PHP branch and the installer's Node package holds."""
        cases = [
            ("nodejs", "18.20.5", {}, False),
            ("node-audit", "1.0", {}, False),
            ("php8.3-cli", "8.3.1", {}, False),
            ("libapache2-mod-php8.3", "8.3.1", {}, False),
            ("php", "2:8.3+1", {"Source": "php-defaults"}, False),
            ("php8.2-cli", "8.2.35-1~deb12u1", {"Source": "php8.2"}, True),
            ("apache2", "2.4.68-1~deb12u1", {"Source": "apache2"}, True),
        ]
        for name, version, record, allowed in cases:
            with self.subTest(name=name):
                result = updates.check_freepbx_compatibility(name, version, record)
                self.assertEqual(result is None, allowed)

    def test_trivy_filter_keeps_only_actionable_debian_findings(self) -> None:
        """Keep HIGH/CRITICAL Debian findings with fixes, grouped by package."""
        selected = {"PkgName": "audit-package", "Severity": "HIGH", "FixedVersion": "2"}
        second = dict(selected, Severity="CRITICAL", FixedVersion="3")
        report = {
            "Metadata": {"OS": {"Family": "debian", "Name": "12"}},
            "Results": [
                {
                    "Class": "os-pkgs",
                    "Type": "debian",
                    "Vulnerabilities": [
                        selected,
                        second,
                        dict(selected, Severity="MEDIUM"),
                        dict(selected, FixedVersion=""),
                    ],
                },
                {
                    "Class": "lang-pkgs",
                    "Type": "composer",
                    "Vulnerabilities": [selected],
                },
            ],
        }
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "trivy.json"
            path.write_text(json.dumps(report), encoding="utf-8")
            self.assertEqual(
                updates.load_findings(path), {"audit-package": [selected, second]}
            )

    def test_merging_keeps_approved_fixes_and_replaces_each_package_once(self) -> None:
        """Retain unrelated approved rows and deterministically append additions."""
        rows = [
            ["kept", "1", "kept.deb", "url", "sha"],
            ["changed", "1", "old.deb", "url", "sha"],
        ]
        proposals = {
            "z-added": ["z-added", "2", "z.deb", "url", "sha"],
            "changed": ["changed", "2", "new.deb", "url", "sha"],
            "a-added": ["a-added", "2", "a.deb", "url", "sha"],
        }
        expected = [
            rows[0],
            proposals["changed"],
            proposals["a-added"],
            proposals["z-added"],
        ]
        with tempfile.TemporaryDirectory() as directory:
            output = Path(directory) / "proposal.tsv"
            with contextlib.redirect_stdout(io.StringIO()):
                updates.write_proposal(rows, proposals, output)
            self.assertEqual(
                output.read_text(encoding="utf-8"),
                "".join("\t".join(row) + "\n" for row in expected),
            )
        self.assertEqual(rows[1][1], "1", "Merging must not mutate approved inputs")

    def test_corrupt_cached_archive_is_rejected_without_downloading_a_replacement(
        self,
    ) -> None:
        """A bad checksum must fail before package parsing or a fallback download."""
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "audit_1_amd64.deb"
            path.write_bytes(b"corrupt archive")
            row = ["audit", "1", path.name, "https://example.invalid/unused", "0" * 64]
            with (
                patch.object(updates, "fetch") as download,
                patch.object(updates, "read_deb_identity") as identity,
                self.assertRaises(RuntimeError),
            ):
                updates.get_manifest_package_file(row, Path(directory))
            download.assert_not_called()
            identity.assert_not_called()


if __name__ == "__main__":
    unittest.main()
