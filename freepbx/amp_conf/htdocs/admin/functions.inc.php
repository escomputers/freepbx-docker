<?php /* $id$ */
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//

$dirname = isset($amp_conf['AMPWEBROOT']) ? $amp_conf['AMPWEBROOT'] . '/admin' : __DIR__;

//http://php.net/manual/en/function.phpversion.php
if (!defined('PHP_VERSION_ID')) {
	$version = explode('.', PHP_VERSION);
	define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
}

spl_autoload_register('fpbx_framework_autoloader');

//css minimizer class
if (!isset($bootstrap_settings['include_compress']) || $bootstrap_settings['include_compress']) {
	require_once($dirname . '/libraries/compress.class.php');
}

//collation services
require_once($dirname . '/libraries/core_collator.php');

//class that handles freepbx global setting. Dont autoload - we ALWAYS need this anyway
require_once($dirname . '/libraries/freepbx_conf.class.php');

//freepbx helpers for debugging/logging/comparing
if (!isset($bootstrap_settings['include_utility_functions']) || $bootstrap_settings['include_utility_functions']) {
	require_once($dirname . '/libraries/utility.functions.php');
}

//----------include function files----------

//module state manipulation functions
require_once($dirname . '/libraries/modulefunctions.class.php');
require_once($dirname . '/libraries/modulefunctions.legacy.php');

//dynamic registry of which exten's are in use and by whom
require_once($dirname . '/libraries/usage_registry.functions.php');

//PHP Restful Library
require_once($dirname . '/libraries/pest.functions.php');

//lightweight query functions
require_once($dirname . '/libraries/sql.functions.php');

//functions for view related activities
require_once($dirname . '/libraries/view.functions.php');

//functions for reding writing voicemail files
require_once($dirname . '/libraries/voicemail.function.php');

//legacy functions
require_once($dirname . '/libraries/legacy.functions.php');

//feature code related functions - not sure why these arent part of the class
require_once($dirname . '/libraries/featurecodes.functions.php');

//----------include helpers----------

//freepbx specific gui helpers
require_once($dirname . '/helpers/freepbx_helpers.php');

//general html helpers
require_once($dirname . '/helpers/html_helper.php');

//form generation
if (!defined('BASEPATH')){define('BASEPATH', '');}
if (!function_exists('get_instance')) {
	function get_instance(){return new ci_def();}
}
if (!class_exists('ci_def')) {
	class ci_def {function __construct(){$this->lang = new ci_lan_def(); $this->config = new ci_config(); $this->uri = new ci_uri_string();}}
}
if (!class_exists('ci_lan_def')) {
	class ci_lan_def {function load(){return false;} function line(){return false;}}
}
if (!class_exists('ci_config')) {
	class ci_config {function __construct(){return false;} function site_url($v){return $v;} function item(){return false;}}
}
if (!class_exists('ci_uri_string')) {
	class ci_uri_string {function  uri_string(){return false;}}
}
if (!function_exists('config_item')) {
	function config_item(){}
}
require_once($dirname . '/helpers/form_helper.php');

