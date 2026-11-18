#!/usr/bin/env python3

r"""Prepare a verified Debian security update proposal for the FreePBX image.

The script is intended to run inside a Debian 12 amd64 container created from the
exact FreePBX image analyzed by Trivy.
It reads the Trivy JSON report and the committed source/packages/packages.tsv file.
Then for each HIGH/CRITICAL Debian package finding with a known fixed version, it:

- searches for available updates in APT, using Bookworm and Bookworm Security;

- verifies that the candidate's source version reaches the fix reported by Trivy,
using specific constraints to avoid breaking compatibility for FreePBX, in particular:
    - PHP: applies updates only within the 8.2 branch;
    - Node: blocks updates for the nodejs package and any package whose name starts with node-.

- skips updates already present in the committed source/packages/packages.tsv file

- simulates the installation of the selected updates, including any required dependencies
and stops if the plan would install new packages, remove packages or downgrade versions

- gets the exact `.deb` files from Debian Snapshot, checking that the name, version and
architecture are correct and that the checksum matches the one published in the verified APT indexes

- checks that APT accepts the complete final manifest using its exact archived
`.deb` files, including approved package versions kept from previous proposals

- creates a new package manifest proposal: `packages-proposed.tsv` ready to be compared
with the committed `packages.tsv` file.

Typical usage inside the disposable container:
python3 prepare_security_updates.py --report /tmp/trivy.json \
    --manifest /tmp/packages.tsv --output /tmp/packages-proposed.tsv
"""

import argparse
import hashlib
import json
import os
import re
import subprocess
import sys
import tempfile
from pathlib import Path
from typing import Any
from urllib.parse import quote

# Constants for the APT sources.list used to select Bookworm and Bookworm Security.
APT_SOURCES = (
    "deb https://deb.debian.org/debian bookworm main\n"
    "deb https://deb.debian.org/debian-security bookworm-security main\n"
)


def run(*args: str) -> str:
    """Run a command, forward diagnostics and return its standard output.

    Args:
        *args (str): Executable name followed by its command-line arguments.

    Returns:
        str: Standard output captured from the successful command.

    Raises:
        RuntimeError: If the command exits with a nonzero status. The error
            includes its captured output and diagnostics.
        OSError: If the operating system cannot start the command.
    """
    result = subprocess.run(args, text=True, capture_output=True, check=False)
    if result.returncode:
        raise RuntimeError(f"{args[0]} failed:\n{result.stdout}{result.stderr}")
    if result.stderr:
        # Keep diagnostics separate so retries cannot corrupt JSON or APT data.
        print(result.stderr, file=sys.stderr, end="")
    return result.stdout


def compare(left: str, operator: str, right: str) -> bool:
    """Compare Debian package versions using dpkg's version ordering.

    Debian versions may contain epochs, tildes and binary rebuild suffixes.
    Their ordering must follow dpkg rather than a lexical or SemVer comparison.

    Args:
        left (str): Version on the left side of the comparison.
        operator (str): A dpkg comparison operator, such as "gt" or "ge".
        right (str): Version on the right side of the comparison.

    Returns:
        bool: Whether the comparison condition is satisfied.

    Raises:
        RuntimeError: If dpkg cannot evaluate the comparison.
        OSError: If dpkg cannot be started.
    """
    result = subprocess.run(
        ["dpkg", "--compare-versions", left, operator, right], check=False
    )
    # dpkg returns 0 if the comparison is true and 1 if false.
    # Any other status, including a negative value after a signal, is an error.
    if result.returncode not in (0, 1):
        raise RuntimeError(f"Cannot compare versions: {left!r}, {right!r}")
    return result.returncode == 0


def parse_package_metadata(text: str) -> list[dict[str, str]]:
    """Convert package information into dictionaries.

    Read lines such as "Package: apache2" as field names and values. Blank lines
    separate blocks of package information; create one dictionary for each block.
    Ignore indented lines that continue a previous field's value, such as a long
    description.

    Args:
        text (str): Package information from apt-cache or dpkg-deb, with blocks
            separated by blank lines.

    Returns:
        list[dict[str, str]]: One dictionary per nonempty block, mapping field
            names such as Package, Version, and SHA256 to their text values.
    """
    return [
        dict(
            line.split(": ", 1)
            for line in block.splitlines()
            if line and not line[0].isspace() and ": " in line
        )
        for block in text.strip().split("\n\n")
        if block.strip()
    ]


