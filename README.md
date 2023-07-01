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

Dockerfile 

<img src="https://dl.dropboxusercontent.com/s/is8aj5ld2ywfw6i/scanned-by-snyk.png" alt="scanned by snyk" width="151" height="86"></img>

### Volumes
| Directories        |               
| ----------------   |            
| `/etc`             |          
| `/usr`             |          
| `/home/asterisk`   |     
| `/var              |


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
# Create passwords for both MySQL root user and freepbxuser
printf "yourstrongmysqlrootpassword" > mysql_root_password.txt

# Set proper file permissions
chmod 600 mysql_root_password.txt

# Build and run
bash build.sh

# Run Vault for secrets management
docker compose exec vault-transit sh /build/configure.sh

docker run --name vault --network=freepbx-docker_defaultnet --ip=172.18.0.5 -d -p 8100:8100 -v vault:/vault --cap-add=IPC_LOCK -e VAULT_ADDR=http://127.0.0.1:8100 -e VAULT_TOKEN=token-printed-by-configure.sh -e MYSQL_ROOT_PASSWORD=$(cat mysql_root_password.txt) vault:custom

# Configure Vault
docker exec -it vault sh /usr/local/bin/configure.sh

# Install Freepbx
# export VAULT_TOKEN=
docker compose exec freepbx php /usr/src/install-freepbx.php
```

Login to the web server's admin URL, enter your admin username, admin password, and email address and start configuring the system!

### Optional but recommended steps
```bash
docker compose exec freepbx fwconsole ma disablerepo commercial
docker compose exec freepbx fwconsole ma installall
docker compose exec freepbx fwconsole ma delete firewall
docker compose exec freepbx fwconsole reload
docker compose exec freepbx fwconsole restart
```