#!/bin/bash

ldconfig /usr/local/lib64/

# GENERATE RSA KEYS
if [[ "$*" == *"--gen-rsa"* ]]; then

    # Get encryption key variable from env
    encryption_key_env=$(env | grep -oP "(?<=ENCRYPTION_KEY=).*")
    # Add environment variable to /etc/profile
    echo "export ENCRYPTION_KEY='$encryption_key_env'" | tee -a /etc/profile > /dev/null

    echo "Generating RSA keys..."

    # Load profile variables
    source /etc/profile

    # Get encryption key
    encryption_key="$ENCRYPTION_KEY"

    # Create a temporary file to hold the password
    temp_file=$(mktemp)

    # Write the password to the temporary file
    echo "$encryption_key" > "$temp_file"

    # Generate private key
    /usr/local/bin/openssl genrsa -aes128 -passout file:"$temp_file" -out /root/freepbx_private.pem 4096

    # Extract public key
    /usr/local/bin/openssl rsa -passin file:"$temp_file" -in /root/freepbx_private.pem -pubout > /var/run/encrypted-secret/freepbx_public.pem

    # Remove the temporary file
    rm "$temp_file"
fi


# UPDATE FREEPBX CONFIG WITH NEW PASSWORD
if [[ "$*" == *"--update"* ]]; then
    echo "Reading secret..."
    # Load profile variables
    source /etc/profile

    # Get encryption key
    encryption_key="$ENCRYPTION_KEY"

    # Decrypt the content
    decrypted_content=$(/usr/local/bin/openssl pkeyutl -decrypt -passin 'pass:$encryption_key' -inkey /root/freepbx_private.pem -in /var/run/encrypted-secret/secret.enc)
    echo "Updating FreePBX configuration"
    # Escape any special characters in the decrypted content
    escaped_content=$(printf '%s\n' "$decrypted_content" | sed -e 's/[]\/$*.^[]/\\&/g')

    # Update
    sed -i "s/\(\$amp_conf\['AMPDBPASS'\] = \).*/\1'$escaped_content';/" /etc/freepbx.conf
fi


# INSTALL
if [[ "$*" == *"--install"* ]]; then
    echo "Installing..."
    # Load profile variables
    source /etc/profile

    # Get encryption key
    encryption_key="$ENCRYPTION_KEY"

    # Create a temporary file to hold the password
    temp_file=$(mktemp)

    # Write the password to the temporary file
    echo "$encryption_key" > "$temp_file"

    # Decrypt the content
    decrypted_content=$(/usr/local/bin/openssl pkeyutl -decrypt -passin file:"$temp_file" -inkey /root/freepbx_private.pem -in /var/run/encrypted-secret/secret.enc)

    # Escape any special characters in the decrypted content
    escaped_content=$(printf '%s\n' "$decrypted_content" | sed -e 's/[]\/$*.^[]/\\&/g')

    # Install
    command="php /usr/src/freepbx/install -n --dbuser=freepbxuser --dbpass=$(printf "%q" "$escaped_content") --dbhost=db"
    eval "$command"

    # Remove the temporary file
    rm "$temp_file"
fi


# ADD CRONJOB
if [[ "$*" == *"--add-cron"* ]]; then
    # Add cronjob
    echo "* * * * * bash /usr/local/bin/credentials.sh --update" >> update-credentials
    # Install new cron file
    crontab update-credentials
    # Start service
    service cron start
fi