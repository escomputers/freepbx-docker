-- Create asterisk database
CREATE DATABASE asterisk;

CREATE DATABASE asteriskcdrdb;

CREATE USER 'freepbxuser' IDENTIFIED BY 'password';

GRANT ALL PRIVILEGES ON asterisk.* TO 'freepbxuser';
GRANT ALL PRIVILEGES ON asteriskcdrdb.* TO 'freepbxuser';

FLUSH PRIVILEGES;
