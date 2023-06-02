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
| Directory        |Description                                                                       |
| ---------------- | ----------------------------------------------------------------------------------------------------- |
| `/usr/src`         | FreePBX files                                                                  |
| `/var/lib/mysql`   | Database files                                                                  |

### Ports
The following ports are exposed.

| Port              | Description |
| ----------------- | ----------- |
| `80`              | HTTP        |
| `443`             | HTTPS       |
| `4445`            | FOP         |
| `4569`            | IAX         |
| `5060`            | PJSIP       |
| `5160`            | SIP         |
| `8001`            | UCP         |
| `8003`            | UCP SSL     |
| `8008`            | UCP         |
| `8009`            | UCP SSL     |
| `18000-20000/udp` | RTP ports   |

---

## In progress
* TLS termination (in progress)

---

## Requirements
- Docker
- Git
- VoIP SIP trunk/trunks (DID/DIDs)

---

## Installation (this procedure will change soon)

### Build application
Clone repository, then:

```bash
# Extract source files
unzip asterisk-16.zip 

unzip freepbx.zip

# Create passwords for both MySQL root user and freepbxuser
printf "yourstrongmysqlrootpassword" > mysql_root_password.txt
printf "yourstrongmysqlfreepbxuserpassword" > freepbxuser_password.txt

# Build and run
docker compose up -d --build
```

### Freepbx setup
```bash
# Start Asterisk
docker compose exec freepbx /usr/src/freepbx/start_asterisk start

# Install FreePBX
docker compose exec freepbx /usr/src/freepbx/install -n --dbpass=$(cat /run/secrets/mysql_root_password) --dbhost=db
```

Login to the web server's admin URL, enter your admin username, admin password, and email address and start configuring the system!