def package_record(options: list[str], name: str, version: str) -> dict[str, str]:
    """Read APT metadata for an exact package version and validate its checksum.

    The caller must first refresh and verify the APT indexes. Only amd64 and
    architecture-independent packages are accepted.
    If multiple records have the same package name and version, accept them
    only if their SHA-256 checksums match.

    Args:
        options (list[str]): APT options selecting the sources and index directory.
        name (str): Debian binary package name.
        version (str): Exact Debian package version to retrieve.

    Returns:
        dict[str, str]: Matching APT fields, including package identity, filename,
            and the validated SHA-256 checksum.

    Raises:
        RuntimeError: If apt-cache fails, no matching record exists, or the
            records have missing, malformed, or conflicting checksums.
        OSError: If apt-cache cannot be started.
    """
    # Get the package metadata for the exact version
    records = parse_package_metadata(
        run("apt-cache", *options, "show", f"{name}={version}")
    )
    # Filter out records that do not match the requested name, version, or architecture
    records = [
        record
        for record in records
        if record.get("Package") == name
        and record.get("Version") == version
        and record.get("Architecture") in ("amd64", "all")
    ]

    checksums = {record.get("SHA256", "") for record in records}

    # Raise an error if there is not exactly one valid SHA-256 checksum among the records
    if len(checksums) != 1 or not re.fullmatch(
        r"[0-9a-f]{64}", next(iter(checksums), "")
    ):
        raise RuntimeError(f"Missing or conflicting APT checksums for {name}={version}")

    return records[0]


def get_source_version(record: dict[str, str]) -> str:
    """Read the source version from an APT binary package record.

    Source may contain a name and a version, such as "e2fsprogs (1.47.0-2)".
    If Source omits the version, or the entire field is absent, Debian defines
    the source version as equal to the binary package's Version field.

    Args:
        record (dict[str, str]): APT package metadata, including Package and
            Version, and optionally Source.

    Returns:
        str: Source version to compare with Trivy's required fixed version.

    Raises:
        RuntimeError: If Source is present but has an invalid format.
        KeyError: If a required APT metadata field is missing.
    """
    source = record.get("Source")
    if source is None:
        return record["Version"]

    match = re.fullmatch(r"[a-z0-9][a-z0-9+.-]+(?:\s+\(([^()\s]+)\))?", source.strip())
    if not match:
        raise RuntimeError(
            f"Malformed APT Source field for {record['Package']}: {source!r}"
        )
    return match.group(1) or record["Version"]


def check_freepbx_compatibility(
    name: str, version: str, record: dict[str, str]
) -> str | None:
    """Check whether an update breaks the FreePBX 17 compatibility policy.

    Preserve the official installer's holds on nodejs and node-* and keep PHP
    runtime and default-package updates within the supported PHP 8.2 branch.

    Args:
        name (str): Debian binary package name.
        version (str): Proposed Debian package version.
        record (dict[str, str]): APT metadata, optionally including Source.

    Returns:
        str | None: The reason the update is blocked, or None when these
            compatibility checks allow it.
    """
    # FreePBX 17 supports PHP 8.2; the official installer holds nodejs and node-*.
    # https://sangomakb.atlassian.net/wiki/spaces/FP/pages/279052296/
    # https://github.com/FreePBX/sng_freepbx_debian_install/blob/b023b8426786a518d25dfc0d11ba0c0314fcd927/sng_freepbx_debian_install.sh
    if name == "nodejs" or name.startswith("node-"):
        return "held by the FreePBX installer"

    # Match runtime names such as php8.2-cli without treating php-pear as PHP itself.
    runtime = re.match(r"^(?:php|libapache2-mod-php)(\d+\.\d+)(?:-|$)", name)

    # APT may append the source version in parentheses after the source name.
    source = record.get("Source", name).split()[0]

    php_error_msg = "outside the supported PHP 8.2 branch"
    if runtime and runtime.group(1) != "8.2":
        return php_error_msg
    if source == "php8.2" and not re.match(r"^(?:\d+:)?8\.2\.", version):
        return php_error_msg

    # PHP metapackages can select a different runtime without a versioned name.
    if source == "php-defaults" and not re.match(r"^(?:\d+:)?8\.2(?:[.+~]|$)", version):
        return "would change the default PHP branch"

    return None


def fetch(url: str, destination: Path | None = None) -> str:
    """Fetch an HTTPS resource with bounded timeouts and retries.

    Both the initial request and any redirects must use HTTPS. An existing
    destination file is overwritten when downloading to disk.

    Args:
        url (str): HTTPS URL of the resource.
        destination (Path | None): Download path, or None to capture the body.

    Returns:
        str: The response body when destination is None; otherwise an empty string.

    Raises:
        RuntimeError: If curl fails after applying its retry policy.
        OSError: If curl cannot be started.
    """
    args = [
        "curl",
        "--proto",
        "=https",
        "--proto-redir",
        "=https",
        "--fail",
        "--silent",
        "--show-error",
        "--location",
        "--connect-timeout",
        "20",
        "--max-time",
        "120",
        "--retry",
        "3",
        "--retry-max-time",
        "180",
    ]
    if destination is not None:
        args.extend(["--output", str(destination)])
    return run(*args, url)


