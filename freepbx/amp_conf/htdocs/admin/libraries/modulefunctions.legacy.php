<?php
function _module_backtrace() {
    $trace = debug_backtrace();
    $function = $trace[1]['function'];
	$line = $trace[1]['line'];
	$file = $trace[1]['file'];
	freepbx_log(LOG_WARNING,'Depreciated Function '.$function.' detected in '.$file.' on line '.$line);
}

function module_delete($modulename, $force = false) {
	_module_backtrace();
	$modulef = module_functions::create();
	return $modulef->delete($modulename, $force);
}

function module_uninstall($modulename, $force = false) {
	_module_backtrace();
	$modulef = module_functions::create();
	return $modulef->uninstall($modulename, $force);
}

function module_disable($modulename, $force = false) {
	_module_backtrace();
	$modulef = module_functions::create();
	return $modulef->disable($modulename, $force);
}

function module_install($modulename, $force = false) {
	_module_backtrace();
	$modulef = module_functions::create();
	return $modulef->install($modulename, $force);
}

function module_enable($modulename, $force = false) {
	_module_backtrace();
	$modulef = module_functions::create();
	return $modulef->enable($modulename, $force);
}

function module_reversedepends($modulename) {
	_module_backtrace();
	$modulef = module_functions::create();
	return $modulef->reversedepends($modulename);
}

function module_checkdepends($modulename) {
	_module_backtrace();
	$modulef = module_functions::create();
	return $modulef->checkdepends($modulename);
}

function module_getinfo($module = false, $status = false, $forceload = false) {
	_module_backtrace();
	$modulef = module_functions::create();
	return $modulef->getinfo($module, $status, $forceload);
}

function modules_getversion($modname) {
	_module_backtrace();
	$modulef = module_functions::create();
	return $modulef->_getversion($modname); 
}

function _module_distro_id() {
	_module_backtrace();
	$modulef = module_functions::create();
	return $modulef->_distro_id(); 
}

function module_set_active_repos($repos) {
	_module_backtrace();
	$modulef = module_functions::create();
	foreach($repos as $repo => $status) {
		$modulef->set_active_repo($repo, $status);
	}
}
