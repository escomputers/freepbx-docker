# freepbx-docker

```
tar xvfz asterisk-16-current.tar.gz

docker compose up -d --build

docker compose exec freepbx /usr/src/freepbx/start_asterisk start

docker compose exec freepbx /usr/src/freepbx/install -n --dbhost=db
```