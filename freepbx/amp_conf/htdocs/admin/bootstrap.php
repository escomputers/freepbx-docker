<?php

//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//

/*
 * Bootstrap Settings:
 *
 * bootstrap_settings['skip_astman']           - legacy $skip_astman, default false
 *
 * bootstrap_settings['astman_config']         - default null, config arguemnt when creating new Astman
 * bootstrap_settings['astman_options']        - default array(), config options creating new Astman
 *                                               e.g. array('cachemode' => true), see astman documentation
 * bootstrap_settings['astman_events']         - default 'off' used when connecting, Astman defaults to 'on'
 *
 * bootstrap_settings['freepbx_error_handler'] - false don't set it, true use default, named use what is passed
 *
 * bootstrap_settings['freepbx_auth']          - true (default) - authorize, false - bypass authentication
 *
 * bootstrap_settings['include_compress']      - true (default) - include compress class
 *
 * bootstrap_settings['include_utility_functions'] - true (default) - include utility functions.
 *
 * bootstrap_settings['include_framework_functions'] - true (default) - include the framework functions which are unavailable elsewhere
 *
 * bootstrap_settings['freepbx_auth']          - true (default) - authorize, false - bypass authentication
 *
 *
 * $restrict_mods: false means include all modules functions.inc.php, true skip all modules
 *                 array of hashes means each module where there is a hash
 *                 e.g. $restrict_mods = array('core' => true, 'dashboard' => true)
 *
 * Settings that are set by bootstrap to indicate the results of what was setup and not:
 *
 * $bootstrap_settings['framework_functions_included'] = true/false;
 * $bootstrap_settings['amportal_conf_initialized'] = true/false;
 * $bootstrap_settings['astman_connected'] = false/false;
 * $bootstrap_settings['function_modules_included'] = true/false true if one or more were included, false if all were skipped;
 */
if (isset($bootstrap_settings['returnimmediately'])) {
	return;
}

$mt = microtime();
// we should never re-run this file, something is wrong if we do.
//
//enable error reporting and start benchmarking
ini_set("default_charset","UTF-8");

function microtime_float() { list($usec,$sec) = explode(' ',microtime()); return ((float)$usec+(float)$sec); }
$benchmark_starttime = microtime_float();

global $amp_conf;
if (empty($amp_conf['AMPWEBROOT'])) {
	$amp_conf['AMPWEBROOT'] = dirname(dirname(__FILE__));
}
$dirname = $amp_conf['AMPWEBROOT'] . '/admin';

if (isset($bootstrap_settings['bootstrapped'])) {
  freepbx_log(FPBX_LOG_ERROR,"Bootstrap has already been called once, bad code somewhere");
  return;
} else {
  $bootstrap_settings['bootstrapped'] = true;
}

// Legacy setting methods
if (!isset($bootstrap_settings['skip_astman'])) {
  $bootstrap_settings['skip_astman'] = isset($skip_astman) ? $skip_astman : false;
}
$restrict_mods = isset($restrict_mods) ? $restrict_mods : false;

// Set defaults for unset settings
$bootstrap_defaults = array('skip_config' => null,
	'astman_config' => null,
	'astman_options' => array(
		'cachemode' => true
	),
	'astman_events' => 'off',
	'freepbx_error_handler' => true,
	'freepbx_auth' => true,
	'cdrdb' => false,
	'include_compress' => true,
	'include_utility_functions' => true,
	'include_framework_functions' =>true,
	'report_error_link' => true
);
foreach ($bootstrap_defaults as $key => $default_value) {
	if (!isset($bootstrap_settings[$key])) {
		$bootstrap_settings[$key] = $default_value;
	}
}
$GLOBALS['report_error_link'] = $bootstrap_settings['report_error_link'];

// include base functions
if(!class_exists('ComposerAutoloaderInitpbxframework')) {
	include $dirname .'/libraries/Composer/vendor/autoload.php';
}

$bootstrap_settings['framework_functions_included'] = false;
//load all freepbx functions
if ($bootstrap_settings['include_framework_functions']) {
	require_once($dirname . '/functions.inc.php');
	$bootstrap_settings['framework_functions_included'] = true;
}

