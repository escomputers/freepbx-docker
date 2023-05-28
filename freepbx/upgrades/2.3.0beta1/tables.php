<?php

/* merge_ext_followme_priv($dest) {
 * 
 * The purpose of this function is to take a destination
 * that was either a core extension OR a findmefollow-destination
 * and convert it so that they are merged and handled just like
 * direct-did routing
 *
 * Assuming an extension number of 222:
 *
 * The two formats that existed for findmefollow were:
 *
 * ext-findmefollow,222,1
 * ext-findmefollow,FM222,1
 *
 * The one format that existed for core was:
 *
 * ext-local,222,1
 *
 * In all those cases they should be converted to:
 *
 * from-did-direct,222,1
 *
 */
function merge_ext_followme_priv($dest) {

	if (preg_match("/^\s*ext-findmefollow,(FM)?(\d+),(\d+)/",$dest,$matches) ||
	    preg_match("/^\s*ext-local,(FM)?(\d+),(\d+)/",$dest,$matches) ) {
				// matches[2] => extn
				// matches[3] => priority
		return "from-did-direct,".$matches[2].",".$matches[3];
	} else {
		return $dest;
	}
}

outn("Upgrading Inbound Routing to allow for Music on Hold per DID..");

$sql = "SELECT mohclass FROM incoming";
$confs = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($confs)) { // no error... Already done
	out("Not Required");
} else {
	$sql = "ALTER TABLE incoming ADD mohclass VARCHAR ( 80 ) DEFAULT \"default\"";
	$results = $db->query($sql);
	if(DB::IsError($results)) {
	        die($results->getMessage());
	}
	out("Done");
}

outn("Upgrading Inbound Routing to provide a description field..");

$sql = "SELECT description FROM incoming";
$confs = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($confs)) { // no error... Already done
	out("Not Required");
} else {
	$sql = "ALTER TABLE incoming ADD description VARCHAR ( 80 ) NULL";
	$results = $db->query($sql);
	if(DB::IsError($results)) {
	        die($results->getMessage());
	}
	out("Done");
}

outn("Upgrading Inbound Routing to provide a CID Prefix field..");

$sql = "SELECT grppre FROM incoming";
$confs = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($confs)) { // no error... Already done
	out("Not Required");
} else {
	$sql = "ALTER TABLE incoming ADD grppre VARCHAR ( 80 ) NULL";
	$results = $db->query($sql);
	if(DB::IsError($results)) {
	        die($results->getMessage());
	}
	out("Done");
}

outn("Upgrading Users/Extension Table to allow for Music on Hold per Direct DID..");

$sql = "SELECT mohclass FROM users";
$confs = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($confs)) { // no error... Already done
	out("Not Required");
} else {
	$sql = "ALTER TABLE users ADD mohclass VARCHAR ( 80 ) DEFAULT \"default\"";
	$results = $db->query($sql);
	if(DB::IsError($results)) {
	        die($results->getMessage());
	}
	out("Done");
}

$sql = "SELECT sipname FROM users";
$confs = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($confs)) { // no error... Already done
	out("Not Required");
} else {
	$sql = "ALTER TABLE users ADD sipname VARCHAR ( 50 ) NULL ";
	$results = $db->query($sql);
	if(DB::IsError($results)) {
	        die($results->getMessage());
	}
	out("Done");
}

outn("Checking for Global var VMX_CONTEXT..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='VMX_CONTEXT'");
if (!$nrows) {
	$db->query("insert into globals values ('VMX_CONTEXT', 'from-internal')");
	out("Created");
} else {
	out("Already exists!");
}

outn("Checking for Global var VMX_PRI..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='VMX_PRI'");
if (!$nrows) {
	$db->query("insert into globals values ('VMX_PRI', '1')");
	out("Created");
} else {
	out("Already exists!");
}

outn("Checking for Global var VMX_TIMEDEST_CONTEXT..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='VMX_TIMEDEST_CONTEXT'");
if (!$nrows) {
	$db->query("insert into globals values ('VMX_TIMEDEST_CONTEXT', '')");
	out("Created");
} else {
	out("Already exists!");
}

