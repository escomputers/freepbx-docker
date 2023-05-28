<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//
function customappsreg_destinations() {
	// return an associative array with destination and description
	$allDests = \FreePBX::Customappsreg()->getAllCustomDests();
	if (!$allDests) {
		return null;
	}

	$extens = array();
	foreach ($allDests as $row) {
		// If this has a return flag, we need to wrap it.
		if ($row['destret']) {
			$dest = "customdests,dest-".$row['destid'].",1";
			$extens[] = array('destination' => $dest, 'description' => $row['description'], 'category' => _("Custom Destinations"), 'id' => 'customdests');
		} else {
			$extens[] = array('destination' => $row['target'], 'description' => $row['description'], 'category' => _("Custom Destinations"), 'id' => 'customdests');
		}
	}
	return $extens;
}

/** the 'exten' is the same as the destination for this module
 */
function customappsreg_customdests_getdest($exten) {
	return array($exten);
}

/** If this is ours, we return it, otherwise we return false
 *  We use just use customappsreg and not the display because it
 *  is a per-module routine
 */
function customappsreg_getdestinfo($dest) {
	global $active_modules;

	$allDests = \FreePBX::Customappsreg()->getAllCustomDests();
	// Look for $dest in allDests. If we know about it, then return
	// the details. If we don't, return false.

	// Is it a new one?
	if (substr($dest, 0, 12) == "customdests,") {
		if (!preg_match("/customdests,dest-(\d+),1/", $dest, $matches)) {
			throw new \Exception("Unable to validate dest $dest");
		}
		if (!isset($allDests[$matches[1]])) {
			return false;
		} else {
			$cd = $allDests[$matches[1]];
			// Found it.
			return array('description' => sprintf(_("Custom Destination: %s"), $cd['description']),
		             'edit_url' => "config.php?display=customdests&destid=".$cd['destid']);
		}
	}
	foreach ($allDests as $cd) {
		if ($cd['target'] == $dest) {
			// Found it.
			$tmparr = array('description' => sprintf(_("Custom Destination: %s"), $cd['description']),
		             'edit_url' => "config.php?display=customdests&view=form&destid=".$cd['destid']);
			return $tmparr;
		}
	}

	// Didn't find it.
	return false;
}

function customappsreg_check_extensions($exten=true) {
	global $active_modules;

	$extenlist = array();
	if (is_array($exten) && empty($exten)) {
		return $extenlist;
	}
	$sql = "SELECT custom_exten, description FROM custom_extensions ";
	if (is_array($exten)) {
		$sql .= "WHERE custom_exten in ('".implode("','",$exten)."')";
	}
	$results = sql($sql,"getAll",DB_FETCHMODE_ASSOC);

	$type = isset($active_modules['customappsreg']['type'])?$active_modules['customappsreg']['type']:'tool';

	foreach ($results as $result) {
		$thisexten = $result['custom_exten'];
		$extenlist[$thisexten]['description'] = _("Custom Extension: ").$result['description'];
		$extenlist[$thisexten]['status'] = 'INUSE';
		$extenlist[$thisexten]['edit_url'] = 'config.php?display=customextens&extdisplay='.urlencode($thisexten);
	}
	return $extenlist;
}

function customappsreg_customextens_list() {
	global $db;
	$sql = "SELECT custom_exten, description, notes FROM custom_extensions ORDER BY custom_exten";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if(DB::IsError($results)) {
		die_freepbx($results->getMessage()."<br><br>Error selecting from custom_extensions");
	}
	return $results;
}

function customappsreg_customextens_get($custom_exten) {
	global $db;
	$sql = "SELECT custom_exten, description, notes FROM custom_extensions WHERE custom_exten = ".q($custom_exten);
	$row = $db->getRow($sql, DB_FETCHMODE_ASSOC);
	if(DB::IsError($row)) {
		die_freepbx($row->getMessage()."<br><br>Error selecting row from custom_extensions");
	}
	return $row;
}

function customappsreg_customextens_add($custom_exten, $description, $notes) {
	global $db;

	if ($custom_exten == '') {
		echo "<script>javascript:alert('"._('Invalid Extension, must not be blank')."')</script>";
		return false;
	}
	if (trim($description) == '') {
		echo "<script>javascript:alert('"._('Invalid description specified, must not be blank')."')</script>";
		return false;
	}

	$custom_exten = sql_formattext($custom_exten);
	$description  = sql_formattext($description);
	$notes        = sql_formattext($notes);
	$sql = "INSERT INTO custom_extensions (custom_exten, description, notes) VALUES ($custom_exten, $description, $notes)";
	$results = $db->query($sql);
	if (DB::IsError($results)) {
		if ($results->getCode() == DB_ERROR_ALREADY_EXISTS) {
			echo "<script>javascript:alert('"._('DUPLICATE Extension: This extension already in use')."')</script>";
			return false;
		} else {
			die_freepbx($results->getMessage()."<br><br>".$sql);
		}
	}
	return true;
}

function customappsreg_customextens_delete($custom_exten) {
    FreePBX::Modules()->deprecatedFunction();
    return FreePBX::Customappsreg()->deleteCustomExten($custom_exten);
}

function customappsreg_customextens_edit($old_custom_exten, $custom_exten,  $description, $notes) {
    FreePBX::Modules()->deprecatedFunction();
    return FreePBX::Customappsreg()->editCustomExten($old_custom_exten, $custom_exten, $description, $notes);
}