def calculate_file_sha256(path: Path) -> str:
    """Calculate SHA-256 without loading the entire file into memory.

    Args:
        path (Path): File to read in 1 MiB chunks.

    Returns:
        str: Hexadecimal SHA-256 checksum of the file contents.

    Raises:
        OSError: If the file cannot be opened or read.
    """
    digest = hashlib.sha256()
    with path.open("rb") as file:
        while chunk := file.read(1024 * 1024):
            digest.update(chunk)
    return digest.hexdigest()


def read_deb_identity(path: Path) -> dict[str, str]:
    """Read the package name, version and architecture stored inside a .deb.

    Args:
        path (Path): Package archive to inspect without installing it.

    Returns:
        dict[str, str]: Package, Version, and Architecture fields from dpkg-deb.

    Raises:
        RuntimeError: If dpkg-deb fails or does not return one metadata record.
        OSError: If dpkg-deb cannot be started.
    """
    records = parse_package_metadata(
        run("dpkg-deb", "--field", str(path), "Package", "Version", "Architecture")
    )
    if len(records) != 1:
        raise RuntimeError(f"Cannot read package identity from {path}")
    return records[0]


def download_and_verify_snapshot_package(
    record: dict[str, str], temporary_dir: Path
) -> list[str]:
    """Download and verify a .deb from Debian Snapshot against APT metadata.

    Candidate .deb files must belong to the Debian or Debian Security archive
    and match the following APT metadata:
    - Package name
    - Version
    - Filename
    - Architecture
    - SHA-256 checksum

    Args:
        record (dict[str, str]): APT metadata containing Package, Version,
            Architecture, Filename, and SHA256.
        temporary_dir (Path): Existing download directory. Verified .deb files
            keep their APT filenames for reuse by the final manifest check.
            The caller owns their cleanup.

    Returns:
        list[str]: Manifest columns in order: package name, version, filename,
            immutable Snapshot URL, and SHA-256 checksum.

    Raises:
        RuntimeError: If a command fails, metadata is invalid, or no archived
            file matches the APT checksum and package identity.
        ValueError: If the Snapshot response is not valid JSON.
        KeyError: If required APT or Snapshot fields are missing.
        OSError: If a command cannot start or a local file cannot be read.
    """
    name, version = record["Package"], record["Version"]
    architecture, checksum = record["Architecture"], record["SHA256"]
    filename = Path(record["Filename"]).name

    if not re.fullmatch(r"[A-Za-z0-9_.+%:~\-]+\.deb", filename):
        raise RuntimeError(f"Unexpected APT filename: {filename!r}")

    # Debian epochs and suffixes may contain characters with URL semantics.
    api = (
        "https://snapshot.debian.org/mr/binary/"
        f"{quote(name, safe='')}/{quote(version, safe='')}/binfiles?fileinfo=1"
    )

    data = json.loads(fetch(api))

    hashes = set()

    for item in data["result"]:
        file_hash = item["hash"]
        if item["architecture"] != architecture:
            continue

        if not re.fullmatch(r"[0-9a-f]{40}|[0-9a-f]{64}", file_hash):
            raise RuntimeError(f"Unexpected Snapshot hash for {name}")

        locations = data.get("fileinfo", {}).get(file_hash, [])
        if any(
            location["archive_name"] in ("debian", "debian-security")
            and location["name"] == filename
            for location in locations
        ):
            hashes.add(file_hash)

    # A name/version can identify more than one file; the signed APT checksum decides.
    for file_hash in sorted(hashes):
        url = f"https://snapshot.debian.org/file/{file_hash}"
        downloaded = temporary_dir / filename
        fetch(url, downloaded)

        if calculate_file_sha256(downloaded) != checksum:
            continue

        # Verify the package's own identity after its bytes match the signed index.
        fields = read_deb_identity(downloaded)
        if any(
            fields.get(field) != record[field]
            for field in ("Package", "Version", "Architecture")
        ):
            raise RuntimeError(f"Snapshot package metadata differs from APT: {name}")

        return [name, version, filename, url, checksum]

    raise RuntimeError(
        f"No Snapshot file matches the APT checksum for {name}={version}"
    )