outn("Checking for Global var VMX_TIMEDEST_EXT..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='VMX_TIMEDEST_EXT'");
if (!$nrows) {
	$db->query("insert into globals values ('VMX_TIMEDEST_EXT', 'dovm')");
	out("Created");
} else {
	out("Already exists!");
}

outn("Checking for Global var VMX_TIMEDEST_PRI..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='VMX_TIMEDEST_PRI'");
if (!$nrows) {
	$db->query("insert into globals values ('VMX_TIMEDEST_PRI', '1')");
	out("Created");
} else {
	out("Already exists!");
}

outn("Checking for Global var VMX_LOOPDEST_CONTEXT..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='VMX_LOOPDEST_CONTEXT'");
if (!$nrows) {
	$db->query("insert into globals values ('VMX_LOOPDEST_CONTEXT', '')");
	out("Created");
} else {
	out("Already exists!");
}

outn("Checking for Global var VMX_LOOPDEST_EXT..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='VMX_LOOPDEST_EXT'");
if (!$nrows) {
	$db->query("insert into globals values ('VMX_LOOPDEST_EXT', 'dovm')");
	out("Created");
} else {
	out("Already exists!");
}

outn("Checking for Global var VMX_LOOPDEST_PRI..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='VMX_LOOPDEST_PRI'");
if (!$nrows) {
	$db->query("insert into globals values ('VMX_LOOPDEST_PRI', '1')");
	out("Created");
} else {
	out("Already exists!");
}

outn("Checking for Global var VMX_OPTS_TIMEOUT..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='VMX_OPTS_TIMEOUT'");
if (!$nrows) {
	$db->query("insert into globals values ('VMX_OPTS_TIMEOUT', '')");
	out("Created");
} else {
	out("Already exists!");
}

outn("Checking for Global var VMX_OPTS_LOOP..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='VMX_OPTS_LOOP'");
if (!$nrows) {
	$db->query("insert into globals values ('VMX_OPTS_LOOP', '')");
	out("Created");
} else {
	out("Already exists!");
}

outn("Checking for Global var VMX_OPTS_DOVM..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='VMX_OPTS_DOVM'");
if (!$nrows) {
	$db->query("insert into globals values ('VMX_OPTS_DOVM', '')");
	out("Created");
} else {
	out("Already exists!");
}

outn("Checking for Global var VMX_TIMEOUT..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='VMX_TIMEOUT'");
if (!$nrows) {
	$db->query("insert into globals values ('VMX_TIMEOUT', '2')");
	out("Created");
} else {
	out("Already exists!");
}

outn("Checking for Global var VMX_REPEAT..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='VMX_REPEAT'");
if (!$nrows) {
	$db->query("insert into globals values ('VMX_REPEAT', '1')");
	out("Created");
} else {
	out("Already exists!");
}

outn("Checking for Global var VMX_LOOPS..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='VMX_LOOPS'");
if (!$nrows) {
	$db->query("insert into globals values ('VMX_LOOPS', '1')");
	out("Created");
} else {
	out("Already exists!");
}

outn("Checking for Global var TRANSFER_CONTEXT..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='TRANSFER_CONTEXT'");
if (!$nrows) {
	$db->query("insert into globals values ('TRANSFER_CONTEXT', 'from-internal-xfer')");
	out("Created");
} else {
	out("Already exists!");
}

outn("Alter tables incoming to increase field length.. ");
$db->query("ALTER TABLE incoming CHANGE alertinfo alertinfo VARCHAR( 255 ) NULL");
out("Altered");

outn("Merging findmefollow and core extension destinations for incoming routes..");

$results = array();
$sql = "SELECT cidnum, extension, destination FROM incoming";
$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
if (DB::IsError($results)) { // error - table must not be there
	out("Not Required");
} else {
	foreach ($results as $result) {
		$old_dest  = $result['destination'];
		$extension = $result['extension'];
		$cidnum    = $result['cidnum'];

		$new_dest = merge_ext_followme_priv(trim($old_dest));
		if ($new_dest != $old_dest) {
			$sql = "UPDATE incoming SET destination = '$new_dest' WHERE cidnum = '$cidnum' AND extension = '$extension' AND destination = '$old_dest'";
			$results = $db->query($sql);
			if(DB::IsError($results)) {
				die($results->getMessage());
			}
		}
	}
	out("Done");
}

?>
