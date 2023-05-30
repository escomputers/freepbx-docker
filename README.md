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
docker exec -it freepbx-docker-db-1 /bin/bash

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
docker compose exec freepbx /usr/src/freepbx/install --webroot=/usr/local/apache2/htdocs -n --dbhost=db

docker compose exec freepbx sed -i 's/\/var\/www\/html/\/usr\/local\/apache2\/htdocs/g' /etc/apache2/sites-available/000-default.conf

docker compose exec freepbx sed -i 's/\/var\/www\/html/\/usr\/local\/apache2\/htdocs/g' /etc/apache2/sites-available/default-ssl.conf

docker compose exec freepbx a2enmod rewrite
docker container stop freepbx-docker-freepbx-1
docker container start freepbx-docker-freepbx-1

# Install all Freepbx modules
docker compose exec freepbx fwconsole ma disablerepo commercial
docker compose exec freepbx fwconsole ma installall
docker compose exec freepbx fwconsole ma delete firewall
docker compose exec freepbx fwconsole reload
docker compose exec freepbx fwconsole restart
```