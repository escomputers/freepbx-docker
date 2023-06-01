## Introduction

This is MVP [Docker Compose](https://docs.docker.com/compose/) application for having [FreePBX](https://www.freepbx.org) - A Voice over IP manager for [Asterisk](https://www.asterisk.org), running in containers.

Upon starting this multi-container application, it will give you a turn-key PBX system for SIP calling.

* FreePBX 16
* Asterisk 16
* MySQL/MariaDB database support
* Supports data persistence
* Base image Debian [bullseye-slim](https://hub.docker.com/_/debian/)
* Apache2
* NodeJS 14.x

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
* MySQL/MariaDB hardening

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
unzip asterisk-16.zip 

unzip freepbx.zip

# Set database password for freepbx user
sed -i "s/\$amp_conf\['AMPDBPASS'\] = 'md5(uniqid())';/\$amp_conf['AMPDBPASS'] = 'yourpassword';/" freepbx/installlib/installcommand.class.php

# Build and run
docker compose up -d --build
```

### Database setup
```bash
docker exec -it freepbx-docker-db-1 /bin/bash

mysql -h localhost -u root

CREATE USER 'freepbxuser'@'%' IDENTIFIED BY 'yourpassword';

GRANT ALL PRIVILEGES ON `asterisk`.* TO 'freepbxuser'@'%';

GRANT ALL PRIVILEGES ON `asteriskcdrdb`.* TO 'freepbxuser'@'%';

FLUSH PRIVILEGES;
```

### Freepbx setup
```bash
# Start Asterisk
docker compose exec freepbx /usr/src/freepbx/start_asterisk start

# Install FreePBX
docker compose exec freepbx /usr/src/freepbx/install -n --dbhost=db
```

Login to the web server's admin URL, enter your admin username, admin password, and email address and start configuring the system!