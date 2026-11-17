"""Run the integration suite in its own disposable Debian 12 amd64 container."""

import signal
import subprocess
import sys
import uuid
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
IMAGE = (
    "docker.io/library/python@sha256:"
    "e8c69d86735f6df3efa06b324694fe51f0422785f83a3bd463052636c2421944"
)
CONTAINER_ROOT = "/tmp/freepbx-security-tests"


def docker(*args: str, check: bool = True) -> subprocess.CompletedProcess[str]:
    """Run a Docker CLI command and capture output for useful failure diagnostics.

    Args:
        *args (str): Docker command arguments.
        check (bool): Raise if the command fails.

    Returns:
        subprocess.CompletedProcess[str]: Command output and exit status.

    Raises:
        RuntimeError: If a checked command fails.
        OSError: If Docker cannot be started.
    """
    result = subprocess.run(
        ["docker", *args], text=True, capture_output=True, check=False
    )
    if check and result.returncode:
        raise RuntimeError(result.stdout + result.stderr)
    return result


def main() -> int:
    """Create the test container, run unittest, and remove only owned resources."""
    container = None
    downloaded_image = None
    exit_code = 1
    # Let SIGTERM, like Ctrl-C, reach finally so this runner cleans up its container.
    signal.signal(signal.SIGTERM, signal.default_int_handler)
    try:
        # Check the engine before pulling anything. No existing container is used.
        docker("info", "--format", "{{.ID}}")
        if docker("image", "inspect", IMAGE, check=False).returncode:
            print("Downloading the pinned Debian 12 amd64 test image.", flush=True)
            docker("pull", "--platform", "linux/amd64", IMAGE)
            downloaded_image = docker(
                "image", "inspect", "--format", "{{.Id}}", IMAGE
            ).stdout.strip()

        name = "freepbx-security-tests-" + uuid.uuid4().hex[:12]
        container = docker(
            "create",
            "--name",
            name,
            "--label",
            "freepbx.security-tests=true",
            "--platform",
            "linux/amd64",
            "--network",
            "none",
            "--read-only",
            "--cap-drop",
            "ALL",
            "--security-opt",
            "no-new-privileges=true",
            "--cap-add",
            "FOWNER",
            "--cap-add",
            "CHOWN",
            "--cap-add",
            "SETUID",
            "--cap-add",
            "SETGID",
            "--cap-add",
            "DAC_OVERRIDE",
            "--memory",
            "768m",
            "--pids-limit",
            "128",
            "--tmpfs",
            "/tmp:rw,nosuid,nodev,size=536870912",
            "--tmpfs",
            "/opt/freepbx-security/debs:rw,nosuid,nodev,size=67108864",
            "--entrypoint",
            "sleep",
            IMAGE,
            "infinity",
        ).stdout.strip()
        docker("start", container)
        docker(
            "exec",
            container,
            "mkdir",
            "-p",
            f"{CONTAINER_ROOT}/tests",
            f"{CONTAINER_ROOT}/.github/scripts",
        )
        # Send only the two needed files through stdin. Host paths need not be
        # visible to Docker, and the files can be written into the /tmp tmpfs.
        for relative in (
            ".github/scripts/prepare_security_updates.py",
            "tests/test_security_updates_integration.py",
        ):
            subprocess.run(
                [
                    "docker",
                    "exec",
                    "-i",
                    container,
                    "tee",
                    f"{CONTAINER_ROOT}/{relative}",
                ],
                input=(ROOT / relative).read_bytes(),
                stdout=subprocess.DEVNULL,
                check=True,
            )

        # Stream unittest output while preserving its exit status for local use or CI.
        exit_code = subprocess.run(
            [
                "docker",
                "exec",
                "--env",
                "FREEPBX_INTEGRATION_CONTAINER=1",
                container,
                "python3",
                "-B",
                "-m",
                "unittest",
                "discover",
                "-s",
                f"{CONTAINER_ROOT}/tests",
                "-p",
                "test_security_updates_integration.py",
                "-b",
                "-v",
            ],
            check=False,
            timeout=300,
        ).returncode
    except KeyboardInterrupt:
        print(
            "Integration tests interrupted; cleaning up the test container.",
            file=sys.stderr,
        )
        exit_code = 130
    except subprocess.TimeoutExpired:
        print("Integration tests exceeded five minutes.", file=sys.stderr)
    except (OSError, RuntimeError, subprocess.CalledProcessError) as error:
        print(f"Integration runner failed: {error}", file=sys.stderr)
    finally:
        if container:
            # The ID comes only from this invocation's docker create command.
            result = docker("rm", "--force", container, check=False)
            if result.returncode:
                exit_code = 1
                print(
                    f"Could not remove test container {container}: {result.stderr}",
                    file=sys.stderr,
                )
        if downloaded_image:
            # Keep pre-existing images. Never force removal if another container
            # has begun using an image downloaded by this invocation.
            result = docker("image", "rm", downloaded_image, check=False)
            if result.returncode:
                print(f"Test image retained: {result.stderr}", file=sys.stderr)
    return exit_code


if __name__ == "__main__":
    sys.exit(main())
