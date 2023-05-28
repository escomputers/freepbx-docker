<?php

// Add default TONEZONE of 'us' if no TONEZONE exists already
$sql = "SELECT value FROM globals WHERE variable = 'TONEZONE' ";
$tz = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!is_array($tz)) { // does not exist already
        // Default to 'us'
        $sql = "INSERT INTO globals (variable, value) VALUES ('TONEZONE', 'us') ";
        $result = $db->query($sql);
        if(DB::IsError($result)) {
                die($result->getDebugInfo());
        }
}

// Add column 'channel' to incoming routing
$sql = "SELECT channel FROM incoming";
$check = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if(DB::IsError($check)) {
        $sql = "ALTER TABLE incoming ADD channel VARCHAR( 20 ) DEFAULT \"\"";
        $result = $db->query($sql);
        if(DB::IsError($result)) {
                die($result->getDebugInfo());
        }
}

// Add default ALLOW_SIP_ANON of 'no' if no ALLOW_SIP_ANON exists already
$sql = "SELECT value FROM globals WHERE variable = 'ALLOW_SIP_ANON' ";
$asa = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!is_array($asa)) { // does not exist already
        // Default to 'no'
        $sql = "INSERT INTO globals (variable, value) VALUES ('ALLOW_SIP_ANON', 'no') ";
        $result = $db->query($sql);
        if(DB::IsError($result)) {
                die($result->getDebugInfo());
        }
}

// Add default FAX_RX_FROM which allows users to set the 'from' address of fax emails
// I've used a gmail account as the default as we know the domain's going to exist.
$sql = "SELECT value FROM globals WHERE variable = 'FAX_RX_FROM' ";
$asa = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!is_array($asa)) { // does not exist already
        // Default to 'no'
        $sql = "INSERT INTO globals (variable, value) VALUES ('FAX_RX_FROM', 'freepbx@gmail.com') ";
        $result = $db->query($sql);
        if(DB::IsError($result)) {
                die($result->getDebugInfo());
        }
}

// MODIFIED (PL)
// 
// Check for the directdid and didalert fields in users table
// first search for table, you never know
// 
// Also add the TRUNK_OPTIONS variable to globals
//
// Add 'directdid' field
$sql = "SELECT directdid FROM users";
$usersexten_directdid = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if(DB::IsError($usersexten_directdid)) { // error, new field doesn't exist
	$sql = "ALTER TABLE users ADD directdid VARCHAR( 50 ) NULL";
	$result = $db->query($sql);
	if(DB::IsError($result)) 
		die($result->getDebugInfo());
}

// Add 'didalert' field
$sql = "SELECT didalert FROM users";
$usersexten_didalert = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if(DB::IsError($usersexten_didalert)) { // error, new field doesn't exist
	$sql = "ALTER TABLE users ADD didalert VARCHAR( 50 ) NULL";
	$result = $db->query($sql);
	if(DB::IsError($result)) 
		die($result->getDebugInfo());
}

// Add TRUNK_OPTIONS field
$sql = "SELECT value FROM globals WHERE variable = 'TRUNK_OPTIONS' ";
$asa = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!is_array($asa)) { // does not exist already
	// Default to 'r'
	$sql = "INSERT INTO globals (variable, value) VALUES ('TRUNK_OPTIONS', '') ";
	$result = $db->query($sql);
	if(DB::IsError($result)) 
                die($result->getDebugInfo());
}

?>