//freepbx autoloader
function fpbx_framework_autoloader($class) {
	if ($class === true) {
		// Deprecated - true USED to mean 'load all modules'
		return false;
	}

	// Handle guielements
	if (substr($class, 0, 3) == 'gui') {
		$class = 'component';
	}

	// FreePBX Module autoloader
	if (stripos($class, 'FreePBX\\modules\\') === 0) {
		// Trim the front
		$req = substr($class, 16);
		// If there's ANOTHER slash in the request, we want to try to autoload
		// the file.
		$modarr = explode('\\', $req);
		if (!isset($modarr[1])) {
			// TODO: Add *real* module autoloader here in FreePBX 15, replacing the BMO __get() autoloader
			return;
		}
		// This is a basic implementation of PSR4 under ..admin/modules/modulename/.. so that
		// a request for \FreePBX\modules\Ucp\Widgets\Ponies would look for a file
		// called ..admin/modules/ucp/Widgets/Ponies.php and then load it, if it exists.
		$moddir = \FreePBX::Config()->get('AMPWEBROOT')."/admin/modules/".strtolower(array_shift($modarr))."/";
		$filepath = $moddir.join("/", $modarr).".php";
		if (file_exists($filepath)) {
			include $filepath;
		}
		// Always return here, as there's nothing left to try.
		return;
	}

	$maps = array(
		// Static maps to files
		'CI_Email' => 'helpers/Email.php',
		'CI_Table' => 'helpers/Table.php',
		'ampuser' => 'libraries/ampuser.class.php',
		'CssMin' => 'libraries/cssmin.class.php',
		'component' => 'libraries/components.class.php',
		'featurecode' => 'libraries/featurecodes.class.php',
		'moduleHook' => 'libraries/moduleHook.class.php',
		'modulelist' => 'libraries/modulelist.class.php',
		'modgettext' => 'libraries/modgettext.class.php',
		'notifications' => 'libraries/notifications.class.php',
		'xml2Array' =>  'libraries/xml2Array.class.php',
		'fwmsg' => 'libraries/fwmsg.class.php',
		'FreePBX\\Database\\Migration' => 'libraries/BMO/Database/Migration.class.php',
		'FreePBX\\Database\\PDOStatement' => 'libraries/BMO/Database/PDOStatement.class.php',
		'FreePBX\\Job\\TaskInterface' => 'libraries/BMO/Job/Job.php',
		'PicoFeed\\Reader\\Reader' => 'libraries/Builtin/PicoFeed/Reader.php',
		'PicoFeed\\Client\\Client' => 'libraries/Builtin/PicoFeed/Client.php',
		// Namespaces
		'FreePBX\\Builtin\\' => 'libraries/Builtin',
		'FreePBX\\Console\\Command\\' => 'libraries/Console',
		'FreePBX\\Api\\Gql\\' => 'libraries/Api/Gql',
		'FreePBX\\Api\\Rest\\' => 'libraries/Api/Rest',
		'Media\\' => 'libraries/media/Media',
		'Media\\Driver\\' => 'libraries/media/Media/Driver',
		'Media\\Driver\\Drivers\\' => 'libraries/media/Media/Driver/Drivers',
		'mm\\Mime\\' => 'libraries/media/mm/Mime',
		'mm\\Mime\\Type\\' => 'libraries/media/mm/Mime/Type',
		'mm\\Mime\\Type\\Magic\\' => 'libraries/media/mm/Mime/Type/Magic',
		'mm\\Mime\\Type\\Magic\\Adapter\\' => 'libraries/media/mm/Mime/Type/Magic/Adapter',
		'mm\\Mime\\Type\\Glob\\' => 'libraries/media/mm/Mime/Type/Glob',
		'mm\\Mime\\Type\\Glob\\Adapter\\' => 'libraries/media/mm/Mime/Type/Glob/Adapter',

	);

	// Is it a direct mapping?
	if (isset($maps[$class])) {
		// Special handling for CI_Email and CI_Table
		//
		// TODO: ci_def and ci_lan_def are defined in bootstrap,
		//       do we need to define them here, too?
		//
		if ($class === "CI_Email" || $class === "CI_Table") {
			if (!function_exists('log_message')) {
				function log_message(){};
			}
			if (!function_exists('get_instance')) {
				function get_instance(){return new ci_def();}
			}
			if (!class_exists('ci_def', false)) {
				class ci_def {function __construct(){
					$this->lang = new ci_lan_def();}}
			}
			if (!class_exists('ci_lan_def', false)) {
				class ci_lan_def {function load(){return false;} function line(){return false;}}
			}
			if (!defined('BASEPATH')){
				define('BASEPATH', '');
			}
			if (!defined('FOPEN_READ')) {
				define('FOPEN_READ', 'rb');
			}
		}
		if (!file_exists(__DIR__."/".$maps[$class])) {
			throw new \Exception("Bug: Autoloader says $class is at ".$maps[$class].", but file doesn't exist");
		}
		// Debugging
		// print "Loaded class $class from ".$maps[$class]."\n";
		include __DIR__."/".$maps[$class];
		return;
	}

	// Check to see if this is a new autoloader request.  If it has a backslash in
	// the class, we're using the new autoloader. Note it doesn't support proper
	// PSR4 autoloading, you'll need to manually define classes in maps, above.
	if (strpos($class, '\\') !== false) {
		// Explode it to figure out the namespace and class name
		$sections = explode('\\', $class);
		$classname = array_pop($sections);
		$namespace = join('\\', $sections).'\\';

		if (isset($maps[$namespace])) {
			$file = __DIR__."/".$maps[$namespace]."/$classname.php";
			if (file_exists($file)) {
				// print "Loaded class $class from $file\n";
				include $file;
				return;
			}
			// Old .class.php?
			$oldfile = __DIR__."/".$maps[$namespace]."/$classname.class.php";
			if (!file_exists($oldfile)) {
				throw new \Exception("Bug: Explicitly know about $namespace, asked for $class, but $file (or $classname.class.php) doesn't exist");
			}
			// print "Loaded class $class from $file\n";
			include $oldfile;
		}
	}
}

