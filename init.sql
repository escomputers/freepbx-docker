-- Create asterisk database
CREATE DATABASE asterisk;

CREATE DATABASE asteriskcdrdb;

GRANT ALL PRIVILEGES ON *.* TO 'vault'@'172.18.0.5';

-- Grant privileges to freepbxuser for asteriskcdrdb database
--GRANT ALL PRIVILEGES ON `asteriskcdrdb`.* TO 'freepbxuser'@'%';

-- Grant privileges to freepbxuser for asterisk database
--GRANT ALL PRIVILEGES ON `asterisk`.* TO 'freepbxuser'@'%';

FLUSH PRIVILEGES;