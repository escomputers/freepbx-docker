<?php

$sql = "SELECT emergency_cid from devices";
$devices = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if(DB::IsError($devices)) {     
	$sql = "ALTER TABLE devices ADD emergency_cid VARCHAR( 100 ) NULL";
	$result = $db->query($sql); 
	if(DB::IsError($result)) {     
		die($result->getDebugInfo()); 
	}
}

?>