def load_findings(report_path: Path) -> dict[str, list[dict[str, Any]]]:
    """Read and group fixable Debian HIGH/CRITICAL findings from a Trivy report.

    Validate the reported Debian release and retain only OS package findings with
    a known fixed version.
    Group multiple vulnerabilities for the same package together.

    Args:
        report_path (Path): UTF-8 Trivy JSON report for the scanned image.

    Returns:
        dict[str, list[dict]]: Findings grouped by Debian binary package name.

    Raises:
        RuntimeError: If the report is not for Debian 12, has no Debian package
            result, or contains an invalid binary package name.
        ValueError: If the report is not valid JSON.
        KeyError: If a selected finding lacks its package name.
        OSError: If the report cannot be read.
    """
    report = json.loads(report_path.read_text(encoding="utf-8"))
    report_os = report.get("Metadata", {}).get("OS", {})
    if (
        report_os.get("Family") != "debian"
        or str(report_os.get("Name", "")).split(".", maxsplit=1)[0] != "12"
    ):
        raise RuntimeError("The report does not describe Debian 12")

    results = [
        result
        for result in report.get("Results", [])
        if result.get("Class") == "os-pkgs" and result.get("Type") == "debian"
    ]
    if not results:
        raise RuntimeError("The report contains no Debian package result")

    # Collect HIGH/CRITICAL Debian vulnerabilities with a known fixed version,
    # grouping them by package name.
    findings: dict[str, list[dict[str, Any]]] = {}
    for result in results:
        for finding in result.get("Vulnerabilities", []):
            if finding.get("Severity") in ("HIGH", "CRITICAL") and finding.get(
                "FixedVersion"
            ):
                name = finding["PkgName"]
                if not re.fullmatch(r"[a-z0-9][a-z0-9+.-]+", name):
                    raise RuntimeError(f"Invalid Debian package name: {name!r}")
                findings.setdefault(name, []).append(finding)
    return findings


def select_updates(
    options: list[str],
    findings: dict[str, list[dict[str, Any]]],
    approved: dict[str, list[str]],
) -> dict[str, str]:
    """Select available APT versions that satisfy the reported security fixes.

    Check the container against the scanned versions, enforce the FreePBX
    compatibility policy and preserve fixes already present in the approved
    manifest. Compare candidate source versions with Trivy's required fixed
    versions. Packages without a newer binary candidate whose source version
    satisfies all reported fixes are reported and skipped.

    Args:
        options (list[str]): APT options selecting verified Bookworm indexes.
        findings (dict[str, list[dict]]): Trivy findings grouped by binary package.
        approved (dict[str, list[str]]): Approved manifest rows keyed by package.

    Returns:
        dict[str, str]: Selected binary package names and exact target versions.

    Raises:
        RuntimeError: If a command fails, the report differs from the container,
            a version comparison fails, an APT Source field is malformed, or an
            approved checksum conflicts with APT.
        KeyError: If required finding or APT metadata fields are missing.
        OSError: If an external command cannot be started.
    """
    selected = {}
    for name, items in sorted(findings.items()):
        # Get the installed version and status from the container's dpkg database
        installed = run(
            "dpkg-query", "-W", "-f=${Version}\t${db:Status-Status}", name
        ).strip()

        if any(installed != f"{item['InstalledVersion']}\tinstalled" for item in items):
            raise RuntimeError(
                f"The report and container package versions differ: {name}"
            )

        # Debian findings use source versions for their fixed-version thresholds.
        # Require the highest source fix among this package's HIGH/CRITICAL CVEs.
        required_source_version = items[0]["FixedVersion"]
        for item in items[1:]:
            if compare(item["FixedVersion"], "gt", required_source_version):
                required_source_version = item["FixedVersion"]

        # Run apt-cache policy to find the candidate version available in Bookworm
        policy = run("apt-cache", *options, "policy", name)
        match = re.search(r"^\s*Candidate:\s*(\S+)", policy, re.MULTILINE)
        candidate = match.group(1) if match else "(none)"

        # The binary candidate must be newer than the installed binary version.
        if candidate == "(none)" or not compare(
            candidate, "gt", items[0]["InstalledVersion"]
        ):
            print(
                f"SKIP {name}: APT binary candidate {candidate} is not newer than "
                f"installed version {items[0]['InstalledVersion']}"
            )
            continue

        # Get the package record for the candidate version
        record = package_record(options, name, candidate)

        # The security fix must be present in the source used to build the .deb.
        # Binary and source versions can differ, so compare the source to Trivy.
        candidate_source_version = get_source_version(record)

        if not compare(candidate_source_version, "ge", required_source_version):
            print(
                f"SKIP {name}: binary candidate {candidate} has source version "
                f"{candidate_source_version}; required source fix "
                f"{required_source_version}"
            )
            continue

        # Check the candidate version against the FreePBX compatibility policy
        compatibility_problem = check_freepbx_compatibility(name, candidate, record)
        if compatibility_problem:
            print(f"SKIP [compatibility_problem] {name}: {compatibility_problem}")
            continue

        # Committed packages.tsv file may already contain a fix newer than the
        # published image.
        # So keep that approved version instead of replacing it with an older one.
        if name in approved and compare(approved[name][1], "ge", candidate):
            if approved[name][1] == candidate and approved[name][4] != record["SHA256"]:
                raise RuntimeError(
                    f"The approved checksum differs from APT: {name}={candidate}"
                )
            print(
                f"SKIP {name}: the approved manifest already has version {approved[name][1]}"
            )
            continue

        selected[name] = candidate
        print(
            f"SELECT {name}: {items[0]['InstalledVersion']} -> {candidate}; "
            f"source {candidate_source_version}; "
            f"required source fix {required_source_version}"
        )
    return selected


