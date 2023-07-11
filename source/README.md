### Build from source
```bash
cd source
tar -xvf asterisk.tar && tar -xvf freepbx.tar && tar -xvf odbc.tar
docker build -t freepbx:custom .
```