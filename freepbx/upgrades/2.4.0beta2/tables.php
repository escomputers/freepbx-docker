<?php

outn("Converting module_xml data field to MEDIUMBLOB..");
$sql = "ALTER TABLE `module_xml` CHANGE `data` `data` MEDIUMBLOB NOT NULL";
$results = $db->query($sql);
if(DB::IsError($results)) {
	out("ERROR: failed to convert table ".$results->getMessage());
} else {
	out("OK");
}

?>
