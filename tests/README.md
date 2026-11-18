## Security update script tests

Tests for `.github/scripts/prepare_security_updates.py` that cover:
source-version metadata, process exit statuses, FreePBX runtime
limits, Trivy finding selection, cumulative manifest preservation and rejection
of corrupt cached archives.

### Unit tests

```bash
python tests/test_security_updates_unit.py
```

## Integration tests

Requirements:
- Docker engine supporting Linux amd64 containers.

```bash
python tests/run_integration_tests.py
```

Tests run in a temporary Debian 12 container, using real APT and dpkg commands
with locally generated test packages:
- use source package versions to determine whether an update includes the required fix
- accept compatible package combinations and rejects incompatible dependencies
- reject `.deb` files whose package name, version, architecture or checksum fails validation
- leave the approved manifest unchanged and removes stale proposals when a check fails.
