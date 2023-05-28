<?php
global $db;

if (!$db->getAll('SHOW COLUMNS FROM incoming WHERE FIELD = "id"')) {
	$sql = "ALTER TABLE `incoming` ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`id`)";
	$result = $db->query($sql);
}
