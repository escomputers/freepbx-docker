-- Create asterisk database
CREATE DATABASE asterisk;

-- Grant privileges to freepbxuser for asterisk database
GRANT ALL PRIVILEGES ON `asterisk`.* TO 'freepbxuser'@'%';

CREATE DATABASE asteriskcdrdb;

-- Grant privileges to freepbxuser for asteriskcdrdb database
GRANT ALL PRIVILEGES ON `asteriskcdrdb`.* TO 'freepbxuser'@'%';

FLUSH PRIVILEGES;