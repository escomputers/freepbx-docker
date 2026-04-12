#!/usr/bin/env bash
set -e

ensure_freepbx_cli_links() {
  local cli_name target_path

  for cli_name in fwconsole amportal; do
    target_path="/var/lib/asterisk/bin/${cli_name}"
    if [[ -e "$target_path" ]]; then
      ln -sfn "$target_path" "/usr/sbin/${cli_name}"
    fi
  done
}

ensure_freepbx_cli_links

# Start cron
/usr/sbin/cron &

# Start postfix email service
service postfix start

# Start Asterisk service
/usr/local/src/freepbx/start_asterisk start &

# Start Fail2ban
rm -f /var/run/fail2ban/fail2ban.pid /var/run/fail2ban/fail2ban.sock
fail2ban-client start &

exec apache2ctl -D FOREGROUND