/**
 * returns true if asterisk is running with chan_dahdi
 *
 * @return bool
 */
function ast_with_dahdi() {
	global $version;
	global $astman;
	global $amp_conf;
	global $chan_dahdi_loaded;

	// determine once, subsequent calls will use this
	global $ast_with_dahdi;

	if (isset($ast_with_dahdi)) {
		return $ast_with_dahdi;
	}
	if (!isset($version) || !$version || !is_string($version)) {
		$engine_info = engine_getinfo();
		$version = $engine_info['version'];
	}

	if ($amp_conf['ZAP2DAHDICOMPAT']) {
		$ast_with_dahdi = true;
		$chan_dahdi_loaded = true;
		return true;
	} elseif (version_compare($version,'1.4.21','ge')) {
		// we only had dahdi at this point so force the setting
		//
		$freepbx_conf =& freepbx_conf::create();
		if ($freepbx_conf->conf_setting_exists('ZAP2DAHDICOMPAT')) {
			$freepbx_conf->set_conf_values(array('ZAP2DAHDICOMPAT' => true), true, true);
			freepbx_log(FPBX_LOG_NOTICE, _("Auto set ZAP2DAHDICOMPAT to true because we are running a version of Asterisk greater than 1.4.21"));
		} else {
			freepbx_log(FPBX_LOG_ERROR, _("freepbx setting  ZAP2DAHDICOMPAT not found, somethng is corrupt in the conf database?"));
		}

		$ast_with_dahdi = true;
		$chan_dahdi_loaded = true;
		return true;
	}
	$ast_with_dahdi = false;
	return $ast_with_dahdi;
}

