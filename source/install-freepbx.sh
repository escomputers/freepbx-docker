#!/bin/bash

# Function to make API call to Vault and retrieve MySQL credentials
function getMySQLCredentialsFromVault() {
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
    password=$(echo "$response" | jq -r '.data.password')

}

password=$(getMySQLCredentialsFromVault)

# Run install.php with command-line arguments
command="php /usr/src/freepbx/install -n --dbuser=freepbxuser --dbpass=$(printf "%q" "$password") --dbhost=db"
eval "$command"

