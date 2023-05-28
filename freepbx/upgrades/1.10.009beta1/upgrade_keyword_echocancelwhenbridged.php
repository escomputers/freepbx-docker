<?php  /* $Id$ */
outn("Upgrading IAX table..");

$sql = "ALTER TABLE `iax` CHANGE `keyword` `keyword` VARCHAR( 30 ) NOT NULL";
$results = $db->query($sql);
if (DB::IsError($results)) {
	die($results->getMessage());
}
out("OK");

outn("Upgrading SIP table..");

$sql = "ALTER TABLE `sip` CHANGE `keyword` `keyword` VARCHAR( 30 ) NOT NULL";
$results = $db->query($sql);
if (DB::IsError($results)) {
        die($results->getMessage());
}
out("OK");

outn("Upgrading ZAP table..");

$sql = "ALTER TABLE `zap` CHANGE `keyword` `keyword` VARCHAR( 30 ) NOT NULL";
$results = $db->query($sql);
if (DB::IsError($results)) {
        die($results->getMessage());
}
out("OK");

outn("Fixing 'echocancelwhenbridge' to 'echocancelwhenbridged'..");

$sql = "UPDATE zap SET keyword = 'echocancelwhenbridged' WHERE keyword = 'echocancelwhenbridge'";
$results = $db->query($sql);
if (DB::IsError($results)) {
        die($results->getMessage());
}
out("OK");
?>