def run_apt_simulation(options: list[str], packages: list[str]) -> str:
    """Run an APT installation simulation, print its output, and return it.

    Both simulations use the same command options but receive different package
    lists. This command does not install packages; callers validate its plan.

    Args:
        options (list[str]): APT options selecting sources and temporary indexes.
        packages (list[str]): Exact name=version specifications or absolute
            paths to .deb files.

    Returns:
        str: Standard output containing APT's simulated plan.

    Raises:
        RuntimeError: If apt-get fails.
        OSError: If apt-get cannot be started.
    """
    plan = run(
        "apt-get",
        *options,
        "--simulate",
        "--verbose-versions",
        "--only-upgrade",
        "--no-install-recommends",
        "--no-remove",
        "install",
        *packages,
    )
    print(plan, end="")
    return plan


def simulate_upgrades(options: list[str], selected: dict[str, str]) -> dict[str, str]:
    """Simulate exact selected versions and validate the complete APT plan.

    Include dependency upgrades required by APT. Reject new installations,
    removals, downgrades, unsupported architectures and plans that fail to
    include every explicitly selected version.

    Args:
        options (list[str]): APT options selecting verified Bookworm indexes.
        selected (dict[str, str]): Nonempty mapping of packages to target versions.

    Returns:
        dict[str, str]: Every package upgrade planned by APT and its exact version.

    Raises:
        RuntimeError: If simulation fails or the plan violates these requirements.
        OSError: If an external command cannot be started.
    """
    # Simulate exact versions; include any additional upgrades required by APT.
    plan = run_apt_simulation(
        options, [f"{name}={version}" for name, version in selected.items()]
    )

    upgrades = parse_apt_upgrade_plan(plan)

    # Stop if APT's plan omits any requested package or selects a version
    # different from the exact version we requested for that package.
    if any(upgrades.get(name) != version for name, version in selected.items()):
        raise RuntimeError("The APT plan does not install every selected version")

    return upgrades


def parse_apt_upgrade_plan(plan: str) -> dict[str, str]:
    """Read APT simulation output and reject changes other than package upgrades.

    Shared by the selected-update simulation and the final manifest check so
    both reject removals, new installations, unsupported architectures, and
    versions that are not newer than the installed versions.

    Args:
        plan (str): Standard output from apt-get --simulate --verbose-versions.

    Returns:
        dict[str, str]: Planned binary package upgrades and their exact versions.

    Raises:
        RuntimeError: If the plan contains an unsupported change or a version
            comparison fails.
        OSError: If dpkg cannot be started for a version comparison.
    """
    upgrades = {}
    for line in plan.splitlines():
        if line.startswith("Remv "):
            raise RuntimeError("APT would remove a package")
        if not line.startswith("Inst "):
            continue

        # Existing-package upgrades include [installed-version].
        # If the field is missing, it would install a package that
        # was not already installed, so the plan gets rejected.
        match = re.match(r"^Inst (\S+) \[([^\]]+)\] \((\S+)", line)
        if not match:
            raise RuntimeError(
                f"APT would add a package or returned an unexpected plan: {line}"
            )

        name, installed, version = match.groups()

        # Reject any package that is not amd64.
        if ":" in name:
            name, architecture = name.rsplit(":", 1)
            if architecture != "amd64":
                raise RuntimeError(f"Unsupported architecture in the APT plan: {line}")

        # Require a version newer than the one currently installed in the container.
        if not compare(version, "gt", installed):
            raise RuntimeError(
                f"APT would not upgrade {name}: {installed} -> {version}"
            )

        upgrades[name] = version

    return upgrades