function engine_getinfo($force_read=false) {
	global $amp_conf;
	global $astman;
	static $engine_info;

	$gotinfo = false;

	if (!$force_read && isset($engine_info) && $engine_info != '') {
		return $engine_info;
	}

	switch ($amp_conf['AMPENGINE']) {
		case 'asterisk':
			if (isset($astman) && $astman->connected()) {
				//get version (1.4)
				$response = $astman->send_request('Command', array('Command'=>'core show version'));
				if (preg_match('/No such command/',$response['data'])) {
					// get version (1.2)
					$response = $astman->send_request('Command', array('Command'=>'show version'));
				}
				$verinfo = $response['data'];
			} else {
				// could not connect to asterisk manager, try console
				$verinfo = exec('asterisk -V');
			}

			if (preg_match('/Asterisk (\d+(\.\d+)*)(-?(\S*))/', $verinfo, $matches)) {
				$engine_info = array('engine'=>'asterisk', 'version' => $matches[1], 'additional' => $matches[4], 'raw' => $verinfo);
				$gotinfo = true;
			} elseif (preg_match('/Asterisk (?:SVN|GIT)-(\d+(\.\d+)*)(-?(\S*))/', $verinfo, $matches)) {
				$engine_info = array('engine'=>'asterisk', 'version' => $matches[1], 'additional' => $matches[4], 'raw' => $verinfo);
				$gotinfo = true;
			} elseif (preg_match('/Asterisk (?:SVN|GIT)-branch-(\d+(\.\d+)*)-r(-?(\S*))/', $verinfo, $matches)) {
				$engine_info = array('engine'=>'asterisk', 'version' => $matches[1].'.'.$matches[4], 'additional' => $matches[4], 'raw' => $verinfo);
				$gotinfo = true;
			} elseif (preg_match('/Asterisk (?:SVN|GIT)-trunk-r(-?(\S*))/', $verinfo, $matches)) {
				$engine_info = array('engine'=>'asterisk', 'version' => '1.8', 'additional' => $matches[1], 'raw' => $verinfo);
				$gotinfo = true;
			} elseif (preg_match('/Asterisk (?:SVN|GIT)-.+-(\d+(\.\d+)*)-r(-?(\S*))-(.+)/', $verinfo, $matches)) {
				$engine_info = array('engine'=>'asterisk', 'version' => $matches[1], 'additional' => $matches[3], 'raw' => $verinfo);
				$gotinfo = true;
			} elseif (preg_match('/Asterisk [B].(\d+(\.\d+)*)(-?(\S*))/', $verinfo, $matches)) {
				$engine_info = array('engine'=>'asterisk', 'version' => '1.2', 'additional' => $matches[3], 'raw' => $verinfo);
				$gotinfo = true;
			} elseif (preg_match('/Asterisk [C].(\d+(\.\d+)*)(-?(\S*))/', $verinfo, $matches)) {
				$engine_info = array('engine'=>'asterisk', 'version' => '1.4', 'additional' => $matches[3], 'raw' => $verinfo);
				$gotinfo = true;
												} elseif (preg_match('/Asterisk certified\/(\d+(\.\d+)*)(-?(\S*))/', $verinfo, $matches)) {
																$engine_info = array('engine'=>'asterisk', 'version' => $matches[1] . $matches[3], 'additional' => $matches[3], 'raw' => $verinfo);
				$gotinfo = true;
			}

			if (!$gotinfo) {
				$engine_info = array('engine'=>'ERROR-UNABLE-TO-PARSE', 'version'=>'0', 'additional' => '0', 'raw' => $verinfo);
			}
			if ($amp_conf['FORCED_ASTVERSION']) {
				$engine_info['engine'] = $amp_conf['AMPENGINE'];
				$engine_info['version'] = $amp_conf['FORCED_ASTVERSION'];
			}

			// Now we make sure the ASTVERSION freepbx_setting/amp_conf value is defined and set

			// this is not initialized in the installer because I think there are scenarios where
			// Asterisk may not be running and we may some day not need it to be so just deal
			// with it here.
			//
			$freepbx_conf = freepbx_conf::create();
			if (!$freepbx_conf->conf_setting_exists('ASTVERSION')) {
				// ASTVERSION
				//
				$set['value'] = $engine_info['version'];
				$set['defaultval'] = '';
				$set['options'] = '';
				$set['readonly'] = 1;
				$set['hidden'] = 1;
				$set['level'] = 10;
				$set['module'] = '';
				$set['category'] = 'Internal Use';
				$set['emptyok'] = 1;
				$set['name'] = 'Asterisk Version';
				$set['description'] = "Last Asterisk Version detected (or forced)";
				$set['type'] = CONF_TYPE_TEXT;
				$freepbx_conf->define_conf_setting('ASTVERSION',$set,true);
				unset($set);
				$amp_conf['ASTVERSION'] = $engine_info['version'];
			}

			if ($engine_info['version'] != $amp_conf['ASTVERSION']) {
				$freepbx_conf->set_conf_values(array('ASTVERSION' => $engine_info['version']), true, true);
			}

			return $engine_info;
		break;
	}
	$engine_info = array('engine'=>'ERROR-UNSUPPORTED-ENGINE-'.$amp_conf['AMPENGINE'], 'version'=>'0', 'additional' => '0', 'raw' => $verinfo);
	return $engine_info;
}

function do_reload($passthru=false) {
	return \FreePBX::Framework()->doReload($passthru);
}


// draw list for users and devices with paging
// $skip has been deprecated, used to be used to page-enate
function drawListMenu($results, $skip=null, $type=null, $dispnum, $extdisplay, $description=false) {

	$index = 0;
	echo "<ul>\n";
	if ($description !== false) {
		echo "\t<li><a ".($extdisplay=='' ? 'class="current"':'')." href=\"config.php?display=".$dispnum."\">"._("Add")." ".$description."</a></li>\n";
	}
	if (isset($results)) {
		foreach ($results as $key=>$result) {
			$index= $index + 1;
			echo "\t<li><a".($extdisplay==$result[0] ? ' class="current"':''). " href=\"config.php?display=".$dispnum."&extdisplay={$result[0]}\">{$result[1]} &lt;{$result[0]}&gt;</a></li>\n";
		}
	}
	echo "</ul>\n";
}

