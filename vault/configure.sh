# grab mysql root password from environment
password="$MYSQL_ROOT_PASSWORD"

# create temp directory for configuring Vault
mkdir -p /build

# initialize Vault with its transit engine
vault operator init > /build/output.txt

# grab root token
root_token=$(grep -o "Initial Root Token: .*" /build/output.txt | awk '{print $4}')

unset VAULT_TOKEN
vault login $root_token

# create administrator policy to dismiss the root token
vault policy write administrator-policy /usr/local/bin/administrator-policy.hcl

# create associated token
vault token create -policy=administrator-policy > /build/admin-token.txt

# grab admin token
admin_token=$(grep -o "token .*" /build/admin-token.txt | awk '{print $2}')
echo "Please copy the following token to keep using Vault:"
echo "$admin_token"
echo ""

vault login $admin_token

# revoke root token
#vault token revoke $root_token

# enable database secret engine
vault secrets enable database

# configure connection from Vault to MySql container
vault write database/config/mysql-database \
    plugin_name=mysql-database-plugin \
    connection_url="{{username}}:{{password}}@tcp(172.18.0.2:3306)/" \
    allowed_roles="*" \
    username="root" \
    password=$password

# set root credentials rotation static role
vault write database/static-roles/root-rotation \
    db_name=mysql-database \
    rotation_statements="ALTER USER '{{name}}'@'localhost' IDENTIFIED BY '{{password}}';" \
    username="root" \
    rotation_period=86400

: '
# dynamic roles
vault write database/roles/asterisk-role \
    db_name=mysql-database \
    creation_statements="CREATE USER '{{name}}'@'%' IDENTIFIED BY '{{password}}';GRANT ALL PRIVILEGES ON asterisk.* TO '{{name}}'@'%';" \
    default_ttl="1h" \
    max_ttl="24h"
vault write database/roles/asteriskcdrdb-role \
    db_name=mysql-database \
    creation_statements="CREATE USER '{{name}}'@'%' IDENTIFIED BY '{{password}}';GRANT ALL PRIVILEGES ON asteriskcdrdb.* TO '{{name}}'@'%';" \
    default_ttl="1h" \
    max_ttl="24h"
'

# enable app role authentication method
vault auth enable approle

# create a policy which allows app to read database credentials
vault policy write freepbx /usr/local/bin/freepbx-policy.hcl

# create associated token for the app
echo ""
echo "Please copy the following token, needed by application:"
vault token create -policy="freepbx" -format json | jq -r '.auth | .client_token'

# remove build directory
rm -rf /build