<?php

function framework_check_extension_usage($exten=true, $module_hash=false, $report_conflicts=true) {
	FreePBX::Modules()->deprecatedFunction();
	return FreePBX::Extensions()->checkUsage($exten, $report_conflicts);
}

function framework_get_extmap($json=false) {
	FreePBX::Modules()->deprecatedFunction();
	$res = FreePBX::Extensions()->getExtmap();
	if($json) {
		return json_encode($res);
	}
	return $res;
}

function framework_set_extmap() {
	FreePBX::Modules()->deprecatedFunction();
	return FreePBX::Extensions()->setExtmap();
}

function framework_check_destination_usage($dest=true, $module_hash=false) {
	FreePBX::Modules()->deprecatedFunction();
	return FreePBX::Destinations()->getAllInUseDestinations($dest);
}

function framework_display_extension_usage_alert($usage_arr=array(),$split=false,$alert=true) {
	FreePBX::Modules()->deprecatedFunction();
	return FreePBX::View()->displayExtensionUsageAlert($usage_arr,$split,$alert);
}

function framework_display_destination_usage($dest, $module_hash=false) {
	FreePBX::Modules()->deprecatedFunction();
	return FreePBX::Destinations()->destinationUsageArray($dest);
}

function framework_identify_destinations($dest, $module_hash=false) {
	FreePBX::Modules()->deprecatedFunction();
	return FreePBX::Destinations()->identifyDestinations($dest);
}

function framework_list_problem_destinations($module_hash=false, $ignore_custom=false) {
	FreePBX::Modules()->deprecatedFunction();
	return FreePBX::Destinations()->listProblemDestinations($ignore_custom);
}

function framework_change_destination($old_dest, $new_dest, $module_hash=false) {
	FreePBX::Modules()->deprecatedFunction();
	return FreePBX::Destinations()->changeDestination($old_dest, $new_dest);
}

function mod_func_iterator($func, &$opts = '') {
	FreePBX::Modules()->deprecatedFunction();
	return FreePBX::Modules()->functionIterator($func, $opts);
}

function framework_get_conflict_url_helper($account) {
	FreePBX::Modules()->deprecatedFunction();
	return FreePBX::View()->getConflictUrlHelper($account);
}

function framework_list_extension_conflicts($module_hash=false) {
	FreePBX::Modules()->deprecatedFunction();
	return FreePBX::Extensions()->listExtensionConflicts();
}
