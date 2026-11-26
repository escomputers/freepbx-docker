#!/usr/bin/env bash
set -euo pipefail

docker compose exec -T freepbx timeout 60s asterisk -rx 'core waitfullybooted' |
  grep -F 'Asterisk has fully booted.'
status=$(docker compose exec -T freepbx curl --silent --show-error \
  --max-time 15 --output /dev/null --write-out '%{http_code}' \
  http://127.0.0.1/admin/config.php)
echo "Admin page HTTP status: $status"
test "$status" = 200
docker compose exec -T freepbx fwconsole reload

# The upgrade test stores the same identifier in FreePBX and in both volumes.
if [[ $# -eq 1 ]]; then
  docker compose exec -T freepbx sh -ec '
    for path in /etc/.freepbx-ci-etc /var/.freepbx-ci-var; do
      if [ ! -f "$path" ]; then
        echo "Volume persistence check failed: $path is missing." >&2
        exit 1
      fi
      if ! printf "%s\n" "$1" | cmp -s - "$path"; then
        echo "Volume persistence check failed: $path does not contain the expected run ID." >&2
        exit 1
      fi
    done
  ' sh "$1"
  docker compose exec -T freepbx fwconsole setting FREEPBX_SYSTEM_IDENT |
    grep -F "[$1]"
fi
