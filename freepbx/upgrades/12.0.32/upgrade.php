<?php
global $db;
$sql = "ALTER TABLE module_xml CHANGE data data longblob";
$db->query($sql);
