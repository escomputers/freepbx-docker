<?php
if (! function_exists("out")) {
	function out($text) {
		echo $text."<br />";
	}
}

if (! function_exists("outn")) {
	function outn($text) {
		echo $text;
	}
}

global $db;

outn("Add field providedest to featurecodes..");
$sql = "SELECT providedest FROM featurecodes";
$confs = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($confs)) { // no error... Already done
  out("Not Required");
} else {
  $sql = "ALTER TABLE featurecodes ADD providedest TINYINT ( 4 ) NOT NULL DEFAULT '0'";
  $results = $db->query($sql);
  if(DB::IsError($results)) {
    die($results->getMessage());
  }
  out("Done");
}

// These are done in core's install.php, however, in upgrade scenarios using the installer,
// the installer will upgrade and then run retrieve_conf (as might a user accidently) which
// ends up calling the destination registry functions which makes everything crash since
// these fields they are expecting are not there yet as core has not yet been updated. So...
// we add them here which should not hurt anything, and for now we'll leave this in core
// as well for now.
//
out(_("Checking for schema changes for core's users table."));
$new_cols = array('noanswer_cid','busy_cid','chanunavail_cid');
foreach ($new_cols as $col) {
  outn(sprintf(_("Checking for %s field.."),$col));
  $sql = "SELECT $col FROM `users`";
  $check = $db->getRow($sql, DB_FETCHMODE_ASSOC);
  if(DB::IsError($check)) {
    // add new field
    $sql = "ALTER TABLE `users` ADD `$col` VARCHAR( 20 ) DEFAULT '';";
    $result = $db->query($sql);
    if(DB::IsError($result)) { die_freepbx($result->getDebugInfo()); }
    out(_("added"));
  } else {
    out(_("already exists"));
  }
}

$new_cols = array('noanswer_dest','busy_dest','chanunavail_dest');
foreach ($new_cols as $col) {
  outn(sprintf(_("Checking for %s field.."),$col));
  $sql = "SELECT $col FROM `users`";
  $check = $db->getRow($sql, DB_FETCHMODE_ASSOC);
  if(DB::IsError($check)) {
    // add new field
    $sql = "ALTER TABLE `users` ADD `$col` VARCHAR( 255 ) DEFAULT '';";
    $result = $db->query($sql);
    if(DB::IsError($result)) { die_freepbx($result->getDebugInfo()); }
    out(_("added"));
  } else {
    out(_("already exists"));
  }
}