//now that its been included, use our own error handler as it tends to be much more verbose.
if ($bootstrap_settings['freepbx_error_handler'] && empty($bootstrap_settings['fix_zend'])) {
	$error_handler = $bootstrap_settings['freepbx_error_handler'] === true ? '' : $bootstrap_settings['freepbx_error_handler'];
	if (function_exists($error_handler)) {
		set_error_handler($error_handler, E_ALL);
	} else {
		set_error_handler('freepbx_error_handler', E_ALL);
		$whoops = new \Whoops\Run;
		if(isset($bootstrap_settings['whoops_handler'])) {
			$class = '\\Whoops\\Handler\\'.$bootstrap_settings['whoops_handler'];
			$handler = new $class;
			if($bootstrap_settings['whoops_handler'] == 'JsonResponseHandler') {
				//$handler->addTraceToOutput(true);
			}
			$whoops->pushHandler($handler);
		} else {
			if(php_sapi_name() == 'cli') {
				$handler = new \Whoops\Handler\PlainTextHandler;
				$handler->addTraceToOutput(true);
				$whoops->pushHandler($handler);
			} else {
				$handler = new \Whoops\Handler\PrettyPageHandler;
				$handler->setPageTitle("There was an error");
				$handler->addResourcePath(__DIR__."/views/whoops");
				$whoops->pushHandler($handler);
			}
		}

		$whoops->register();
	}
}

// BMO: Initialize BMO as early as possible.

$bmo = dirname(__FILE__)."/libraries/BMO/FreePBX.class.php";
if (file_exists($bmo)) {
    include_once($bmo);
    $bmo = new FreePBX($amp_conf);
} else {
    throw new Exception("Unable to load BMO");
}
/** TODO Remove this when all modules are finally NOT referencing it like this **/
class Database extends FreePBX\Database {};

//Not available until PHP 5.4
if(!defined("ENT_HTML401")) {
	define("ENT_HTML401", 0);
}

// bootstrap.php should always be called from freepbx.conf so
// database conifguration already included, connect to database:
//
require_once(dirname(__FILE__)."/libraries/DB.class.php");
global $db;
$db = new DB();

// get settings
$freepbx_conf = $bmo->Freepbx_conf();

//set this before we run date functions
$timezone = $bmo->View->setTimezone();

// passing by reference, this means that the $amp_conf available to everyone is the same one as present
// within the class, which is probably a direction we want to go to use the class.
//
$bootstrap_settings['amportal_conf_initialized'] = false;
$amp_conf = $freepbx_conf->parse_amportal_conf("/etc/amportal.conf",$amp_conf);

if(!empty($amp_conf['PHP_CONSOLE'])) {
	$connector = PhpConsole\Connector::getInstance();
	if(!empty($amp_conf['PHP_CONSOLE_PASSWORD'])) {
		$connector->setPassword($amp_conf['PHP_CONSOLE_PASSWORD']);
	}
	$handler = PhpConsole\Handler::getInstance();
	$handler->start();
}

$amp_conf['PHP_ERROR_LEVEL'] = !empty($amp_conf['PHP_ERROR_LEVEL']) ? $amp_conf['PHP_ERROR_LEVEL'] : "ALL_NOSTRICTNOTICE";
switch($amp_conf['PHP_ERROR_LEVEL']) {
	case "ALL":
		error_reporting(E_ALL);
	break;
	case "ALL_NOSTRICT":
		error_reporting(E_ALL & ~E_STRICT);
	break;
	case "ALL_NOSTRICTNOTICEWARNING":
		error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE & ~E_USER_NOTICE & ~E_WARNING & ~E_USER_WARNING);
	break;
	case "ALL_NOSTRICTNOTICEWARNINGDEPRECIATED":
		error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE & ~E_USER_NOTICE & ~E_WARNING & ~E_USER_WARNING & ~E_DEPRECATED & ~E_USER_DEPRECATED);
	break;
	case "NONE":
		error_reporting(0);
		restore_error_handler();
		if(is_object($whoops) && is_a($whoops,"\Whoops\Run")) {
			$whoops->unregister();
		}
	break;
	case "ALL_NOSTRICTNOTICE":
	default;
		error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE & ~E_USER_NOTICE);
	break;
}

