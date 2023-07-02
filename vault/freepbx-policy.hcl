# Read database secrets
path "database/static-creds/*"
{
  capabilities = ["read"]
}

path "database/roles/*"
{
  capabilities = ["read"]
}