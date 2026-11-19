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

# The upgrade test sets the system identifier before replacing the container.
if [[ $# -eq 1 ]]; then
  docker compose exec -T freepbx fwconsole setting FREEPBX_SYSTEM_IDENT |
    grep -F "[$1]"
fi