// set the language so local module languages take
$bmo->View->setLanguage();
$bmo->View->setTimezone();

// For performance tuning, or, for assistance in debugging a white screen,
// you can turn this on for a full trace of functions, memory use, and time
// taken.
//
// This MUST start after amp_conf is set or it's useless!!
// sorry Rob :-(
if(!empty($amp_conf['FPBXPERFLOGGING'])) {
	$bmo->Performance->On('dbug',$mt);
}

$asterisk_conf = $freepbx_conf->get_asterisk_conf();
$bootstrap_settings['amportal_conf_initialized'] = true;

//connect to cdrdb if requestes
if ($bootstrap_settings['cdrdb']) {
	$dsn = array(
		'phptype'  => $amp_conf['CDRDBTYPE'] ? $amp_conf['CDRDBTYPE'] : $amp_conf['AMPDBENGINE'],
		'hostspec' => $amp_conf['CDRDBHOST'] ? $amp_conf['CDRDBHOST'] : $amp_conf['AMPDBHOST'],
		'username' => $amp_conf['CDRDBUSER'] ? $amp_conf['CDRDBUSER'] : $amp_conf['AMPDBUSER'],
		'password' => $amp_conf['CDRDBPASS'] ? $amp_conf['CDRDBPASS'] : $amp_conf['AMPDBPASS'],
		'port'     => $amp_conf['CDRDBPORT'] ? $amp_conf['CDRDBPORT'] : '3306',
		//'socket'   => $amp_conf['CDRDBTYPE'] ? $amp_conf['CDRDBTYPE'] : 'mysql',
		'database' => $amp_conf['CDRDBNAME'] ? $amp_conf['CDRDBNAME'] : 'asteriskcdrdb',
	);
	$cdrdb = DB::connect($dsn);
}

$bootstrap_settings['astman_connected'] = false;
include $dirname . '/libraries/php-asmanager.php';
$astman = new \AGI_AsteriskManager($bootstrap_settings['astman_config'], $bootstrap_settings['astman_options']);
if (!$bootstrap_settings['skip_astman']) {
	// attempt to connect to asterisk manager proxy
	if (!$amp_conf["ASTMANAGERPROXYPORT"] || !$res = $astman->connect($amp_conf["ASTMANAGERHOST"] . ":" . $amp_conf["ASTMANAGERPROXYPORT"], $amp_conf["AMPMGRUSER"] , $amp_conf["AMPMGRPASS"], $bootstrap_settings['astman_events'])) {
		// attempt to connect directly to asterisk, if no proxy or if proxy failed
		if (!$res = $astman->connect($amp_conf["ASTMANAGERHOST"] . ":" . $amp_conf["ASTMANAGERPORT"], $amp_conf["AMPMGRUSER"] , $amp_conf["AMPMGRPASS"], $bootstrap_settings['astman_events'])) {
			// couldn't connect at all
			freepbx_log(FPBX_LOG_CRITICAL,"Connection attmempt to AMI failed");
		} else {
			$bootstrap_settings['astman_connected'] = true;
		}
	}
} else {
	$bootstrap_settings['astman_connected'] = true;
}

//Because BMO was moved upward we have to inject this lower
FreePBX::create()->astman = $astman;
$nt = notifications::create();

//include gui functions + auth if nesesarry
// If set to freepbx_auth but we are in a cli mode, then don't bother authenticating either way.
// TODO: is it ever possible through an apache or httplite configuration to run a web launched php script
//       as 'cli' ? Also, from a security perspective, should we just require this always be set to false
//       if we want to bypass authentication and not try to be automatic about it?
//
if (!$bootstrap_settings['freepbx_auth'] || (php_sapi_name() == 'cli')) {
	if (!defined('FREEPBX_IS_AUTH')) {
		define('FREEPBX_IS_AUTH', 'TRUE');
	}
} else {
	require($dirname . '/libraries/gui_auth.php');
	frameworkPasswordCheck();
	// Check and increase php memory_limit if needed and if allowed on the system
	//
	$current_memory_limit = rtrim(ini_get('memory_limit'),'M');
	$proper_memory_limit = '256';
	if ($current_memory_limit < $proper_memory_limit) {
		if (ini_set('memory_limit',$proper_memory_limit.'M') !== false) {
			$nt->add_notice('core', 'MEMLIMIT', _("Memory Limit Changed"), sprintf(_("Your memory_limit, %sM, is set too low and has been increased to %sM. You may want to change this in you php.ini config file"),$current_memory_limit,$proper_memory_limit));
		} else {
			$nt->add_warning('core', 'MEMERR', _("Low Memory Limit"), sprintf(_("Your memory_limit, %sM, is set too low and may cause problems. FreePBX is not able to change this on your system. You should increase this to %sM in you php.ini config file"),$current_memory_limit,$proper_memory_limit),'http://wiki.freepbx.org/x/lgK3AQ');
		}
	} else {
		$nt->delete('core', 'MEMLIMIT');
	}
}
if (!isset($no_auth) && !defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }//we should never need this, just another line of defence
bootstrap_include_hooks('pre_module_load', 'all_mods');
$bootstrap_settings['function_modules_included'] = false;

