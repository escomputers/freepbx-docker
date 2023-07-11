# Start Asterisk service
/usr/src/freepbx/start_asterisk start &

# Generate RSA keys
bash /usr/local/bin/credentials.sh --gen-keys

# Generate ENCRYPTION_KEY
bash /usr/local/bin/secret-gen.sh

# Start crontab
service cron start

# Start Fail2ban
fail2ban-client start &

apache2ctl -D FOREGROUND