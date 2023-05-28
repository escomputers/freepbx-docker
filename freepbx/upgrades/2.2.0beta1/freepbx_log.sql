CREATE TABLE IF NOT EXISTS `freepbx_log` (
`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`time` DATETIME NOT NULL ,
`section` VARCHAR( 50 ) NULL ,
`level` ENUM( 'error', 'warning', 'debug', 'devel-debug' ) NOT NULL ,
`status` INT NOT NULL,
`message` TEXT NOT NULL ,
INDEX ( `time` , `level` )
) ENGINE = MYISAM ;