def verify_upgrades(
    options: list[str],
    upgrades: dict[str, str],
    approved: dict[str, list[str]],
    selected: dict[str, str],
    temporary_dir: Path,
) -> dict[str, list[str]]:
    """Verify planned upgrades and prepare rows for packages-proposed.tsv.

    Check dependency upgrades added by APT against the FreePBX compatibility
    policy. Reject conflicts with the approved manifest for all planned upgrades.

    For each new or replacement proposal row, download the .deb from Debian
    Snapshot and verify that its package name, version, architecture and SHA-256
    checksum match the APT metadata. Return the rows only after all files pass
    these checks.

    Args:
        options (list[str]): APT options selecting verified Bookworm indexes.
        upgrades (dict[str, str]): Exact versions from the validated APT plan.
        approved (dict[str, list[str]]): Approved manifest rows keyed by package.
        selected (dict[str, str]): Packages selected directly from Trivy findings,
            whose compatibility was already checked. Also used to distinguish
            their log messages from required dependencies.
        temporary_dir (Path): Download directory owned and cleaned by the caller.

    Returns:
        dict[str, list[str]]: Verified replacement or additional manifest rows
            keyed by binary package name.

    Raises:
        RuntimeError: If an update violates policy, conflicts with the approved
            manifest, fails a command, or cannot be verified against Snapshot.
        ValueError: If a Snapshot response is not valid JSON.
        KeyError: If required APT or Snapshot metadata fields are missing.
        OSError: If an external command or a downloaded file cannot be accessed.
    """
    proposals = {}
    for name, version in sorted(upgrades.items()):
        record = package_record(options, name, version)

        # Check compatibility only for dependency upgrades added by APT.
        if name not in selected:
            dep_compatibility_problem = check_freepbx_compatibility(
                name, version, record
            )
            if dep_compatibility_problem:
                raise RuntimeError(
                    f"APT requires an unsupported change to {name}: {dep_compatibility_problem}"
                )

        # Do not replace a version already approved in packages.tsv with an older one,
        # even if that version would upgrade the package installed in the container.
        if name in approved and compare(approved[name][1], "gt", version):
            raise RuntimeError(
                f"The APT plan would downgrade the approved manifest: {name}"
            )

        # If packages.tsv already contains this package at the planned version,
        # require its SHA-256 checksum to match APT's; otherwise stop with an error.
        # If it matches, just keep the approved row and skip the Snapshot download and checks.
        if name in approved and approved[name][1] == version:
            if approved[name][4] != record["SHA256"]:
                raise RuntimeError(
                    f"The approved checksum differs from APT: {name}={version}"
                )
            continue

        reason = "Trivy finding" if name in selected else "required by APT"
        print(f"VERIFY {name}={version} ({reason}) on Debian Snapshot", flush=True)

        # Download the .deb from Debian Snapshot and verify its package name, version,
        # architecture and SHA-256 checksum against APT metadata.
        # Store the verified manifest row in proposals.
        proposals[name] = download_and_verify_snapshot_package(record, temporary_dir)

    return proposals


def merge_proposal_rows(
    rows: list[list[str]], proposals: dict[str, list[str]]
) -> list[list[str]]:
    """Combine approved rows and verified updates without writing a file.

    Args:
        rows (list[list[str]]): Approved five-column manifest rows in their order.
        proposals (dict[str, list[str]]): Verified new or replacement rows by name.

    Returns:
        list[list[str]]: Final rows, preserving existing order and appending
            packages not yet listed in alphabetical order.
    """
    approved = {row[0] for row in rows}
    # Start from the approved packages.tsv rows. If a verified update exists
    # for a package, replace its row; otherwise keep the approved row unchanged.
    # Append verified rows for packages not yet listed, sorted by package name.
    proposed_rows = [proposals.get(row[0], row) for row in rows]
    proposed_rows.extend(
        proposals[name] for name in sorted(proposals) if name not in approved
    )
    return proposed_rows


def get_manifest_package_file(row: list[str], temporary_dir: Path) -> Path:
    """Get a manifest .deb and verify its checksum and package identity.

    Reuse a package already downloaded in this run or present in the published
    image. Download from the row's archived URL only if neither file exists.
    A checksum mismatch stops the procedure instead of selecting another file.
    Require the archive's Package and Version fields to match the manifest row,
    and Architecture to be amd64 or all; the manifest has no architecture column.

    Args:
        row (list[str]): Package name, version, filename, URL, and SHA-256.
        temporary_dir (Path): Download directory owned and cleaned by the caller.

    Returns:
        Path: Verified .deb path for APT's final simulation.

    Raises:
        RuntimeError: If the filename is invalid, the download fails, the file's
            checksum or identity differs from the manifest, or its architecture
            is unsupported.
        OSError: If the file cannot be read or an external command cannot start.
    """
    name, version, filename, url, checksum = row

    if not re.fullmatch(r"[A-Za-z0-9_.+%:~\-]+\.deb", filename):
        raise RuntimeError(f"Unexpected manifest filename: {filename!r}")

    downloaded = temporary_dir / filename
    image_file = Path("/opt/freepbx-security/debs") / filename

    # If the file is not already downloaded, check if it exists in the published image.
    if not downloaded.is_file():
        if image_file.is_file():
            downloaded = image_file
        else:
            fetch(url, downloaded)

    if calculate_file_sha256(downloaded) != checksum:
        raise RuntimeError(f"Manifest package checksum differs: {name}={version}")

    # A matching checksum does not prove that the row names the right package.
    # Read the .deb's own fields before passing the file to the APT simulation.
    fields = read_deb_identity(downloaded)
    if fields.get("Package") != name or fields.get("Version") != version:
        raise RuntimeError(
            f"Manifest package identity differs: expected {name}={version}, "
            f"found {fields.get('Package')}={fields.get('Version')}"
        )
    if fields.get("Architecture") not in ("amd64", "all"):
        raise RuntimeError(
            f"Unsupported manifest package architecture: {name}={version} "
            f"({fields.get('Architecture')})"
        )

    return downloaded


