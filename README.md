### Build
```
unzip asterisk-16.zip 

unzip freepbx.zip

sed -i "s/\$amp_conf\['AMPDBPASS'\] = 'md5(uniqid())';/\$amp_conf['AMPDBPASS'] = 'yourpassword';/" freepbx/installlib/installcommand.class.php

docker compose up -d --build
```

---

### Database container setup
```
docker exec -it freepbx-docker-freepbx-1 /bin/bash

mysql -h localhost -u root

CREATE USER 'freepbxuser'@'%' IDENTIFIED BY 'yourpassword';

GRANT ALL PRIVILEGES ON `asterisk`.* TO 'freepbxuser'@'%';

GRANT ALL PRIVILEGES ON `asteriskcdrdb`.* TO 'freepbxuser'@'%';

FLUSH PRIVILEGES;
```

---


### Freepbx container setup
```
# Start Asterisk
docker compose exec freepbx /usr/src/freepbx/start_asterisk start

# Install
docker compose exec freepbx /usr/src/freepbx/install -n --dbhost=db
```