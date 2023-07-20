# Start Asterisk service
/usr/src/freepbx/start_asterisk start &

# Start Fail2ban
fail2ban-client start &

apache2ctl -D FOREGROUND