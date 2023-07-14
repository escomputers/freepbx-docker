## Introduction

This is MVP [Docker Compose](https://docs.docker.com/compose/) application for having [FreePBX](https://www.freepbx.org) - A Voice over IP manager for [Asterisk](https://www.asterisk.org), running in containers.

Upon starting this multi-container application, it will give you a turnkey PBX system for SIP calling.

* FreePBX 16
* Asterisk 16
* MySQL database support
* Fail2ban pre-configured with restrictive enforcement rules
* Supports data persistence
* Base image Debian [bullseye-slim](https://hub.docker.com/_/debian/)
* Apache2
* NodeJS 14.x
* Hashicorp Vault (state of art) for secret management

Dockerfile scanned by:

<img src="https://dl.dropboxusercontent.com/s/is8aj5ld2ywfw6i/scanned-by-snyk.png" alt="scanned by snyk" width="151" height="86"></img>


### High level scenario
<img src="https://i.ibb.co/WG92pwK/Hashicorp-Vault-Database-integration.jpg" alt="logic-scenario" ></img>


### Volumes
| Directories        | Service |              
| ----------------   | ------- |          
| `/etc`             | freepbx |         
| `/usr`             | freepbx |            
| `/home/asterisk`   | freepbx |     
| `/var`             | freepbx | 
| `/var/run/encrypted-secret` | freepbx shared with vault sidecar |
| `/var/lib`         | mysql   |  
| `/vault`           | vault-transit | 
| `/vault`           | vault   | 


### Ports
The following ports are exposed via Docker.

| Port              | Description |
| ----------------- | ----------- |
| `80`              | HTTP        |
| `443`             | HTTPS       |
| `5038`            | AMI         |
| `8001`            | UCP         |
| `8003`            | UCP SSL     |
| `4569/udp`        | IAX         |
| `5060/udp`        | PJSIP       |
| `5160/udp`        | SIP         |
| `5161/udp`        | SIP         |

RTP ports `16384-32767/udp` require a particular configuration in order to be
properly exposed. There's a known issue about Docker and its way to expose a large range of ports, since each port exposed loads another process into memory and you may be experiencing a low memory condition.
As a trade-off, those ports are going to be exposed via Docker host `iptables` manually.
So, `build.sh` will take care of iptables configuration, besides building and running the image.

### Notes
- Docker iptables rules will bypass any ufw rule on the system.
- If host restarts, iptables rules will be deleted.
- For Windows hosts, all commands must be run as Administrator (do not use Visual Studio terminals, Docker must be run as administrator too). Moreover you could face low memory condition which could stop the build process. To fix this, you must ensure WSL is having proper RAM allocation, so create a file named .wslconfig inside user home directory `%UserProfile%` with the following content (adjust memory value according to your Windows host):
```
[wsl2]
memory=8GB
```

- Customize Fail2ban preferences by editing the file `fail2ban/jail.local`. Currently it bans 2 consecutive failed SIP registration attempts within 30 seconds for 1 week.

## Known issues
Dashboard loads very slowly, displayed correctly after 90 seconds.

---

## In progress
* TLS termination

---

## Requirements
- Docker >= 18.06.0+
- Git
- VoIP SIP trunk/trunks (DID/DIDs)

---

## Installation
```bash
# Create passwords for MySQL root user
printf "your-mysql-root-password" > mysql_root_password.txt

# Create passwords for Freepbx user
sed -i "s/'password'/'your-password'/g" init.sql

# Set proper file permissions
chmod 600 mysql_root_password.txt

# Don't worry, passwords will be rotated automatically by Vault everyday,
# rotation period can be customized by editing vault/configure.sh or via Vault UI.
# Do not set role TTL duration less than 60 seconds otherwise application won't be able to read it.

# Build and run
bash build.sh

# Configure first Vault instance for auto unsealing
docker compose exec vault-transit sh /build/configure.sh

# Run second Vault for secrets management (auto unsealed by first Vault instance)
docker run -d --name vault \
 --network=freepbx-docker_defaultnet \
 --ip=172.18.0.5 \
 -p 8100:8100 \
 -v vault:/vault \
 --cap-add=IPC_LOCK \
 -e VAULT_ADDR=http://127.0.0.1:8100 \
 -e VAULT_TOKEN=token-printed-by-configure.sh \
 -e MYSQL_ROOT_PASSWORD=$(cat mysql_root_password.txt) \
 vault:custom

# Configure Vault
docker exec -it vault sh /usr/local/bin/configure.sh

# Run Freepbx
docker run -d \
  --name freepbx \
  --cap-add=NET_ADMIN \
  -e ENCRYPTION_KEY=your-strong-encryption-key \
  -v var_run:/var/run/encrypted-secret \
  -v var_data:/var \
  -v etc_data:/etc \
  -v usr_data:/usr \
  -v asterisk_home:/home/asterisk \
  --network=freepbx-docker_defaultnet \
  --ip=172.18.0.20 \
  -p 80:80/tcp \
  -p 5038:5038/tcp \
  -p 8001:8001/tcp \
  -p 8003:8003/tcp \
  -p 4569:4569/udp \
  -p 5060:5060/udp \
  -p 5061:5061/udp \
  -p 5160:5160/udp \
  -p 5161:5161/udp \
  escomputers/freepbx:latest

# Run FreePbx sidecar (ENCRYPTION_KEY must be the same of FreePbx ENCRYPTION_KEY)
docker run -d \
  --name sidecar-freepbx \
  -e VAULT_ADDR=http://172.18.0.5:8100 \
  -e VAULT_TOKEN=token-printed-by-usr_local_bin_configure.sh \
  -e ENCRYPTION_KEY=your-strong-encryption-key \
  -v var_run:/var/run/encrypted-secret \
  --network=freepbx-docker_defaultnet \
  sidecar:latest

# Install Freepbx
docker exec -it freepbx bash /usr/local/bin/credentials.sh --install
```

Login to the web server's admin URL, enter your admin username, admin password and email address and start configuring the system!

### Optional but recommended steps
```bash
docker exec -it freepbx fwconsole ma disablerepo commercial
docker exec -it freepbx fwconsole ma installall
docker exec -it freepbx fwconsole ma delete firewall
docker exec -it freepbx fwconsole reload
docker exec -it freepbx fwconsole restart
```