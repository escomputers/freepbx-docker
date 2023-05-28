<?php

out("Updating existing voicemail destinations..");
$sql = 'SELECT * FROM extensions WHERE application = "Macro" AND args LIKE "vm,%"';
$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
if(DB::IsError($results)) {     
		die($results->getDebugInfo()); 
}

if(is_array($results)) {
	foreach($results as $result) {
		$vmbox = substr($result['args'],3);
		outn($vmbox.",");
		$sql = 'UPDATE extensions SET application = "Goto", args = "ext-local,${VM_PREFIX}'.$vmbox.',1" WHERE application = "Macro" AND args LIKE "vm,%"';
		$vmresults = $db->query($sql);
			if(DB::IsError($vmresults)) {     
					die($vmresults->getDebugInfo()); 
			}
	}
}
out("..OK");
?>