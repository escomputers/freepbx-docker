<?php

$ringlist = ringgroups_list();
$ctr = 0;
if (is_array($ringlist)) {
	outn("   Creating 'ringgroups' table if needed... ");
	// Create the new table first
	$sql = "CREATE TABLE IF NOT EXISTS `ringgroups` ( ";
	$sql .= "`grpnum` INT NOT NULL , ";
	$sql .= "`strategy` VARCHAR( 50 ) NOT NULL , ";
	$sql .= "`grptime` SMALLINT NOT NULL , ";
	$sql .= "`grppre` VARCHAR( 100 ) NULL , ";
	$sql .= "`grplist` VARCHAR( 255 ) NOT NULL , ";
	$sql .= "`annmsg` VARCHAR( 255 ) NULL , ";
	$sql .= "`postdest` VARCHAR( 255 ) NULL , ";
	$sql .= "PRIMARY KEY  (`grpnum`) ";
	$sql .= ") TYPE = MYISAM ";
	$result = $db->query($sql);
	if(DB::IsError($result)) {
		die($result->getDebugInfo());
	}
	out("OK");
	
	out("   Upgrading old Ring Group(s)");
	// upgrade each group
	foreach($ringlist as $item) {
		$ctr += 1;
		
		$grpnum = ltrim($item['0']);
		outn("     upgrading GRP-".$grpnum."... ");

		$exten = ringgroups_get($grpnum, $strategy,  $grptime, $grppre, $grplist);		
		$goto = upgrade_legacy_args_get($grpnum,2,'ext-group');
		
		// write new record
		$sql = "INSERT INTO ringgroups (grpnum, strategy, grptime, grppre, grplist, postdest) ";
		$sql .= "VALUES (";
		$sql .= $grpnum.", '".str_replace("'","''",$strategy)."', ".$grptime.", '".str_replace("'","''",$grppre)."', '".str_replace("'","''",$grplist)."', '".str_replace("'","''",$goto)."') ";
		$result = $db->query($sql);
		if(DB::IsError($result)) {
			die($result->getDebugInfo());
		}
		
		// update existing -- mark as CONVERTED
		$sql = "UPDATE extensions SET context = CONCAT('CONVERTED',context) WHERE context = 'ext-group' AND extension = '".$grpnum."'";
		$result = $db->query($sql);
		if(DB::IsError($result)) {
			die($result->getDebugInfo());
		}
		
		out("OK");
	}
	
	out("   Processed ".$ctr." Ring Group(s)");
} else { // might have the Ring Groups module already installed from a previous beta -- needs new table
	$sql = "SELECT COUNT(*) AS RES FROM modules WHERE modulename = 'ringgroups'";
	$result = $db->query($sql);
	if(DB::IsError($result)) {
		die($result->getDebugInfo());
	} else {
		$row = $result->fetchRow();
		if ($row[0] > 0) {
			outn("   Ring Groups module in use, creating 'ringgroups' table if needed... ");
			// Create the new table first
			$sql = "CREATE TABLE IF NOT EXISTS `ringgroups` ( ";
			$sql .= "`grpnum` INT NOT NULL , ";
			$sql .= "`strategy` VARCHAR( 50 ) NOT NULL , ";
			$sql .= "`grptime` SMALLINT NOT NULL , ";
			$sql .= "`grppre` VARCHAR( 100 ) NULL , ";
			$sql .= "`grplist` VARCHAR( 255 ) NOT NULL , ";
			$sql .= "`annmsg` VARCHAR( 255 ) NULL , ";
			$sql .= "`postdest` VARCHAR( 255 ) NULL , ";
			$sql .= "PRIMARY KEY  (`grpnum`) ";
			$sql .= ") TYPE = MYISAM ";
			$result = $db->query($sql);
			if(DB::IsError($result)) {
				die($result->getDebugInfo());
			}
			out("OK");
		}
	}
}

// ** HELPER FUNCTIONS
function ringgroups_list() {
	global $db;
	$sql = "SELECT DISTINCT extension FROM extensions WHERE context = 'ext-group' ORDER BY extension";
	$results = $db->getAll($sql);
	if(DB::IsError($results)) {
		$results = null;
	}
	foreach($results as $result){
		$extens[] = array($result[0]);
	}
	return $extens;
}

function ringgroups_get($grpexten, &$strategy, &$time, &$prefix, &$group) {
	global $db;
	$sql = "SELECT args FROM extensions WHERE context = 'ext-group' AND extension = '".$grpexten."' AND priority = '1'";
	$res = $db->getAll($sql);
	if(DB::IsError($res)) {
	   die($res->getMessage());
	}
	if (isset($res[0][0]) && preg_match("/^rg-group,(.*),(.*),(.*),(.*)$/", $res[0][0], $matches)) {
		$strategy = $matches[1];
		$time = $matches[2];
		$prefix = $matches[3];
		$group = $matches[4];
		return true;
	} 
	return false;
}

function upgrade_legacy_args_get($exten,$priority,$context) {
	global $db;
	$sql = "SELECT args FROM extensions WHERE extension = '".$exten."' AND priority = '".$priority."' AND context = '".$context."'";
	list($args) = $db->getRow($sql);
	return $args;
}

?>

