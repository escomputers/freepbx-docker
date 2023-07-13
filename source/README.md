### Build from source
```bash
cd source

tar -xzvf asterisk-16-current.tar.gz && tar -xzvf odbc.tar.gz && tar -xzvf freepbx.tar.gz
docker build -t freepbx:custom .
```