def verify_final_manifest(
    rows: list[list[str]], options: list[str], temporary_dir: Path
) -> None:
    """Require APT to accept all final manifest versions together.

    The simulated plan must match the exact versions listed in the final manifest.
    It must not upgrade packages outside that manifest, install packages not
    already present, remove packages, downgrade versions or use unsupported
    architectures.

    Args:
        rows (list[list[str]]): Complete final five-column manifest rows.
        options (list[str]): APT options selecting sources and temporary indexes.
        temporary_dir (Path): Package download directory owned by the caller.

    Raises:
        RuntimeError: If a file fails verification, APT rejects the combination,
            or its plan does not produce exactly the manifest versions without
            upgrading packages outside the manifest.
        OSError: If a package file or external command cannot be accessed.
    """
    versions = {row[0]: row[1] for row in rows}

    files = [get_manifest_package_file(row, temporary_dir) for row in rows]
    print("Checking dependencies of the complete final manifest with APT.", flush=True)

    plan = run_apt_simulation(options, [str(file) for file in files])

    # Get the planned upgrades from APT's simulation output and check that they
    # match all the package criterias.
    upgrades = parse_apt_upgrade_plan(plan)

    for name, version in upgrades.items():
        if versions.get(name) != version:
            raise RuntimeError(
                f"APT's final plan would change a package outside the requested "
                f"manifest versions: {name}={version}"
            )

    # A manifest version may already be installed and therefore absent from
    # APT's upgrade list. Every row must match either the plan or that installed
    # version; otherwise APT did not accept all requested files as intended.
    for name, version in versions.items():
        if name in upgrades:
            continue
        installed = run(
            "dpkg-query", "-W", "-f=${Version}\t${db:Status-Status}", name
        ).strip()
        if installed != f"{version}\tinstalled":
            raise RuntimeError(
                f"APT's final plan does not install manifest version {name}={version}"
            )

    print("Final manifest dependency check passed.")


def write_proposal(
    rows: list[list[str]], proposals: dict[str, list[str]], output_path: Path
) -> None:
    """Merge verified updates into rows and write an upgrade proposal.

    Keep the original row order for existing packages and append new packages
    sorted by name. Print the replacement, addition and unchanged-package counts.

    Args:
        rows (list[list[str]]): Approved five-column manifest rows in their order.
        proposals (dict[str, list[str]]): Verified new or replacement rows by name.
        output_path (Path): UTF-8 proposal path with an existing parent directory.

    Raises:
        OSError: If the proposal cannot be written.
    """
    approved = {row[0] for row in rows}
    replaced = sum(name in approved for name in proposals)
    added = len(proposals) - replaced
    proposed_rows = merge_proposal_rows(rows, proposals)
    output_path.write_text(
        "".join("\t".join(row) + "\n" for row in proposed_rows), encoding="utf-8"
    )

    print(
        f"Proposal: {replaced} replaced, {added} added, {len(rows) - replaced} unchanged; "
        f"{len(proposed_rows)} packages total."
    )


