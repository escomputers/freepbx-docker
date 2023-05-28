<?php  /* $Id$ */

/* check for old prefix based routing */
outn("Upgrading Call Groups..");

// First update "|" to "-" usage for *-HEAD.
$sql = "update extensions set args = REPLACE(args,'|','-') where args LIKE 'GROUP=%' and args LIKE '%|%'";
$results = $db->query($sql);
if(DB::IsError($results)) {
	die($results->getMessage());
}

// get all call groups
$sql = "select extension, args from extensions where args LIKE 'GROUP=%';";
$results = $db->getAll($sql);
if(DB::IsError($results)) {
	die($results->getMessage());
}

out(count($results)." to check...");

if (count($results) > 0) {
	// yes, there are ring groups defined
	
	foreach ($results as $key => $value) {
		// replace * that are not at the beginning of an extension
		$new_extensions = preg_replace("/([0-9*#]+)\*([0-9#]+)/","$1$2#",$value[1]);
		// only replace if changed
		if($new_extensions != $value[1]) {
			out("Changing ". $value[1] ." to ". $new_extensions);
			$sql = sprintf("update extensions set args = '%s' WHERE extension = '%s' ", $new_extensions, $value['0']) ."AND args LIKE 'GROUP=%'";
			// debug("sql = ". $sql);
			$update_results = $db->query($sql);
			if(DB::IsError($update_results)) {
				die($update_results->getMessage());
			}
		}
	}
}

out("OK");

?>

