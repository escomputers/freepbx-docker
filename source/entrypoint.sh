# Start Asterisk service
/usr/src/freepbx/start_asterisk start &

# Generate ENCRYPTION_KEY
bash /usr/local/bin/credentials.sh --gen-key

# Generate RSA keys
bash /usr/local/bin/credentials.sh --gen-rsa

# Add cronjob
bash /usr/local/bin/credentials.sh --add-cron

# Start Fail2ban
fail2ban-client start &

apache2ctl -D FOREGROUND