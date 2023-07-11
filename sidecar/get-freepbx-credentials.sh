#!/bin/bash

while true; do
    # Configure Vault server details
    vaultAddress=$VAULT_ADDR
    vaultToken=$VAULT_TOKEN

    # Set the path to the Vault endpoint
    vaultPath='database/static-creds/freepbx-role'

    # Set up HTTP headers for the API request
    headers=(
        "X-Vault-Token: $vaultToken"
    )

    # Make the API request to Vault
    response=$(curl -s -H "${headers[@]}" "$vaultAddress/v1/$vaultPath")

    # Parse the JSON response from Vault
    app_token=$(echo "$response" | jq -r '.data.password')

    # Encryption key  from env
    encryption_key="$ENCRYPTION_KEY"

    # Generate private key
    echo "$encryption_key" | openssl genrsa -aes128 -passout stdin -out /root/sidecar_private.pem 4096

    # Extract public key
    echo "$encryption_key" | openssl rsa -passin stdin -in /root/sidecar_private.pem -pubout > /var/run/encrypted-secret/sidecar_public.pem

    # Create a temporary file to hold the secret
    temp_file=$(mktemp)

    # Write the password to the temporary file
    echo "$app_token" > "$temp_file"

    # Encrypt the secret content
    openssl pkeyutl -encrypt -inkey /var/run/encrypted-secret/freepbx_public.pem -pubin -in "$temp_file" -out /var/run/encrypted-secret/secret.enc

    # Remove the temporary file
    rm "$temp_file"

    # Time delay
    sleep 60
done