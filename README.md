## Introduction

This is MVP [Docker Compose](https://docs.docker.com/compose/) application for having [FreePBX](https://www.freepbx.org) - A Voice over IP manager for [Asterisk](https://www.asterisk.org), running in containers.

Upon starting this multi-container application, it will give you a turn-key PBX system for SIP calling.

* FreePBX 16
* Asterisk 16
* MySQL database support
* Supports data persistence
* Base image Debian [bullseye-slim](https://hub.docker.com/_/debian/)
* Apache2
* NodeJS 14.x

Dockerfile 

<img src="https://dl.dropboxusercontent.com/s/is8aj5ld2ywfw6i/scanned-by-snyk.png" alt="scanned by snyk" width="151" height="86"></img>

### Volumes
| Directory           | Description                     |
| ----------------   | --------------------             |
| `/var`             | App & Database files             |
| `/etc`             | App configuration files          |
| `/usr`             | Source and app files             |
| `/home/asterisk`   | Asterisk user home directory     |

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


## Known issues
Dashboard loads very slowly, displayed correctly after 90 seconds.

---

## In progress
* TLS termination

---

## Requirements
- Docker >= 18.06.0+
- Docker host Linux with iptables installed
- Git
- VoIP SIP trunk/trunks (DID/DIDs)

---

## Installation

### Build and run application
Clone repository, then:

```bash
# Extract source files
unzip asterisk-16.zip

unzip freepbx.zip

# Create passwords for both MySQL root user and freepbxuser
printf "yourstrongmysqlrootpassword" > mysql_root_password.txt
printf "yourstrongmysqlfreepbxuserpassword" > freepbxuser_password.txt

# Set proper file permissions
chmod 600 mysql_root_password.txt freepbxuser_password.txt

# Build and run
bash build.sh
```

### Freepbx setup
```bash
# Install FreePBX
docker exec -it freepbx-docker-freepbx-1 /bin/bash

cd freepbx/ && ./install -n --dbpass=$(cat /run/secrets/mysql_root_password) --dbhost=db
```

Login to the web server's admin URL, enter your admin username, admin password, and email address and start configuring the system!