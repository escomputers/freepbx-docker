#!/bin/bash

# Generate a random password
password=$(/usr/local/bin/openssl/bin/openssl rand -base64 64)

# Export the password as an environment variable
export ENCRYPTION_KEY="$password"

# Append the export command to /etc/profile
echo "export ENCRYPTION_KEY='$password'" | tee -a /etc/profile