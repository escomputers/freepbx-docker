<?php

//Increase KVstore key lengths to the max of 190
//https://issues.freepbx.org/browse/FREEPBX-14956
$sth = FreePBX::Database()->prepare("SHOW TABLES LIKE 'kvstore\_%'");
$sth->execute();
$tables = $sth->fetchAll(\PDO::FETCH_ASSOC);
$sql = [];
foreach($tables as $table) {
	$tablename = current($table);

	$sql[] = "ALTER TABLE `$tablename` DROP INDEX `uniqueindex`";
	$sql[] = "ALTER TABLE `$tablename` DROP INDEX `keyindex`";
	$sql[] = "ALTER TABLE `$tablename` DROP INDEX `idindex`";

	$sql[] = "ALTER TABLE `$tablename` ADD UNIQUE INDEX `uniqueindex` (`key`(190), `id`(190))";
	$sql[] = "ALTER TABLE `$tablename` ADD INDEX `keyindex` (`key`(190))";
	$sql[] = "ALTER TABLE `$tablename` ADD INDEX `idindex` (`id`(190))";
}
if(!empty($sql)) {
	FreePBX::Database()->exec(implode(";",$sql));
}
