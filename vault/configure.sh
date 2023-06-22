vault token create -policy=administrator-policy
vault secrets enable database
vault write database/config/mysql-database \
    plugin_name=mysql-database-plugin \
    connection_url="{{username}}:{{password}}@tcp(172.18.0.2:3306)/" \
    allowed_roles="*" \
    username="vault" \
    password="vault_password"

vault write database/roles/asterisk-role \
    db_name=asterisk \
    creation_statements="CREATE USER '{{name}}'@'%' IDENTIFIED BY '{{password}}';GRANT ALL PRIVILEGES ON `asterisk`.* TO '{{name}}'@'%';" \
    default_ttl="1h" \
    max_ttl="24h"

vault write database/roles/asteriskcdrdb-role \
    db_name=asteriskcdrdb \
    creation_statements="CREATE USER '{{name}}'@'%' IDENTIFIED BY '{{password}}';GRANT ALL PRIVILEGES ON `asteriskcdrdb`.* TO '{{name}}'@'%';" \
    default_ttl="1h" \
    max_ttl="24h"

vault auth enable approle
vault policy write freepbx freepbx-policy.hcl
vault token create -policy="freepbx" -format json | jq -r '.auth | .client_token'
#vault write auth/approle/role/freepbx token_policies="freepbx"