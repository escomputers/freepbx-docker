"""Automatic regressions with real Debian tools and disposable package databases.

Run through run_integration_tests.py. Only repository sources and Snapshot transport
are replaced with local fixtures. APT output, package identities, checksums, and
Debian version comparisons are real.
"""

import hashlib
import importlib.util
import json
import os
import shutil
import subprocess
import tempfile
import unittest
from dataclasses import dataclass
from pathlib import Path
from unittest.mock import patch
from urllib.parse import quote, unquote, urlsplit

# Keep this short loader local so either test module can run independently.
# pylint: disable=duplicate-code
SCRIPT = (
    Path(__file__).resolve().parents[1] / ".github/scripts/prepare_security_updates.py"
)
SPEC = importlib.util.spec_from_file_location("security_updates", SCRIPT)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"Cannot load {SCRIPT}")
updates = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(updates)


@dataclass(frozen=True)
class DebPackage:
    """A real, payload-free .deb and the metadata used by the local repository."""

    fields: dict[str, str]
    path: Path

    def manifest_row(self) -> list[str]:
        """Return the five manifest columns for this archive."""
        return [
            self.fields["Package"],
            self.fields["Version"],
            self.path.name,
            "https://snapshot.debian.org/file/" + self.fields["SHA256"],
            self.fields["SHA256"],
        ]


