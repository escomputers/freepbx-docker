## 17-nofail2ban-r1

This published tag starts from the original image `escomputers/freepbx:17-nofail2ban` (identified by its digest) and brings the following changes:

1. the `pacchetti/` directory, which contains:
   - `pacchetti/debs/`, containing the 104 security update packages (`.deb` files, not committed here)
   - `pacchetti/packages.tsv`, which records each package's name and version

2. `apply-security-updates.sh`, which updates existing volumes before starting the services

3. `entrypoint.sh`, which calls that script and starts the services.

### Build

Run the following commands from the repository root.

```bash
# Download the .tar.gz archive containing the 104 .deb files
curl --proto '=https' -fL \
  'https://pub-e2bfdd830b5a48c4b69d970e86f38957.r2.dev/freepbx-17-r1-r2-debs.tar.gz' \
  -o freepbx-17-r1-r2-debs.tar.gz &&

# Verify checksum
printf '%s  %s\n' \
  'd5f9f86be753375df32294869b473f51dace3219541cc3b91db9b1e695ebd3d0' \
  'freepbx-17-r1-r2-debs.tar.gz' |
shasum -a 256 --check - &&

# Extract packages
mkdir -p archived/published-images/17-nofail2ban-r1/pacchetti/debs &&
tar -xzf freepbx-17-r1-r2-debs.tar.gz -C archived/published-images/17-nofail2ban-r1/pacchetti/debs &&
rm -f freepbx-17-r1-r2-debs.tar.gz &&

# Build
docker build -t freepbx:r1-rebuild ./archived/published-images/17-nofail2ban-r1
```

## 17-nofail2ban-r2

This published tag starts from `escomputers/freepbx:17-nofail2ban-r1` (identified by its digest) and brings the following changes:

1. `Dockerfile`, which fixes the FreePBX installer's exit code after a successful installation
2. `entrypoint.sh`, which starts FreePBX and its module services with `fwconsole start` when FreePBX is already installed.

Build: `docker build -t freepbx:r2-rebuild ./archived/published-images/17-nofail2ban-r2`
