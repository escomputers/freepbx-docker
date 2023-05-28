
-- 
-- Database changes required for 007 
-- 

-- 
-- Alter tables
-- 

ALTER TABLE `sip` CHANGE `id` `id` BIGINT( 11 ) DEFAULT "-1" NOT NULL;

ALTER TABLE `iax` CHANGE `id` `id` BIGINT( 11 ) DEFAULT "-1" NOT NULL;

ALTER TABLE `extensions` CHANGE `context` `context` VARCHAR( 45  ) DEFAULT 'default' NOT NULL;

-- 
-- Create new tables
-- 

CREATE TABLE IF NOT EXISTS `zap` (`id` bigint(11) NOT NULL default '-1',`keyword` varchar(20) NOT NULL default '',`data` varchar(150) NOT NULL default '',`flags` int(1) NOT NULL default '0',PRIMARY KEY (`id`,`keyword`)) TYPE=MyISAM;

CREATE TABLE IF NOT EXISTS `queues` (`id` bigint(11) NOT NULL default '-1',`keyword` varchar(20) NOT NULL default '',`data` varchar(150) NOT NULL default '',`flags` int(1) NOT NULL default '0',PRIMARY KEY  (`id`,`keyword`,`data`)) TYPE=MyISAM;

CREATE TABLE IF NOT EXISTS `ampusers` (`username` varchar(20) NOT NULL default '',`password` varchar(20) NOT NULL default '',`extension_low` varchar(20) NOT NULL default '',`extension_high` varchar(20) NOT NULL default '',`deptname` varchar(20) NOT NULL default '',`sections` varchar(255) NOT NULL default '',PRIMARY KEY  (`username`)) TYPE=MyISAM;

-- 
-- Add new global variable
-- 

DELETE FROM `globals` WHERE variable = 'DIRECTORY_OPTS';

INSERT INTO `globals` VALUES ('DIRECTORY_OPTS', '');
