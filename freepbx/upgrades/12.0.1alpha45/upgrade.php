<?php
global $amp_conf;
global $db;
if (!$db->getAll('SHOW COLUMNS FROM modules WHERE FIELD = "signature"')) {
    $sql = "ALTER TABLE `modules` ADD COLUMN `signature` BLOB NULL AFTER `enabled`";
    $result = $db->query($sql);
}
