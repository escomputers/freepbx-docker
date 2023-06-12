ui            = true
api_addr      = "https://0.0.0.0:8200"
disable_mlock = true
default_lease_ttl = "168h"
max_lease_ttl = "720h"

storage "file" {
  path    = "/vault/file"
}

listener "tcp" {
  address       = "0.0.0.0:8200"
  tls_disable   = true
}