# copy administrator-policy with Dockerfile
vault token create -policy=administrator-policy
# set VAULT_TOKEN with new administrator token

vault secrets enable database

vault write database/config/mysql-database \
    plugin_name=mysql-database-plugin \
    connection_url="{{username}}:{{password}}@tcp(172.18.0.2:3306)/" \
    allowed_roles="*" \
    username="vault" \
    password="vault_password"

vault write database/static-roles/asterisk \
    db_name=mysql-database \
    rotation_statements="ALTER USER '{{name}}' WITH PASSWORD '{{password}}';" \
    username="asterisk" \
    rotation_period=86400

vault write database/static-roles/freepbx \
    db_name=mysql-database \
    rotation_statements=@rotation.sql \
    username="freepbxuser" \
    rotation_period=86400

: '
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

vault auth enable approle
vault policy write freepbx freepbx-policy.hcl
vault token create -policy="freepbx" -format json | jq -r '.auth | .client_token'