vault token create -policy=administrator-policy
vault secrets enable database
vault write database/config/mysql-db \
    plugin_name=mysql-database-plugin \
    connection_url="{{username}}:{{password}}@tcp(172.18.0.2:3306)/" \
    allowed_roles="mysql-role" \
    username="vault" \
    password="vault_password"