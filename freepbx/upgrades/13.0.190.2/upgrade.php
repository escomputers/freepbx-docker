<?php
global $amp_conf;
global $db;
$sql = "ALTER TABLE `featurecodes` CHANGE COLUMN `description` `description` VARCHAR(500) NOT NULL DEFAULT ''";
$result = $db->query($sql);
$sql = "ALTER TABLE `featurecodes` CHANGE COLUMN `helptext` `helptext` VARCHAR(500) NOT NULL DEFAULT ''";
$result = $db->query($sql);