$restrict_mods_local = $restrict_mods;
//I'm pretty sure if this is == true then there is no need to even pull all
//the module info as we are going down a path such as an ajax path that this
//is just overhead. (We'll know soon enough if this is too restrcitive).
$zended = array();
$zendedbroken = array(); //to display in module_admin, or disable it here and now?
if ($restrict_mods_local !== true) {
	$isauth = !isset($no_auth);
	$modulef = module_functions::create();
	$active_modules = $modulef->getinfo(false, MODULE_STATUS_ENABLED);
	$modpath = $amp_conf['AMPWEBROOT'] . '/admin/modules/';
	if(is_array($active_modules)){
		foreach($active_modules as $key => $module) {
			//check if this module was was excluded
			$is_selected = is_array($restrict_mods_local)
				&& isset($restrict_mods_local[$key]);

			//get file path
			$file = $modpath . $key .'/functions.inc.php';
			$file_exists = is_file($file);

			//check authentication, skip this module if we dont have auth
			$needs_auth = isset($module['requires_auth'])
				&& $module['requires_auth'] == 'false'
				? false : true;
			if (!$isauth && $needs_auth) {
				continue;
			}

			// Zend appears to break class auto-loading. Therefore, if we
			//detect there is a module that requires Zend
			// we will include all the potential classes at this point.
			$needs_zend = isset($module['depends']['phpcomponent'])
				&& stristr($module['depends']['phpcomponent'], 'zend');

			//do we have a license file
			try {
				if ($needs_zend && class_exists('\Schmooze\Zend') && file_exists($file) && \Schmooze\Zend::fileIsLicensed($file) && !FreePBX::Modules()->loadLicensedFileCheck()) {
					$file_exists = false;
					$zendedbroken[] = $key;
				}
				//emergency mode
				if($needs_zend && $file_exists && !empty($bootstrap_settings['fix_zend'])) {
					$file_exists = false;
					$zended[$key] = $file;
				}
				//$file_exists = false;
			} catch(\Exception $e) {
				//Some fatal error happened
				freepbx_log(FPBX_LOG_WARNING,$e->getMessage());
				$file_exists = false;
				$zendedbroken[] = $key;
			}


			//actualy load module
			if ((!$restrict_mods_local || $is_selected) && $file_exists) {
				bootstrap_include_hooks('pre_module_load', $key);
				require_once($file);
				bootstrap_include_hooks('post_module_load', $key);
			}
			//create an array of module sections to display
			//stored as [items][$type][$category][$name] = $displayvalue
			if (isset($module['items']) && is_array($module['items'])) {
				//if asterisk isnt running, mark moduels that depend on
				//asterisk as disbaled
				foreach($module['items'] as $itemKey => $item) {
					$needs_edb = isset($item['needsenginedb'])
							&& strtolower($item['needsenginedb']) == 'yes';
					$needs_running = isset($item['needsenginerunning'])
							&& strtolower($item['needsenginerunning']) == 'yes';
					$needs_astman = $needs_edb || $needs_running;
					if (!$astman->connected() && $needs_astman) {
						$active_modules[$key]['items'][$itemKey]['disabled']
							= true;
					}
				}
			}
		}
	}
	bootstrap_include_hooks('post_module_load', 'all_mods');
	$bootstrap_settings['function_modules_included'] = true;
}