@unittest.skipUnless(
    os.environ.get("FREEPBX_INTEGRATION_CONTAINER") == "1",
    "Use python3 -B tests/run_integration_tests.py for the Debian integration suite",
)
class SecurityUpdatesIntegrationTests(unittest.TestCase):  # pylint: disable=too-many-instance-attributes
    """Exercise proposal preparation against real APT dependency resolution."""

    @classmethod
    def setUpClass(cls) -> None:
        """Refuse to create package fixtures outside the intended test container."""
        if not Path("/.dockerenv").is_file():
            raise RuntimeError(
                "Integration tests require their disposable Docker container"
            )
        release = Path("/etc/os-release").read_text(encoding="utf-8")
        architecture = subprocess.run(
            ["dpkg", "--print-architecture"],
            text=True,
            capture_output=True,
            check=True,
        ).stdout.strip()
        if 'VERSION_ID="12"' not in release or architecture != "amd64":
            raise RuntimeError("Integration tests require Debian 12 amd64")

    def setUp(self) -> None:
        """Create isolated package files, repository metadata, and dpkg state."""
        # unittest closes this context after the test, even if setUp fails.
        temporary = self.enterContext(
            tempfile.TemporaryDirectory(  # pylint: disable=consider-using-with
                prefix="freepbx-security-test-"
            )
        )
        self.root = Path(temporary)
        self.root.chmod(0o755)  # APT's _apt user must be able to read the local index.
        self.repo = self.root / "repo"
        self.repo.mkdir()
        self.database = self.root / "image"
        (self.database / "var/lib/dpkg").mkdir(parents=True)
        self.status = self.database / "var/lib/dpkg/status"
        self.status.write_text("", encoding="utf-8")
        (self.root / "cache").mkdir()
        (self.root / "preferences.d").mkdir()
        (self.root / "preferences").write_text("", encoding="utf-8")
        self.options = [
            "-o",
            f"Dir::State::status={self.status}",
            "-o",
            f"Dir::Cache={self.root / 'cache'}",
            "-o",
            "Dir::Cache::pkgcache=",
            "-o",
            "Dir::Cache::srcpkgcache=",
            "-o",
            f"Dir::Etc::preferences={self.root / 'preferences'}",
            "-o",
            f"Dir::Etc::preferencesparts={self.root / 'preferences.d'}",
        ]
        self.packages: list[DebPackage] = []
        self.manifest = self.root / "approved.tsv"
        self.proposal = self.root / "proposed.tsv"
        self.report = self.root / "trivy.json"

    def build_package(
        self,
        name: str,
        version: str,
        *,
        source: str | None = None,
        depends: str | None = None,
        architecture: str = "amd64",
    ) -> DebPackage:
        """Build a .deb without payload files or maintainer scripts.

        Args:
            name (str): Synthetic binary package name.
            version (str): Debian binary version.
            source (str | None): Optional Source field.
            depends (str | None): Optional real Debian dependency expression.
            architecture (str): Package architecture.

        Returns:
            DebPackage: Built archive and its local APT index fields.
        """
        fields = {
            "Package": name,
            "Version": version,
            "Architecture": architecture,
            "Maintainer": "Security tests <tests@example.invalid>",
            "Description": "Disposable security workflow fixture",
        }
        if source is not None:
            fields["Source"] = source
        if depends is not None:
            fields["Depends"] = depends
        build = self.root / "build" / f"{name}-{version}-{architecture}"
        (build / "DEBIAN").mkdir(parents=True)
        (build / "DEBIAN/control").write_text(
            "".join(f"{key}: {value}\n" for key, value in fields.items()),
            encoding="utf-8",
        )
        archive = self.repo / f"{name}_{quote(version, safe='.+~-')}_{architecture}.deb"
        subprocess.run(
            ["dpkg-deb", "--root-owner-group", "--build", str(build), str(archive)],
            capture_output=True,
            text=True,
            check=True,
        )
        content = archive.read_bytes()
        fields.update(
            Filename=archive.name,
            Size=str(len(content)),
            SHA256=hashlib.sha256(content).hexdigest(),
        )
        package = DebPackage(fields, archive)
        self.packages.append(package)
        return package

    def install_baseline(self, *packages: DebPackage) -> None:
        """Install fixture packages only into this test's temporary dpkg root."""
        subprocess.run(
            [
                "dpkg",
                f"--root={self.database}",
                f"--log={self.root / 'dpkg.log'}",
                "--install",
                *(str(package.path) for package in packages),
            ],
            capture_output=True,
            text=True,
            check=True,
        )

    def fixture_snapshot(self, url: str, destination: Path | None = None) -> str:
        """Serve Snapshot metadata and genuine .deb bytes from local fixtures.

        Args:
            url (str): Snapshot API or archive URL requested by production code.
            destination (Path | None): Download destination, if requested.

        Returns:
            str: API JSON, or an empty string after copying the archive.
        """
        parsed = urlsplit(url)
        self.assertEqual(parsed.hostname, "snapshot.debian.org")
        if destination is not None:
            checksum = parsed.path.rsplit("/", 1)[1]
            package = next(p for p in self.packages if p.fields["SHA256"] == checksum)
            shutil.copyfile(package.path, destination)
            return ""
        parts = unquote(parsed.path).strip("/").split("/")
        self.assertEqual(parts[:2], ["mr", "binary"])
        name, version = parts[2:4]
        matches = [
            p
            for p in self.packages
            if p.fields["Package"] == name and p.fields["Version"] == version
        ]
        self.assertTrue(matches, url)
        return json.dumps(
            {
                "result": [
                    {
                        "hash": p.fields["SHA256"],
                        "architecture": p.fields["Architecture"],
                    }
                    for p in matches
                ],
                "fileinfo": {
                    p.fields["SHA256"]: [
                        {"archive_name": "debian", "name": p.path.name}
                    ]
                    for p in matches
                },
            }
        )

    def prepare(
        self,
        rows: list[list[str]],
        findings: list[dict[str, str]],
        available: list[DebPackage] | None = None,
    ) -> list[list[str]]:
        """Run the actual preparation code with only local external data.

        Args:
            rows (list[list[str]]): Approved manifest rows.
            findings (list[dict[str, str]]): Synthetic Trivy vulnerability entries.
            available (list[DebPackage] | None): Local APT index contents.

        Returns:
            list[list[str]]: Proposed manifest rows after successful preparation.
        """
        self.manifest.write_text(
            "".join("\t".join(row) + "\n" for row in rows), encoding="utf-8"
        )
        self.report.write_text(
            json.dumps(
                {
                    "Metadata": {"OS": {"Family": "debian", "Name": "12"}},
                    "Results": [
                        {
                            "Class": "os-pkgs",
                            "Type": "debian",
                            "Vulnerabilities": findings,
                        }
                    ],
                }
            ),
            encoding="utf-8",
        )
        indexed = self.packages if available is None else available
        (self.repo / "Packages").write_text(
            "\n".join(
                "".join(f"{key}: {value}\n" for key, value in p.fields.items())
                for p in indexed
            ),
            encoding="utf-8",
        )
        manifest_before = self.manifest.read_bytes()
        status_before = self.status.read_bytes()
        system_status_before = Path("/var/lib/dpkg/status").read_bytes()
        # Every run must remove any stale proposal before it can fail.
        self.proposal.write_text("stale proposal", encoding="utf-8")
        original_run = updates.run

        def run_with_fixture_database(*args: str) -> str:
            # Only redirect state paths; execute all commands and comparisons.
            if args[0] == "dpkg-query":
                args = (args[0], f"--admindir={self.status.parent}", *args[1:])
            elif args[0] in ("apt-get", "apt-cache"):
                args = (args[0], *self.options, *args[1:])
            return original_run(*args)

        try:
            with (
                patch.object(updates, "run", run_with_fixture_database),
                patch.object(updates, "fetch", self.fixture_snapshot),
                patch.object(
                    updates, "APT_SOURCES", f"deb [trusted=yes] file:{self.repo} ./\n"
                ),
                patch.dict(os.environ, {"LC_ALL": "C"}),
            ):
                updates.prepare(self.report, self.manifest, self.proposal)
        finally:
            self.assertEqual(self.manifest.read_bytes(), manifest_before)
            self.assertEqual(self.status.read_bytes(), status_before)
            self.assertEqual(
                Path("/var/lib/dpkg/status").read_bytes(), system_status_before
            )
        return [
            line.split("\t")
            for line in self.proposal.read_text(encoding="utf-8").splitlines()
        ]

    @staticmethod
    def finding(name: str, installed: str, fixed: str) -> dict[str, str]:
        """Return a synthetic fixable Trivy entry for a real fixture package."""
        return {
            "PkgName": name,
            "InstalledVersion": installed,
            "FixedVersion": fixed,
            "Severity": "HIGH",
            "VulnerabilityID": "SYNTHETIC-TEST-ONLY",
        }

    def test_binary_rebuild_does_not_prove_the_source_fix_is_present(self) -> None:
        """Reject the previously accepted newer binary built from unfixed source."""
        old = self.build_package(
            "audit-source", "2.1-1.0-1+b1", source="audit-src (1.0-1)"
        )
        self.build_package("audit-source", "2.1-1.0-1+b2", source="audit-src (1.0-1)")
        self.install_baseline(old)
        rows = self.prepare(
            [old.manifest_row()],
            [self.finding("audit-source", "2.1-1.0-1+b1", "1.0-2")],
        )
        self.assertEqual(rows, [old.manifest_row()])

    def test_fixed_source_version_is_accepted(self) -> None:
        """Accept an actual source fix, including an epoch and a binary rebuild."""
        old = self.build_package(
            "audit-fixed", "1:1.0-1+b1", source="audit-src (1:1.0-1)"
        )
        new = self.build_package(
            "audit-fixed", "2:0.1-1+b1", source="audit-src (2:0.1-1)"
        )
        self.install_baseline(old)
        rows = self.prepare(
            [old.manifest_row()], [self.finding("audit-fixed", "1:1.0-1+b1", "2:0.1-1")]
        )
        self.assertEqual(rows, [new.manifest_row()])

    def test_all_reported_source_fixes_must_be_satisfied(self) -> None:
        """A candidate fixing one CVE must not bypass another CVE's higher threshold."""
        old = self.build_package(
            "audit-multiple", "2.1-1.0-1", source="audit-src (1.0-1)"
        )
        self.build_package("audit-multiple", "2.1-1.0-2", source="audit-src (1.0-2)")
        self.install_baseline(old)
        rows = self.prepare(
            [old.manifest_row()],
            [
                self.finding("audit-multiple", old.fields["Version"], fixed)
                for fixed in ("1.0-2", "1.0-3")
            ],
        )
        self.assertEqual(rows, [old.manifest_row()])

    def test_incompatible_merged_manifest_is_rejected_before_writing(self) -> None:
        """Catch the merge defect even though the selected update resolves alone."""
        lib1 = self.build_package("audit-lib", "1")
        self.build_package("audit-lib", "2")
        app1 = self.build_package("audit-app", "1", depends="audit-lib (>= 1)")
        app2 = self.build_package("audit-app", "2", depends="audit-lib (= 1)")
        self.install_baseline(lib1, app1)
        approved = [app2.manifest_row(), lib1.manifest_row()]
        findings = [self.finding("audit-lib", "1", "2")]
        with self.assertRaisesRegex(
            RuntimeError, r"audit-app\s*:\s*Depends:\s+audit-lib \(= 1\)"
        ):
            self.prepare(approved, findings)
        self.assertFalse(self.proposal.exists())

    def test_compatible_merge_uses_exact_archives_missing_from_current_index(
        self,
    ) -> None:
        """Accept a valid retained version through its .deb, not the live candidate."""
        lib1 = self.build_package("audit-lib", "1")
        lib2 = self.build_package("audit-lib", "2")
        app1 = self.build_package("audit-app", "1", depends="audit-lib (>= 1)")
        app2 = self.build_package("audit-app", "2", depends="audit-lib (>= 1)")
        app3 = self.build_package("audit-app", "3", depends="audit-lib (>= 1)")
        self.install_baseline(lib1, app1)
        rows = self.prepare(
            [app2.manifest_row(), lib1.manifest_row()],
            [self.finding("audit-lib", "1", "2")],
            available=[app3, lib1, lib2],
        )
        self.assertEqual(rows, [app2.manifest_row(), lib2.manifest_row()])

    def test_valid_unchanged_native_and_architecture_all_packages_are_accepted(
        self,
    ) -> None:
        """If Trivy reports no vulnerabilities, the proposed package list must match the approved list exactly."""
        native = self.build_package("audit-native", "1")
        portable = self.build_package("audit-portable", "1", architecture="all")
        self.install_baseline(native, portable)
        approved = [native.manifest_row(), portable.manifest_row()]
        self.assertEqual(self.prepare(approved, []), approved)

    def test_checksum_cannot_validate_a_different_already_installed_package(
        self,
    ) -> None:
        """Reproduce the identity gap with a correct checksum for the wrong .deb."""
        expected = self.build_package("audit-expected", "1")
        other = self.build_package("audit-other", "1")
        self.install_baseline(expected, other)
        row = other.manifest_row()
        row[0] = "audit-expected"
        with self.assertRaises(RuntimeError):
            self.prepare([row], [])
        self.assertFalse(self.proposal.exists())

    def test_wrong_version_and_foreign_architecture_are_rejected(self) -> None:
        """Reject archives whose version or architecture violates the manifest."""
        installed = self.build_package("audit-identity", "1")
        wrong_version = self.build_package("audit-identity", "2")
        foreign = self.build_package("audit-identity", "1", architecture="arm64")
        self.install_baseline(installed)
        for package in (wrong_version, foreign):
            row = package.manifest_row()
            row[1] = "1"
            with self.subTest(fields=package.fields), self.assertRaises(RuntimeError):
                self.prepare([row], [])
            self.assertFalse(self.proposal.exists())


if __name__ == "__main__":
    unittest.main()
