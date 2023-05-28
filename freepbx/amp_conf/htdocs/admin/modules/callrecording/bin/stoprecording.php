#!/usr/bin/env php
<?php
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2014 Schmooze Com Inc.

// This file exists because of https://issues.asterisk.org/jira/browse/ASTERISK-24527

//Bootstrap FreePBX
$bootstrap_settings['freepbx_auth'] = false;
include '/etc/freepbx.conf';

$mychan = $argv[1];
$recchan = gv($mychan, "RECORD_ID", rand());

if (!$recchan) {
	// Not recording?
	exit(-2);
}

// Validate that that channel exists.
$mixid = gv($recchan, "MIXMON_ID");
if (!$mixid) {
	// The channel that we think is recording doesn't exist.
	// I give up.
	exit(-1);
}

// Stop the monitor!
$astman->stopmixmonitor($recchan);

exit;

function gv($mychan, $var) {
	global $astman;
	$ret = $astman->GetVar($mychan, $var, rand());
	if($ret["Response"] != "Success"){
		return false;
	}
	return $ret["Value"];
}


