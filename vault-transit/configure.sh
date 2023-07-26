# initialize
vault operator init -key-shares=1 -key-threshold=1 > /build/output.txt

# grab root token and unseal key
root_token=$(grep -o "Initial Root Token: .*" /build/output.txt | awk '{print $4}')
unseal_key=$(grep -o "Unseal Key 1: .*" /build/output.txt | awk '{print $4}')

# unseal and login for the first time
vault operator unseal $unseal_key
vault login $root_token

# enable audit device
vault audit enable file file_path=/vault/logs/audit.log

# enable transit secret engine
vault secrets enable transit

# create an encryption key named, "autounseal"
vault write -f transit/keys/autounseal

# create a policy named autounseal
vault policy write autounseal /build/autounseal.hcl

# create orphan periodic client token with the autounseal policy
# attached and response wrap it with TTL of 120 seconds
vault token create -orphan -policy="autounseal" -wrap-ttl=300 -period=24h > /build/token.txt

# grab wrapping_token
wrapping_token=$(grep -o "wrapping_token: .*" /build/token.txt | awk '{print $2}')

VAULT_TOKEN=$wrapping_token vault unwrap > /build/root_token_out.txt
token=$(grep -o "token .*" /build/root_token_out.txt | awk '{print $2}')
echo "Please copy the following token, needed by Vault:"
echo ""
echo "$token" && rm /build/*.txt
echo ""
echo "Please copy the following unseal key:"
echo "$unseal_key"