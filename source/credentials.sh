#!/bin/bash

# Load profile variables
source /etc/profile

# Get encryption key
encryption_key="$ENCRYPTION_KEY"

if [[ "$*" == *"--gen-keys"* ]]; then
    # Generate RSA keys
    echo "Generating RSA keys..."

    # Create a temporary file to hold the password
    temp_file=$(mktemp)

    # Write the password to the temporary file
    echo "$encryption_key" > "$temp_file"

    # Generate private key
    /usr/local/bin/openssl/bin/openssl genrsa -aes128 -passout pass:$(cat "$temp_file") -out /root/freepbx_private.pem 4096

    # Extract public key
    /usr/local/bin/openssl/bin/openssl rsa -passin pass:$(cat "$temp_file") -in /root/freepbx_private.pem -pubout > /var/run/encrypted-secret/freepbx_public.pem

    # Remove the temporary file
    rm "$temp_file"
fi

if [[ "$*" == *"--update"* ]]; then
    echo "Reading secret..."
    # Decrypt the content
    decrypted_content=$(/usr/local/bin/openssl/bin/openssl pkeyutl -decrypt -passin pass:$encryption_key -inkey /root/freepbx_private.pem -in /var/run/encrypted-secret/secret.enc)
    echo "Updating FreePBX configuration"
    # Escape any special characters in the decrypted content
    escaped_content=$(printf '%s\n' "$decrypted_content" | sed -e 's/[]\/$*.^[]/\\&/g')

    # Update
    sed -i "s/\(\$amp_conf\['AMPDBPASS'\] = \).*/\1'$escaped_content';/" /etc/freepbx.conf
    
fi

if [[ "$*" == *"--install"* ]]; then
    # Run install command when --install argument is present
    echo "Installing..."
    # Decrypt the content
    decrypted_content=$(/usr/local/bin/openssl/bin/openssl pkeyutl -decrypt -passin pass:$encryption_key -inkey /root/freepbx_private.pem -in /var/run/encrypted-secret/secret.enc)
    command="php /usr/src/freepbx/install -n --dbuser=freepbxuser --dbpass=$(printf "%q" "$decrypted_content") --dbhost=db"
    eval "$command"
fi
