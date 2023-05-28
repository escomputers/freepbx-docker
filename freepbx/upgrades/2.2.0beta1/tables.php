<?php


outn("Upgrading Inbound Routing to allow for RINGING signalling..");

$sql = "SELECT ringing FROM incoming";
$confs = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($confs)) { // no error... Already done
	out("Not Required");
} else {
	$sql = "ALTER TABLE incoming ADD ringing VARCHAR ( 20 ) NULL ";
	$results = $db->query($sql);
	if(DB::IsError($results)) {
	        die($results->getMessage());
	}
	out("Done");
}


outn("Upgrading Users/Extension Table to allow for Fax Receipt..");

$sql = "SELECT faxexten FROM users";
$confs = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($confs)) { // no error... Already done
	out("Not Required");
} else {
	$sql = "ALTER TABLE users ADD faxexten VARCHAR ( 20 ) NULL ";
	$results = $db->query($sql);
	if(DB::IsError($results)) {
	        die($results->getMessage());
	}
	out("Done");
}

$sql = "SELECT faxemail FROM users";
$confs = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($confs)) { // no error... Already done
	out("Not Required");
} else {
	$sql = "ALTER TABLE users ADD faxemail VARCHAR ( 50 ) NULL ";
	$results = $db->query($sql);
	if(DB::IsError($results)) {
	        die($results->getMessage());
	}
	out("Done");
}

$sql = "SELECT answer FROM users";
$confs = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($confs)) { // no error... Already done
	out("Not Required");
} else {
	$sql = "ALTER TABLE users ADD answer TINYINT ( 1 ) NULL ";
	$results = $db->query($sql);
	if(DB::IsError($results)) {
	        die($results->getMessage());
	}
	out("Done");
}

$sql = "SELECT wait FROM users";
$confs = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($confs)) { // no error... Already done
	out("Not Required");
} else {
	$sql = "ALTER TABLE users ADD wait INT ( 2 ) NULL ";
	$results = $db->query($sql);
	if(DB::IsError($results)) {
	        die($results->getMessage());
	}
	out("Done");
}

outn("Upgrading Users/Extension Table to allow for Privacy Manager..");

$sql = "SELECT privacyman FROM users";
$confs = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($confs)) { // no error... Already done
	out("Not Required");
} else {
	$sql = "ALTER TABLE users ADD privacyman TINYINT ( 1 ) NULL ";
	$results = $db->query($sql);
	if(DB::IsError($results)) {
	        die($results->getMessage());
	}
	out("Done");
}

outn("Checking for Global var OPERATOR_XTN..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='OPERATOR_XTN'");
if (!$nrows) {
	$db->query("insert into globals values ('OPERATOR_XTN', '')");
	out("Created");
} else {
	out("Already exists!");
}

outn("Checking for Global var VM_OPTS..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='VM_OPTS'");
if (!$nrows) {
	$db->query("insert into globals values ('VM_OPTS', '')");
	out("Created");
} else {
	out("Already exists!");
}

outn("Checking for Global var TIMEFORMAT..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='TIMEFORMAT'");
if (!$nrows) {
	$db->query("insert into globals values ('TIMEFORMAT', 'kM')");
	out("Created");
} else {
	out("Already exists!");
}
?>
