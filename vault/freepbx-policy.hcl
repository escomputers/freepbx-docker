# Read dynamic database secrets
path "database/creds/asterisk-role"
{
  capabilities = ["read"]
}

path "database/creds/asteriskcdrdb-role"
{
  capabilities = ["read"]
}