/* merge_ext_followme($dest) {
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
function merge_ext_followme($dest) {

	if (preg_match("/^\s*ext-findmefollow,(FM)?(\d+),(\d+)/",$dest,$matches) ||
			preg_match("/^\s*ext-local,(FM)?(\d+),(\d+)/",$dest,$matches) ) {
				// matches[2] => extn
				// matches[3] => priority
		return "from-did-direct,".$matches[2].",".$matches[3];
	} else {
		return $dest;
	}
}

function get_headers_assoc($url) {
	global $amp_conf;
	if ($amp_conf['MODULEADMINWGET']) {
		FreePBX::Curl()->setEnvVariables();
		exec("wget --spider --server-response -q ".escapeshellarg($url)." 2>&1", $wgetout, $exitstatus);
		$headers = array();
		if($exitstatus == 0 && !empty($wgetout)) {
			foreach($wgetout as $value) {
				$ar = explode(':', $value);
				$key = trim($ar[0]);
				if(isset($ar[1])) {
					$value = trim($ar[1]);
					$headers[strtolower($key)] = trim($value);
				}
			}
			if(!empty($headers)) {
				return $headers;
			}
		}
		return false;
	}

	$url_info=parse_url($url);
	$host = isset($url_info['host']) ? $url_info['host'] : '';
	if (isset($url_info['scheme']) && $url_info['scheme'] == 'https') {
		$port = isset($url_info['port']) ? $url_info['port'] : 443;
		@$fp=fsockopen('ssl://'.$host, $port, $errno, $errstr, 10);
	} else {
		$port = isset($url_info['port']) ? $url_info['port'] : 80;
		@$fp=fsockopen($host, $port, $errno, $errstr, 10);
	}
	if ($fp) {
		stream_set_timeout($fp, 10);
		$query = isset($url_info['query']) ? $url_info['query'] : '';
		$head = "HEAD ".@$url_info['path']."?".$query;
		$head .= " HTTP/1.0\r\nHost: ".$host."\r\n\r\n";
		fputs($fp, $head);
		while(!feof($fp)) {
			if($header=trim(fgets($fp, 1024))) {
				$sc_pos = strpos($header, ':');
				if ($sc_pos === false) {
					$headers['status'] = $header;
				} else {
					$label = substr( $header, 0, $sc_pos );
					$value = substr( $header, $sc_pos+1 );
					$headers[strtolower($label)] = trim($value);
				}
			}
		}
		return $headers;
	} else {
		return false;
	}
}


// Dragged this in from page.modules.php, so it can be used by install_amp.
function runModuleSQL($moddir,$type){
	trigger_error("runModuleSQL() is depreciated - please use _module_runscripts(), or preferably module_install() or module_enable() instead", E_USER_WARNING);
	_module_runscripts($moddir, $type);
}

//This function calls modulename_contexts()
//expects a returned array which minimally includes 'context' => the actual context to include
//can also define 'description' => the display for this context - if undefined will be set to 'context'
//'module' => the display for the section this should be listed under defaults to module display (can be used to group subsets within one module)
//'parent' => if including another context automatically includes this one, list the parent context
//'priority' => default sort order for includes range -50 to +50, 0 is default
//'enabled' => can be used to flag a context as disabled and it won't be included, but will not have its settings removed.
//'extension' => can be used to tag with an extension for checkRange($extension)
//'dept' => can be used to tag with a department for checkDept($dept)
//	this defaults to false for disabled modules.
function freepbx_get_contexts() {
  $modules = FreePBX::Modules()->getInfo(false, array(MODULE_STATUS_ENABLED, MODULE_STATUS_DISABLED, MODULE_STATUS_NEEDUPGRADE));

	$contexts = array();

	foreach ($modules as $modname => $mod) {
		$funct = strtolower($modname.'_contexts');
		if (function_exists($funct)) {
			// call the  modulename_contexts() function
			$contextArray = $funct();
			if (is_array($contextArray)) {
				foreach ($contextArray as $con) {
					if (isset($con['context'])) {
						if (!isset($con['description'])) {
							$con['description'] = $con['context'];
						}
						if (!isset($con['module'])) {
							$con['module'] = $mod['displayName'];
						}
						if (!isset($con['priority'])) {
							$con['priority'] = 0;
						}
						if (!isset($con['parent'])) {
							$con['parent'] = '';
						}
						if (!isset($con['extension'])) {
							$con['extension'] = null;
						}
						if (!isset($con['dept'])) {
							$con['dept'] = null;
						}
						if ($mod['status'] == MODULE_STATUS_ENABLED) {
							if (!isset($con['enabled'])) {
								$con['enabled'] = true;
							}
						} else {
							$con['enabled'] = false;
						}
						$contexts[ $con['context'] ] = $con;
					}
				}
			}
		}
	}
	return $contexts;
}

function getModuleInfo($module = false, $status = false, $forceload = false){
	$module_functions = module_functions::create();
	return $module_functions->getinfo($module, $status, $forceload);
}