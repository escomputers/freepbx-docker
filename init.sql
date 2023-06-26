-- Create asterisk database
CREATE DATABASE asterisk;

CREATE DATABASE asteriskcdrdb;

GRANT ALL PRIVILEGES ON *.* TO 'vault'@'%' WITH GRANT OPTION;

FLUSH PRIVILEGES;