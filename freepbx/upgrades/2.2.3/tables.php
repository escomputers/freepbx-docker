<?php

outn("Alter tables sip, iax, zap to increase field length.. ");
$db->query("ALTER TABLE sip CHANGE data data VARCHAR( 255 ) NOT NULL");
$db->query("ALTER TABLE iax CHANGE data data VARCHAR( 255 ) NOT NULL");
$db->query("ALTER TABLE zap CHANGE data data VARCHAR( 255 ) NOT NULL");
out("Altered");

// Create module_xml - this was done in the code before, needed to be pulled out
//
outn("Creating module_xml table..");
$sql = "SELECT * FROM module_xml";
$confs = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($confs)) { // no error... Already done
	out("Not Required");
} else {
	$sql =	"CREATE TABLE module_xml (
	           id varchar(20) NOT NULL default 'xml',
	           time int(11) NOT NULL default '0',
	           data blob NOT NULL,
	         PRIMARY KEY  (id)
	         )";
	$results = $db->query($sql);
	if(DB::IsError($results)) {
	        die($results->getMessage());
	}
	out("Done");
}

// Add id filed to table so more key/values can be kept in it
//
outn("Adding id to module_xml table..");
$sql = "SELECT id FROM module_xml";
$confs = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($confs)) { // no error... Already done
	out("Not Required");
} else {
	$sql = "ALTER TABLE module_xml ADD id VARCHAR( 20 ) NOT NULL DEFAULT 'xml' FIRST";
	$results = $db->query($sql);
	if(DB::IsError($results)) {
	        die($results->getMessage());
	}
	$sql = "ALTER TABLE module_xml ADD PRIMARY KEY ( id )";
	$results = $db->query($sql);
	if(DB::IsError($results)) {
	        die($results->getMessage());
	}
	out("Altered");
}

?>