def prepare(report_path: Path, manifest_path: Path, output_path: Path) -> None:
    """Select, verify and write a package manifest proposal.

    Verify that the installed versions of the reported packages match the Trivy
    report. Select available APT versions that satisfy the reported security fixes
    and check that the complete APT plan respects the FreePBX compatibility policy.

    Download the .deb files from Debian Snapshot and verify that their package
    names, versions, architectures, and SHA-256 checksums match the APT metadata
    before writing the proposal.

    Stop if APT proposes to install packages not already present, remove packages,
    or downgrade versions.

    Check the dependencies of the complete final manifest with APT before
    writing it, including approved rows unchanged by this run. Extra upgrades
    and substitutions of manifest versions are rejected.

    An existing proposal is removed before validation. Existing approved rows in
    packages.tsv keep their order; new rows are appended by package name.
    Temporary indexes and package downloads are also removed even when preparation fails.

    Args:
        report_path (Path): UTF-8 Trivy JSON report for the container image.
        manifest_path (Path): Committed five-column packages.tsv file in UTF-8.
        output_path (Path): UTF-8 proposal path with an existing parent
            directory.

    Raises:
        RuntimeError: If validation or an external command fails, an APT plan
            requires an unsupported change, or Snapshot verification fails.
        ValueError: If an input JSON document is invalid.
        KeyError: If required report or package metadata fields are missing.
        OSError: If an input, temporary file, or proposal cannot be accessed.
    """
    if output_path.resolve() == manifest_path.resolve():
        raise RuntimeError("The output must not overwrite the approved manifest")

    # A failed run must not leave an older proposal looking like its new result.
    output_path.unlink(missing_ok=True)
    rows = [
        line.split("\t")
        for line in manifest_path.read_text(encoding="utf-8").splitlines()
    ]
    if not rows or any(len(row) != 5 or not all(row) for row in rows):
        raise RuntimeError("The approved manifest must contain five nonempty columns")
    approved = {row[0]: row for row in rows}
    if len(approved) != len(rows):
        raise RuntimeError("Duplicate packages in the approved manifest")

    # Use the image's own dpkg database and Debian release, not the host's APT state.
    os_release = dict(
        line.split("=", 1)
        for line in Path("/etc/os-release").read_text(encoding="utf-8").splitlines()
        if "=" in line
    )
    if (
        os_release.get("ID", "").strip('"') != "debian"
        or os_release.get("VERSION_ID", "").strip('"') != "12"
        or run("dpkg", "--print-architecture").strip() != "amd64"
    ):
        raise RuntimeError(
            "Run this script inside the pinned scanned Debian 12 amd64 image"
        )

    findings = load_findings(report_path)

    proposals: dict[str, list[str]] = {}
    with tempfile.TemporaryDirectory(prefix="freepbx-security-") as directory:
        temporary_dir = Path(directory)

        # Allow APT's _apt user to only traverse the temporary parent directory.
        temporary_dir.chmod(0o711)

        # Select Bookworm explicitly so a future stable-release change cannot move
        # this check to a newer Debian release.
        sources = temporary_dir / "sources.list"
        sources.write_text(APT_SOURCES, encoding="utf-8")

        # APT stores package indexes in lists/ and unfinished index downloads in
        # lists/partial/. Create both directories inside our temporary directory.
        (temporary_dir / "lists" / "partial").mkdir(parents=True)

        # Pass these settings to APT commands without changing the container's
        # existing package index directory.
        options = [
            # Read repository sources from the file we just created.
            "-o",
            f"Dir::Etc::sourcelist={sources}",
            # Ignore additional sources configured in /etc/apt/sources.list.d/.
            "-o",
            "Dir::Etc::sourceparts=-",
            # Download and read package indexes in our temporary lists/ directory.
            "-o",
            f"Dir::State::lists={temporary_dir / 'lists'}",
        ]

        # Refresh APT indexes only if Trivy reported HIGH/CRITICAL Debian
        # vulnerabilities with a known fixed version; otherwise skip this step.
        if findings:
            # Error-Mode=any makes any index update error fail the command
            print(
                run("apt-get", *options, "-o", "APT::Update::Error-Mode=any", "update"),
                end="",
            )

        # Get the update candidates versions
        selected = select_updates(options, findings, approved)

        if selected:
            # Simulate the installation of the selected versions and any required dependencies.
            upgrades = simulate_upgrades(options, selected)

            # Verify the complete APT plan and download the .deb files from Debian Snapshot.
            proposals = verify_upgrades(
                options, upgrades, approved, selected, temporary_dir
            )

        # Merge the verified proposals with the approved manifest rows, preserving the
        # order of existing rows and appending new rows sorted by package name.
        final_rows = merge_proposal_rows(rows, proposals)

        # Ask APT to check the dependencies of all final manifest versions together.
        # Stop before writing the proposal if they cannot be installed as specified.
        verify_final_manifest(final_rows, options, temporary_dir)

    # Write the verified proposal to disk, merging it with the approved manifest.
    write_proposal(rows, proposals, output_path)


def main() -> int:
    """Parse command-line paths and run proposal preparation.

    Force the C locale so APT output can be parsed consistently.
    Errors are printed to standard error for the workflow log.

    Returns:
        int: Zero on success, or one when preparation fails.

    Raises:
        SystemExit: If argparse handles a help request or rejects the arguments.
    """
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--report", required=True, type=Path)
    parser.add_argument("--manifest", required=True, type=Path)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()
    os.environ["LC_ALL"] = "C"
    try:
        prepare(args.report, args.manifest, args.output)
    except (RuntimeError, OSError, ValueError, KeyError) as error:
        print(f"ERROR: {error}", file=sys.stderr)
        return 1
    return 0


# Run only when executed directly, not when imported
if __name__ == "__main__":
    # Report success (0) or failure (1) to the workflow
    sys.exit(main())
