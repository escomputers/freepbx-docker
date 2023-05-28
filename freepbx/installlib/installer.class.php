<?php
namespace FreePBX\Install;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Installer {
	function __construct(InputInterface $input = NULL, OutputInterface $output = NULL) {
		$this->input = $input;
		$this->output = $output;
	}

	private function log($message) {
		if ($this->output) {
			$this->output->writeln($message);
		} else {
			out($message);
		}
	}

	function amportal_conf_read($filename) {
		$file = file($filename);

		foreach ($file as $line) {
			if (preg_match("/^\s*([a-zA-Z0-9_]+)\s*=\s*(.*)\s*([;#].*)?/", $line, $matches)) {
				$conf[$matches[1]] = $matches[2];
			}
		}

		return $conf;
	}

	function amportal_conf_write($filename) {
		global $amp_conf;

		foreach ($amp_conf as $key => $value) {
			$file[] = $key . "=" . $value;
		}

		file_put_contents($filename, implode("\n", $file) . "\n");
	}

	function asterisk_conf_read($filename) {
		if(!class_exists('FreePBX\LoadConfig')) {
			include dirname(__DIR__)."/amp_conf/htdocs/admin/libraries/BMO/LoadConfig.class.php";
		}
		$conf = new \FreePBX\LoadConfig("Fake FreePBX Object",basename($filename),dirname($filename));

		return $conf->ProcessedConfig;
	}

	function asterisk_conf_write($filename, $conf) {
		foreach($conf as $section => $data) {
			$file[] = "[".$section."]";
			foreach ($data as $key => $value) {
				$file[] = $key . "=" . $value;
			}
		}

		file_put_contents($filename, implode("\n", $file) . "\n");
	}

	function get_version() {
		global $db;

		$version = $db->getOne("SELECT value FROM admin WHERE variable = 'version'");

		return $version;
	}
	function set_version($version) {
		global $db;

		$db->query("UPDATE admin SET value = '".$version."' WHERE variable = 'version'");
	}

	function install_upgrades($version) {
		// **** Read upgrades/ directory
		$this->log("Checking for upgrades..");

		// read versions list from upgrades/
		$versions = array();
		$dir = opendir(UPGRADE_DIR);
		while ($file = readdir($dir)) {
			if (($file[0] != ".") && is_dir(UPGRADE_DIR . "/" . $file)) {
				$versions[] = $file;
			}
		}
		closedir($dir);

		// callback to use php's version_compare() to sort
		usort($versions, "version_compare_freepbx");

		// find versions that are higher than the current version
		$starting_version = false;
		foreach ($versions as $check_version) {
			if (version_compare_freepbx($check_version, $version) > 0) { // if check_version < version
				$starting_version = $check_version;
				break;
			}
		}

		// run all upgrades from the list of higher versions
		if ($starting_version) {
			$pos = array_search($starting_version, $versions);
			$upgrades = array_slice($versions, $pos); // grab the list of versions, starting at $starting_version
			$this->log(count($upgrades)." found");
			foreach ($upgrades as $version) {
				$this->log("Upgrading to ".$version."..");
				$this->install_upgrade($version);
				$this->set_version($version);
				$this->log("Upgrading to ".$version."..OK");
			}
		} else {
			$this->log("No further upgrades necessary");
		}
	}

	/** Install a particular version
	 */
	private function install_upgrade($version) {
		global $db;
		global $amp_conf;

		$db_engine = $amp_conf["AMPDBENGINE"];

		if (is_dir(UPGRADE_DIR . "/" . $version)) {
			// sql scripts first
			$dir = opendir(UPGRADE_DIR . "/" . $version);
			while ($file = readdir($dir)) {
				if (($file[0] != ".") && is_file(UPGRADE_DIR . "/" . $version . "/" . $file)) {
					if ((strtolower(substr($file,-7)) == ".sqlite") && ($db_engine == "sqlite")) {
						$this->install_sql_file(UPGRADE_DIR . "/" . $version . "/" . $file);
					} elseif ((strtolower(substr($file,-4)) == ".sql") &&
							(($db_engine  == "mysql")  ||  ($db_engine  == "pgsql") || ($db_engine == "sqlite3"))) {
						$this->install_sql_file(UPGRADE_DIR . "/" . $version . "/" . $file);
					}
				}
			}

			// now non sql scripts
			$dir = opendir(UPGRADE_DIR . "/" . $version);
			while ($file = readdir($dir)) {
				if (($file[0] != ".") && is_file(UPGRADE_DIR . "/" . $version . "/" . $file)) {
					if ((strtolower(substr($file,-4)) == ".sql") || (strtolower(substr($file,-7)) == ".sqlite")) {
						// sql scripts were dealt with first
					} else if (strtolower(substr($file,-4)) == ".php") {
						$this->log("-> Running PHP script " . UPGRADE_DIR . "/" . $version . "/" . $file);
						include_once(UPGRADE_DIR . "/" . $version . "/" . $file);

					} else if (is_executable(UPGRADE_DIR . "/" . $version . "/" . $file)) {
						$this->log("-> Executing " . UPGRADE_DIR . "/" . $version . "/" . $file);
						exec(UPGRADE_DIR . "/" . $version . "/" . $file);
					} else {
						error("-> Don't know what to do with " . UPGRADE_DIR . "/" . $version . "/" . $file);
					}
				}
			}
		}
	}

	function install_sql_file($file) {
		global $db;

		if (!file_exists($file)) {
			return false;
		}

		// Temporary variable, used to store current query
		$templine = '';
		// Read in entire file
		$lines = file($file);
		// Loop through each line
		foreach ($lines as $line) {
			// Skip it if it's a comment
			if (substr($line, 0, 2) == '--' || $line == '') {
				continue;
			}

			// Add this line to the current segment
			$templine .= $line;
			// If it has a semicolon at the end, it's the end of the query
			if (substr(trim($line), -1, 1) == ';') {
				// Perform the query
				$sth = $db->query($templine);
				// Reset temp variable to empty
				$templine = '';
			}
		}
	}

	//
	// TODO: find a good way to extract the required localization strings for the tools to pickup
	//
	// freepbx_settings_init()
	// this is where we initialize all the freepbx_settings (amportal.conf). This will be run with install_amp and every
	// time we run the framework installer, so new settings can be added here that are framework wide. It may make sense to
	// break this out separately but for now we'll keep it here since this is already part of the infrastructure that is
	// used by both install_amp and the framework install/upgrade script.
	//

	function freepbx_settings_init($commit_to_db = false) {
	global $amp_conf;

	if (!class_exists('freepbx_conf')) {
		include_once ($amp_conf['AMPWEBROOT'].'/admin/libraries/freepbx_conf.class.php');
	}

	$freepbx_conf = \freepbx_conf::create();


	$category = 'Advanced Settings Details';

	$settings[$category]['AS_DISPLAY_HIDDEN_SETTINGS'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Display Hidden Settings',
	'description' => 'This will display settings that are normally hidden by the system. These settings are often internally used settings that are not of interest to most users.',
	'readonly' => 1,
	'hidden' => 1,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['AS_DISPLAY_READONLY_SETTINGS'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Display Readonly Settings',
	'description' => 'This will display settings that are readonly. These settings are often internally used settings that are not of interest to most users. Since they are readonly they can only be viewed.',
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['AS_OVERRIDE_READONLY'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Override Readonly Settings',
	'description' => 'Setting this to true will allow you to override un-hidden readonly setting to change them. Settings that are readonly may be extremely volatile and have a high chance of breaking your system if you change them. Take extreme caution when electing to make such changes.',
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['AS_DISPLAY_FRIENDLY_NAME'] = array(
	'value' => true,
	'options' => '',
	'name' => 'Display Friendly Name',
	'description' => 'Normally the friendly names will be displayed on this page and the internal freepbx_conf configuration names are shown in the tooltip. If you prefer to view the configuration variables, and the friendly name in the tooltip, set this to false..',
	'type' => CONF_TYPE_BOOL,
	);

	$category = 'Cron JOBS';
	$settings[$category]['FWJOBS_LOGS'] = array(
		'description' => _('Enable / disable framework cron jobs logs. If enabled logs will save to ASTLOGDIR/fwjobs.log file'),
		'name' => _('Enable fwconsloe cron jobs execution logs'),
		'value' => false,
		'readonly' => 0,
		'hidden' => 0,
		'level' => 1,
		'emptyok' => 0,
		'sortorder' => 30,
		'type' => CONF_TYPE_BOOL
	);

	$settings[$category]['JOBSRANDOMSLEEP'] = array(
		'defaultval' =>0,
		'options' => array(0,30),
		'readonly' => 0,
		'hidden' => 0,
		'level' => 0,
		'name' => _('FreePBX JOBs Maximum Random sleep'),
		'description' => _('0 for disabling the Random Sleep And Max sleep you can set is 30'),
		'type' => CONF_TYPE_INT
	);


	$category = 'System Setup';

	$settings[$category]['AMPSYSLOGLEVEL'] = array(
	'value' => 'FILE',
	'options' => 'FILE, LOG_EMERG, LOG_ALERT, LOG_CRIT, LOG_ERR, LOG_WARNING, LOG_NOTICE, LOG_INFO, LOG_DEBUG' . ((isset($amp_conf['AMPSYSLOGLEVEL']) && (strtoupper($amp_conf['AMPSYSLOGLEVEL']) == 'SQL' || strtoupper($amp_conf['AMPSYSLOGLEVEL']) == 'LOG_SQL')) ? ', LOG_SQL, SQL' : ''),
	'name' => 'FreePBX Log Routing',
	'description' => "Determine where to send log information if the log is enabled ('Disable FreePBX Log' (AMPDISABLELOG) false. There are two places to route the log messages. 'FILE' will send all log messages to the defined 'FreePBX Log File' (FPBX_LOG_FILE). All the other settings will route the log messages to your System Logging subsystem (syslog) using the specified log level. Syslog can be configured to route different levels to different locations. See 'syslog' documentation (man syslog) on your system for more details.",
	'sortorder' => -190,
	'type' => CONF_TYPE_SELECT,
	);

	$settings[$category]['AMPDISABLELOG'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Disable FreePBX Log',
	'description' => 'Whether or not to invoke the FreePBX log facility.',
	'sortorder' => -180,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['AMPSHOWUPDATESONSSH'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Show Updates on SSH login',
	'description' => 'Show system and module updates information on SSH login.',
	'sortorder' => -170,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['AUTOMODULEUPDATESANDRELOAD'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Reload Config After Automatic Module Updates',
	'description' => 'Do fwconsole reload After automatic module update',
	'sortorder' => -170,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['LOG_OUT_MESSAGES'] = array(
	'value' => true,
	'options' => '',
	'name' => 'Log Verbose Messages',
	'description' => 'FreePBX has many verbose and useful messages displayed to users during module installation, system installations, loading configurations and other places. In order to accumulate these messages in the log files as well as the on screen display, set this to true.',
	'sortorder' => -170,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['LOG_NOTIFICATIONS'] = array(
	'value' => true,
	'options' => '',
	'name' => 'Send Dashboard Notifications to Log',
	'description' => 'When enabled all notification updates to the Dashboard notification panel will also be logged into the specified log file when enabled.',
	'sortorder' => -160,
	'type' => CONF_TYPE_BOOL,
	);

	//Prefixed with 'M' because we are using the moments lib
	$settings[$category]['MDATETIMEFORMAT'] = array(
	'value' => 'llll',
	'options' => '',
	'name' => 'Date and Time Format',
	'description' => 'The format dates and times should display in. The default of "llll" is locale aware. For more formats please see: http://momentjs.com/docs/#/displaying/format/',
	'sortorder' => -150,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['MDATEFORMAT'] = array(
	'value' => 'l',
	'options' => '',
	'name' => 'Date Format',
	'description' => 'The format dates should display in. The default of "l" is locale aware. For more formats please see: http://momentjs.com/docs/#/displaying/format/',
	'sortorder' => -150,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['MTIMEFORMAT'] = array(
	'value' => 'LT',
	'options' => '',
	'name' => 'Time Format',
	'description' => 'The format times should display in. The default of "LT" is local aware. For more formats please see: http://momentjs.com/docs/#/displaying/format/',
	'sortorder' => -150,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['FPBX_LOG_FILE'] = array(
	'value' => $amp_conf['ASTLOGDIR'] . '/freepbx.log',
	'options' => '',
	'name' => 'FreePBX Log File',
	'description' => 'Full path and name of the FreePBX Log File used in conjunction with the Syslog Level (AMPSYSLOGLEVEL) being set to FILE, not used otherwise. Initial installs may have some early logging sent to /tmp/freepbx_pre_install.log when it is first bootstrapping the installer.',
	'sortorder' => -150,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['PHP_ERROR_HANDLER_OUTPUT'] = array(
	'value' => 'freepbxlog',
	'options' => array('dbug','freepbxlog','off'),
	'name' => 'PHP Error Log Output',
	'description' => "Where to send PHP errors, warnings and notices by the FreePBX PHP error handler. Set to 'dbug', they will go to the Debug File regardless of whether dbug Loggin is disabled or not. Set to 'freepbxlog' will send them to the FreePBX Log. Set to 'off' and they will be ignored.",
	'sortorder' => -140,
	'type' => CONF_TYPE_SELECT,
	);

	$settings[$category]['PHP_ERROR_LEVEL'] = array(
	'value' => 'ALL_NOSTRICTNOTICE',
	'options' => array('ALL','ALL_NOSTRICT','ALL_NOSTRICTNOTICE','ALL_NOSTRICTNOTICEWARNING','ALL_NOSTRICTNOTICEWARNINGDEPRECIATED', 'NONE'),
	'name' => 'PHP Error Level',
	'description' => "Sets which PHP errors are reported",
	'sortorder' => -139,
	'type' => CONF_TYPE_SELECT,
	);

	$settings[$category]['PHP_CONSOLE'] = array(
	'value' => false,
	'options' => '',
	'name' => 'PHP Console',
	'description' => "When enabled will turn on PHP Console for error debugging https://github.com/barbushin/php-console",
	'sortorder' => -140,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['PHP_CONSOLE_PASSWORD'] = array(
	'value' => 'batteryhorsestaple',
	'options' => '',
	'name' => 'PHP Console Password',
	'description' => "Used when PHP Console is enabled for error debugging https://github.com/barbushin/php-console",
	'sortorder' => -140,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['AGGRESSIVE_DUPLICATE_CHECK'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Aggresively Check for Duplicate Extensions',
	'description' => "When set to true FreePBX will update its extension map every page load. This is used to check for duplicate extension numbers in the client side javascript validation. Normally the extension map is only created when Apply Configuration Settings is pressed and retrieve_conf is run.",
	'sortorder' => -137,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['AMPEXTENSIONS'] = array(
	'value' => 'extensions',
	'options' => 'extensions,deviceanduser',
	'name' => 'User & Devices Mode',
	'description' => 'Sets the extension behavior in FreePBX.If set to <b>extensions</b>, Devices and Users are administered together as a unified Extension, and appear on a single page. If set to <b>deviceanduser</b>, Devices and Users will be administered separately. Devices (e.g. each individual line on a SIP phone) and Users (e.g. <b>101</b>) will be configured independent of each other, allowing association of one User to many Devices, or allowing Users to login and logout of Devices.',
	'sortorder' => -135,
	'type' => CONF_TYPE_SELECT,
	);

	$settings[$category]['AUTHTYPE'] = array(
	'value' => 'database',
	'options' => 'database,none,webserver,usermanager',
	'name' => 'Authorization Type',
	'description' => 'Authentication type to use for web admin. If type set to <b>database</b>, the primary AMP admin credentials will be the AMPDBUSER/AMPDBPASS above. When using database you can create users that are restricted to only certain module pages. When set to none, you should make sure you have provided security at the apache level. When set to webserver, FreePBX will expect authentication to happen at the apache level, but will take the user credentials and apply any restrictions as if it were in database mode.',
	'level' => 3,
	'readonly' => 1,
	'sortorder' => -130,
	'type' => CONF_TYPE_SELECT,
	);

	$settings[$category]['AMP_ACCESS_DB_CREDS'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Allow Login With DB Credentials',
	'description' => "When Set to True, admin access to the FreePBX GUI will be allowed using the FreePBX configured AMPDBUSER and AMPDBPASS credentials. This only applies when Authorization Type is 'database' mode.",
	'sortorder' => -126,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['FORCED_ASTVERSION'] = array(
	'value' => '',
	'options' => '',
	'name' => 'Force Asterisk Version',
	'description' => 'Normally FreePBX gets the current Asterisk version directly from Asterisk. This is required to generate proper dialplan for a given version. When using some custom Asterisk builds, the version may not be properly parsed and improper dialplan generated. Setting this to an equivalent Asterisk version will override what is read from Asterisk. This SHOULD be left blank unless you know what you are doing.',
	'emptyok' => 1,
	'readonly' => 1,
	'sortorder' => -100,
	'type' => CONF_TYPE_TEXT,
	'level' => 4,
	);

	$settings[$category]['AMPENGINE'] = array(
	'value' => 'asterisk',
	'options' => 'asterisk',
	'name' => 'Telephony Engine',
	'description' => 'The telephony backend engine being used, asterisk is the only option currently.',
	'level' => 3,
	'readonly' => 1,
	'type' => CONF_TYPE_SELECT,
	);

	$settings[$category]['AMPVMUMASK'] = array(
	'value' => '007',
	'options' => '',
	'name' => 'Asterisk VMU Mask',
	'description' => 'Defaults to 077 allowing only the asterisk user to have any permission on VM files. If set to something like 007, it would allow the group to have permissions. This can be used if setting apache to a different user then asterisk, so that the apache user can have access to read/write/delete the voicemail files. If changed, some of the voicemail directory structures may have to be manually changed.',
	'type' => CONF_TYPE_TEXT,
	'level' => 4,
	);

	$settings[$category]['AMPWEBADDRESS'] = array(
	'value' => '',
	'options' => '',
	'name' => 'FreePBX Web Address',
	'description' => 'This is the address of your Web Server. It is mostly obsolete and derived when not supplied and will be phased out, but there are still some areas expecting a variable to be set and if you are using it this will migrate your value.',
	'emptyok' => 1,
	'type' => CONF_TYPE_TEXT,
	'level' => 4,
	);

	$settings[$category]['AMPASTERISKUSER'] = array(
	'value' => 'asterisk',
	'options' => '',
	'name' => 'System Asterisk User',
	'description' => 'The user Asterisk should be running as, used by freepbx_engine. Most systems should not change this.',
	'type' => CONF_TYPE_TEXT,
	'level' => 4,
	'readonly' => 1,
	);

	$settings[$category]['AMPASTERISKGROUP'] = array(
	'value' => 'asterisk',
	'options' => '',
	'name' => 'System Asterisk Group',
	'description' => 'The user group Asterisk should be running as, used by freepbx_engine. Most systems should not change this.',
	'type' => CONF_TYPE_TEXT,
	'level' => 4,
	'readonly' => 1,
	);

	$settings[$category]['AMPASTERISKWEBUSER'] = array(
	'value' => 'asterisk',
	'options' => '',
	'name' => 'System Web User',
	'description' => 'The user your httpd should be running as, used by freepbx_engine. Most systems should not change this.',
	'type' => CONF_TYPE_TEXT,
	'level' => 4,
	'readonly' => 1,
	);

	$settings[$category]['AMPASTERISKWEBGROUP'] = array(
	'value' => 'asterisk',
	'options' => '',
	'name' => 'System Web Group',
	'description' => 'The user group your httpd should be running as, used by freepbx_engine. Most systems should not change this.',
	'type' => CONF_TYPE_TEXT,
	'level' => 4,
	'readonly' => 1,
	);

	$settings[$category]['AMPDEVUSER'] = array(
	'value' => 'asterisk',
	'options' => '',
	'name' => 'System Device User',
	'description' => 'The user that various device directories should be set to, used by freepbx_engine. Examples include /dev/zap, /dev/dahdi, /dev/misdn, /dev/mISDN and /dev/dsp. Most systems should not change this.',
	'type' => CONF_TYPE_TEXT,
	'level' => 4,
	'readonly' => 1,
	);

	$settings[$category]['AMPDEVGROUP'] = array(
	'value' => 'asterisk',
	'options' => '',
	'name' => 'System Device Group',
	'description' => 'The user group that various device directories should be set to, used by freepbx_engine. Examples include /dev/zap, /dev/dahdi, /dev/misdn, /dev/mISDN and /dev/dsp. Most systems should not change this.',
	'readonly' => 1,
	'type' => CONF_TYPE_TEXT,
	'level' => 4,
	);

	$settings[$category]['BROWSER_STATS'] = array(
	'value' => true,
	'options' => '',
	'name' => 'Browser Stats',
	'description' => 'Setting this to true will allow the development team to use google analytics to anonymously analyze browser information to help make better development decisions. Setting to false will stop the PBX from using google analytics directly, but this option will not control web contents displayed in iframes.',
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['USE_GOOGLE_CDN_JS'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Use Google Distribution Network for js Downloads',
	'description' => 'Setting this to true will fetch system javascript libraries such as jQuery and jQuery-ui from ajax.googleapis.com. This can be advantageous if accessing remote or multiple different FreePBX systems since the libraries are only cached once in your browser. If external internet connections are problematic, setting this true could result in slow systems. FreePBX will always fallback to the locally available libraries if the CDN is not available.',
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['SIGNATURECHECK'] = array(
	'value' => true,
	'options' => '',
	'name' => 'Enable Module Signature Checking',
	'description' => 'Checks to make sure modules and their files are validly signed. Will display a notice on any module page that is not correctly verified.',
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['AMPTRACKENABLE'] = array(
	'value' => true,
	'options' => '',
	'name' => 'Enable Module Tracks',
	'description' => 'This enables the setting of module tracks (sub repositories of modules). Whereas a user could select a beta release track of a module or keep it on standard. Disabling this will force all modules into the stable track and disallow users to change the tracks',
	'level' => 2,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['REMOTEUNLOCK'] = array(
	'value' => false,
	'options' => '',
	'hidden' => 1,
	'name' => 'Enable Remote Unlocking',
	'description' => 'Enabling this option will allow a remote user to automatically authenticate as an admin via use of a one-time key generated by "amportal a genunlockkey"',
	'level' => 2,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['UIDEFAULTLANG'] = array(
	'value' => 'en_US',
	'options' => '',
	'readonly' => 0,
	'hidden' => 0,
	'name' => 'Default language',
	'description' => 'The default language used in the webUI',
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['FREEPBX_SYSTEM_IDENT'] = array(
	'value' => 'VoIP Server',
	'options' => '',
	'readonly' => 0,
	'hidden' => 0,
	'name' => 'System Identity',
	'description' => 'This name will be used to help identify this machine in emails or alerts',
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['FREEPBX_SYSTEM_IDENT_REM_DASHBOARD_HELP'] = array(
	'value' => 'no', 
	'options' => '',
	'readonly' => 0,
	'hidden' => 1,
	'name' => 'Remove dashboard help text to change System Identity',
	'description' => 'This will be used to decide if we need to show help text to change system identity in dashboard or not ',
	'type' => CONF_TYPE_TEXT,
	);


	$category = 'Dialplan and Operational';

	$settings[$category]['RFC7462'] = array(
		'value' => true,
		'options' => '',
		'name' => 'Enforce RFC7462',
		'description' => 'Whether to enforce RFC7462 for Alert-Info. With this enabled all Alert Infos will be prefixed with "<lt&>http://127.0.0.1<gt&>;info=" if it was not previously defined. This is to be in accordance with RFC7462. Disabling this enforcement will remove the prefix entirely',
		'type' => CONF_TYPE_BOOL,
		'level' => 2,
	);

	$settings[$category]['AMPBADNUMBER'] = array(
	'value' => true,
	'options' => '',
	'name' => 'Use bad-number Context',
	'description' => 'Generate the bad-number context which traps any bogus number or feature code and plays a message to the effect. If you use the Early Dial feature on some Grandstream phones, you will want to set this to false.',
	'type' => CONF_TYPE_BOOL,
	'level' => 2,
	);

	$settings[$category]['CWINUSEBUSY'] = array(
	'value' => true,
	'options' => '',
	'name' => 'Occupied Lines CW Busy',
	'description' => 'For extensions that have CW enabled, report unanswered CW calls as <b>busy</b> (resulting in busy voicemail greeting). If set to no, unanswered CW calls simply report as <b>no-answer</b>.',
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['ZAP2DAHDICOMPAT'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Convert ZAP Settings to DAHDi',
	'description' => 'If set to true, FreePBX will check if you have chan_dahdi installed. If so, it will automatically use all your ZAP configuration settings (devices and trunks) and silently convert them, under the covers, to DAHDi so no changes are needed. The GUI will continue to refer to these as ZAP but it will use the proper DAHDi channels. This will also keep Zap Channel DIDs working.',
	'readonly' => 1,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['DYNAMICHINTS'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Dynamically Generate Hints',
	'description' => 'If true, Core will not statically generate hints, but instead make a call to the AMPBIN php script, and generate_hints.php through an Asterisk #exec call. This requires asterisk.conf to be configured with <b>execincludes=yes</b> set in the [options] section.',
	'readonly' => 1,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['ENABLECW'] = array(
	'value' => true,
	'options' => '',
	'name' => 'CW Enabled by Default',
	'description' => 'Enable call waiting by default when an extension is created (Default is yes). Set to <b>no</b> to if you do not want phones to be commissioned with call waiting already enabled. The user would then be required to dial the CW feature code (*70 default) to enable their phone. Most installations should leave this alone. It allows multi-line phones to receive multiple calls on their line appearances.',
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['FCBEEPONLY'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Feature Codes Beep Only',
	'description' => 'When set to true, a beep is played instead of confirmation message when activating/de-activating: CallForward, CallWaiting, DayNight, DoNotDisturb and FindMeFollow.',
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['USEGOOGLEDNSFORENUM'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Use Google DNS for Enum',
	'description' => 'Setting this flag will generate the required global variable so that enumlookup.agi will use Google DNS 8.8.8.8 when performing an ENUM lookup. Not all DNS deals with NAPTR record, but Google does. There is a drawback to this as Google tracks every lookup. If you are not comfortable with this, do not enable this setting. Please read Google FAQ about this: <b>http://code.google.com/speed/public-dns/faq.html#privacy</b>.',
	'type' => CONF_TYPE_BOOL,
	'level' => 2,
	);

	$settings[$category]['DISABLECUSTOMCONTEXTS'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Disable -custom Context Includes',
	'description' => 'Normally FreePBX auto-generates a custom context that may be usable for adding custom dialplan to modify the normal behavior of FreePBX. It takes a good understanding of how Asterisk processes these includes to use this and in many of the cases, there is no useful application. All includes will result in a WARNING in the Asterisk log if there is no context found to include though it results in no errors. If you know that you want the includes, you can set this to true. If you comment it out FreePBX will revert to legacy behavior and include the contexts.',
	'type' => CONF_TYPE_BOOL,
	'level' => 2,
	);

	$settings[$category]['NOOPTRACE'] = array(
	'value' => '0',
	'options' => '0,1,2,3,4,5,6,7,8,9,10',
	'name' => 'NoOp Traces in Dialplan',
	'description' => 'Some modules will generate lots of NoOp() commands proceeded by a [TRACE](trace_level) that can be used during development or while trying to trace call flows. These NoOp() commands serve no other purpose so if you do not want to see excessive NoOp()s in your dialplan you can set this to 0. The higher the number the more detailed level of trace NoOp()s will be generated',
	'type' => CONF_TYPE_SELECT,
	);

	$settings[$category]['DIVERSIONHEADER'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Generate Diversion Headers',
	'description' => 'If this value is set to true, then calls going out your outbound routes that originate from outside your PBX and were subsequently forwarded through a call forward, ring group, follow-me or other means, will have a SIP diversion header added to the call with the original incoming DID assuming there is a DID available. This is useful with some carriers that may require this under certain circumstances.',
	'type' => CONF_TYPE_BOOL,
	);

	$opts = array();
	for ($i=-1; $i<=120; $i++) {
	$opts[] = $i;
	}
	$settings[$category]['CFRINGTIMERDEFAULT'] = array(
	'value' => '0',
	'options' => $opts,
	'name' => 'Call Forward Ringtimer Default',
	'description' => 'This is the default time in seconds to try and connect a call that has been call forwarded by the server side CF, CFU and CFB options. (If your phones use client side CF such as SIP redirects, this will not have any affect) If set to the default of 0, it will use the standard ring timer. If set to -1 it will ring the forwarded number with no limit which is consistent with the behavior of some existing PBX systems. If set to any other value, it will ring for that duration before diverting the call to the users voicemail if they have one. This can be overridden for each extension.',
	'type' => CONF_TYPE_SELECT,
	);
	unset($opts);

	$settings[$category]['DEFAULT_INTERNAL_AUTO_ANSWER'] = array(
	'value' => 'disabled',
	'options' => array('disabled','intercom'),
	'name' => 'Internal Auto Answer Default',
	'description' => "Default setting for new extensions. When set to Intercom, calls to new extensions/users from other internal users act as if they were intercom calls meaning they will be auto-answered if the endpoint supports this feature and the system is configured to operate in this mode. All the normal white list and black list settings will be honored if they are set. External calls will still ring as normal, as will certain other circumstances such as blind transfers and when a Follow Me is configured and enabled. If Disabled, the phone rings as a normal phone.",
	'type' => CONF_TYPE_SELECT,
	);

	$settings[$category]['FORCE_INTERNAL_AUTO_ANSWER_ALL'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Force All Internal Auto Answer',
	'description' => "Force all extensions to operate in the Internal Auto Answer mode regardless of their individual settings. See 'Internal Auto Answer Default' for more information.",
	'type' => CONF_TYPE_BOOL,
	);

	$opts = array();
	for ($i=0; $i<=120; $i++) {
	$opts[] = $i;
	}
	$settings[$category]['CONCURRENCYLIMITDEFAULT'] = array(
	'value' => '3',
	'options' => $opts,
	'name' => 'Extension Concurrency Limit',
	'description' => 'Default maximum number of outbound simultaneous calls that an extension can make. This is also very useful as a Security Protection against a system that has been compromised. It will limit the number of simultaneous calls that can be made on the compromised extension. This default is used when an extension is created. A default of 0 means no limit.',
	'type' => CONF_TYPE_SELECT,
	);
	unset($opts);

	$settings[$category]['BLOCK_OUTBOUND_TRUNK_CNAM'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Block CNAM on External Trunks',
	'description' => "Some carriers will reject a call if a CallerID Name (CNAM) is presented. This occurs in several areas when configuring CID on the PBX using the format of 'CNAM' <CNUM>. To remove the CNAM part of CID on all external trunks, set this value to true. This WILL NOT remove CNAM when a trunk is called from an Intra-Company route. This can be done on each individual trunk in addition to globally if there are trunks where it is desirable to keep CNAM information, though most carriers ignore CNAM.",
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['ASTSTOPTIMEOUT'] = array(
	'value' => '120',
	'options' => array(0,5,10,30,60,120,300,600,1800,3600,7200,10800),
	'name' => 'Waiting Period to Stop Asterisk',
	'description' => "When Asterisk is stopped or restarted with the 'amportal stop/restart' commands, it does a graceful stop waiting for active channels to hangup. This sets the maximum time in seconds to wait prior to force stopping Asterisk",
	'type' => CONF_TYPE_SELECT,
	);

	$settings[$category]['ASTSTOPPOLLINT'] = array(
	'value' => '2',
	'options' => array(1,2,3,5,10),
	'name' => 'Polling Interval for Stopping Asterisk',
	'description' => "When Asterisk is stopped or restarted with the 'amportal stop/restart' commands, it does a graceful stop waiting for active channels to hangup. This sets the polling interval to check if Asterisk is shutdown and update the countdown timer.",
	'type' => CONF_TYPE_SELECT,
	);

	$settings[$category]['CID_PREPEND_REPLACE'] = array(
	'value' => true,
	'options' => '',
	'name' => 'Only Use Last CID Prepend',
	'description' => "Some modules allow the CNAM to be prepended. If a previous prepend was done, the default behavior is to remove the previous prepend and only use the most recent one. Setting this to false will turn that off allowing all prepends to be 'stacked' in front of one another.",
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['DITECH_VQA_INBOUND'] = array(
	'value' => '7',
	'options' => array(0,1,2,3,4,5,6,7),
	'name' => 'Ditech VQA Inbound Setting',
	'description' => "If Ditech's VQA, Voice Quality application is installed, this setting will be used for all inbound calls. For more information 'core show application VQA' at the Asterisk CLI will show the different settings.",
	'type' => CONF_TYPE_SELECT,
	);

	$settings[$category]['DITECH_VQA_OUTBOUND'] = array(
	'value' => '7',
	'options' => array(0,1,2,3,4,5,6,7),
	'name' => 'Ditech VQA Outbound Setting',
	'description' => "If Ditech's VQA, Voice Quality application is installed, this setting will be used for all outbound calls. For more information 'core show application VQA' at the Asterisk CLI will show the different settings.",
	'type' => CONF_TYPE_SELECT,
	);

	$settings[$category]['ASTCONFAPP'] = array(
	'value' => 'app_confbridge',
	'options' => array('app_meetme', 'app_confbridge'),
	'name' => 'Conference Room App',
	'description' => _("The asterisk application to use for conferencing. As the app_meetme Asterisk application is deprecated and scheduled for removal in Asterisk 21, it's recommended that it be changed to app_confbridge."),
	'type' => CONF_TYPE_SELECT,
	);

	$settings[$category]['TRUNK_RING_TIMER'] = array(
	'value' => '300',
	'name' => 'Trunk Dial Timeout',
	'description' => 'How many seconds to try a call on your trunks before giving up. This should normally be a very long time and is usually only changed if you have some sort of problematic trunks. This is the Asterisk Dial Command timeout parameter.',
	'readonly' => 1,
	'type' => CONF_TYPE_INT,
	'options' => array(0,86400),
	'level' => 2,
	);

	$settings[$category]['REC_POLICY'] = array(
	'value' => 'caller',
	'options' => array('caller', 'callee'),
	'name' => 'Call Recording Policy',
	'description' => 'Call Recording Policy used to resove the winner in a conflict between two extensions when one wants a call recorded and the other does not, if both their priorities are also the same.',
	'type' => CONF_TYPE_SELECT,
	);

	$settings[$category]['TRANSFER_CONTEXT'] = array(
	'value' => 'from-internal-xfer',
	'options' => '',
	'readonly' => 1,
	'hidden' => 1,
	'level' => 9,
	'emptyok' => 1,
	'name' => 'Asterisk TRANSFER_CONTEXT Variable',
	'description' => "This is the Asterisk Channel Variable TRANSFER_CONTEXT. In general it should NOT be changed unless you really know what you are doing. It is used to do create slightly different 'views' when a call is being transfered. An example is hiding the paging groups so a call isn't accidentally transfered into a page.",
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['ASTSIPDRIVER'] = array(
	'value' => 'chan_pjsip',
	'options' => array('both', 'chan_sip', 'chan_pjsip'),
	'level' => 2,
	'name' => 'SIP Channel Driver',
	'description' => 'The Asterisk channel driver to use for SIP. The default is both for Asterisk 12 and higher. For Asterisk 11 and lower the default will be chan_sip. If only one is compiled into asterisk, the PBX will attempt to auto detect and change the value to what is compiled. The chan_pjsip channel driver does not work on Asterisk 11 or lower.',
	'type' => CONF_TYPE_SELECT,
	);


	$category = 'Directory Layout';

	$settings[$category]['AMPBIN'] = array(
	'value' => '/var/lib/asterisk/bin',
	'options' => '',
	'name' => 'FreePBX bin Dir',
	'description' => 'Location of the FreePBX command line scripts.',
	'readonly' => 1,
	'type' => CONF_TYPE_DIR,
	'level' => 4,
	);

	$settings[$category]['AMPSBIN'] = array(
	'value' => '/usr/sbin',
	'options' => '',
	'name' => 'FreePBX sbin Dir',
	'description' => 'Where (root) command line scripts are located.',
	'readonly' => 1,
	'type' => CONF_TYPE_DIR,
	'level' => 4,
	);

	$settings[$category]['AMPWEBROOT'] = array(
	'value' => '/var/www/html',
	'options' => '',
	'name' => 'FreePBX Web Root Dir',
	'description' => 'The path to Apache webroot (leave off trailing slash).',
	'readonly' => 1,
	'type' => CONF_TYPE_DIR,
	'level' => 4,
	);

	$settings[$category]['ASTAGIDIR'] = array(
	'value' => '/var/lib/asterisk/agi-bin',
	'options' => '',
	'name' => 'Asterisk AGI Dir',
	'description' => 'This is the default directory for Asterisks agi files.',
	'readonly' => 1,
	'type' => CONF_TYPE_DIR,
	'level' => 4,
	);

	$settings[$category]['ASTETCDIR'] = array(
	'value' => '/etc/asterisk',
	'options' => '',
	'name' => 'Asterisk etc Dir',
	'description' => 'This is the default directory for Asterisks configuration files.',
	'readonly' => 1,
	'type' => CONF_TYPE_DIR,
	'level' => 4,
	);

	$settings[$category]['ASTLOGDIR'] = array(
	'value' => '/var/log/asterisk',
	'options' => '',
	'name' => 'Asterisk Log Dir',
	'description' => 'This is the default directory for Asterisks log files.',
	'readonly' => 1,
	'type' => CONF_TYPE_DIR,
	'level' => 4,
	);

	$settings[$category]['ASTMODDIR'] = array(
	'value' => '/usr/lib/asterisk/modules',
	'options' => '',
	'name' => 'Asterisk Modules Dir',
	'description' => 'This is the default directory for Asterisks modules.',
	'readonly' => 1,
	'type' => CONF_TYPE_DIR,
	'level' => 4,
	);

	$settings[$category]['ASTSPOOLDIR'] = array(
	'value' => '/var/spool/asterisk',
	'options' => '',
	'name' => 'Asterisk Spool Dir',
	'description' => 'This is the default directory for Asterisks spool directory.',
	'readonly' => 1,
	'type' => CONF_TYPE_DIR,
	'level' => 4,
	);

	$settings[$category]['ASTRUNDIR'] = array(
	'value' => '/var/run/asterisk',
	'options' => '',
	'name' => 'Asterisk Run Dir',
	'description' => 'This is the default directory for Asterisks run files.',
	'readonly' => 1,
	'type' => CONF_TYPE_DIR,
	'level' => 4,
	);

	$settings[$category]['ASTVARLIBDIR'] = array(
	'value' => '/var/lib/asterisk',
	'options' => '',
	'name' => 'Asterisk bin Dir',
	'description' => 'This is the default directory for Asterisks lib files.',
	'readonly' => 1,
	'type' => CONF_TYPE_DIR,
	'level' => 4,
	);

	$settings[$category]['AMPPLAYBACK'] = array(
	'value' => '/var/lib/asterisk/playback',
	'options' => '',
	'name' => 'Browser Playback Cache Directory',
	'description' => 'This is the default directory for HTML5 releated playback files',
	'readonly' => 1,
	'type' => CONF_TYPE_DIR,
	'level' => 4,
	);

	$settings[$category]['AMPCGIBIN'] = array(
	'value' => '/var/www/cgi-bin',
	'options' => '',
	'name' => 'CGI Dir',
	'description' => 'The path to Apache cgi-bin dir (leave off trailing slash).',
	'readonly' => 1,
	'type' => CONF_TYPE_DIR,
	'level' => 4,
	);

	$settings[$category]['MOHDIR'] = array(
	'value' => 'moh',
	'options' => array('moh','mohmp3'),
	'name' => 'MoH Subdirectory',
	'description' => 'This is the subdirectory for the MoH files/directories which is located in ASTVARLIBDIR. Older installation may be using mohmp3 which was the old Asterisk default and should be set to that value if the music files are located there relative to the ASTVARLIBDIR.',
	'readonly' => 1,
	'type' => CONF_TYPE_SELECT,
	'level' => 4,
	);

	$settings[$category]['CERTKEYLOC'] = array(
	'value' => '/etc/asterisk/keys',
	'options' => '',
	'name' => 'Certificate File Location',
	'description' => 'The location for Asterisk Certificates',
	'readonly' => 1,
	'type' => CONF_TYPE_DIR,
	'level' => 4,
	);

	$category = 'GUI Behavior';

	$settings[$category]['FPBXOPMODE'] = array(
	'value' => 'advanced',
	'options' => 'basic,advanced',
	'name' => 'GUI Operation Mode',
	'description' => 'Determines the mode to use while navigating the PBX. Defaults to "Advanced". If a module does not support "Basic" mode it will default to "Advanced"',
	'sortorder' => -135,
	'readonly' => 1,
	'hidden' => 1,
	'type' => CONF_TYPE_SELECT,
	);

	$settings[$category]['CHECKREFERER'] = array(
	'value' => true,
	'options' => '',
	'name' => 'Check Server Referrer',
	'description' => 'When set to the default value of true, all requests into FreePBX that might possibly add/edit/delete settings will be validated to assure the request is coming from the server. This will protect the system from CSRF (cross site request forgery) attacks. It will have the effect of preventing legitimately entering URLs that could modify settings which can be allowed by changing this field to false.',
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['MODULEADMINWGET'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Use wget For Module Admin',
	'description' => 'Module Admin normally tries to get its online information through direct file open type calls to URLs that go back to the freepbx.org server. If it fails, typically because of content filters in firewalls that do not like the way PHP formats the requests, the code will fall back and try a wget to pull the information. This will often solve the problem. However, in such environment there can be a significant timeout before the failed file open calls to the URLs return and there are often 2-3 of these that occur. Setting this value will force FreePBX to avoid the attempt to open the URL and go straight to the wget calls.',
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['MODULEADMINEDGE'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Set Module Admin to Edge mode',
	'description' => 'Setting module admin to edge mode allows you to vet new module releases before they are deemed stable. This process helps the developers so we encourage you to enable it. If you want a more stable system please leave this set to no. See http://wiki.freepbx.org/x/boi3Aw for more details',
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['SHOWLANGUAGE'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Show Language setting',
	'description' => 'Show Language setting on menu . Defaults = false',
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['SERVERINTITLE'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Include Server Name in Browser',
	'description' => 'Precede browser title with the server name.',
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['RELOADCONFIRM'] = array(
	'value' => true,
	'options' => '',
	'name' => 'Require Confirm with Apply Changes',
	'description' => 'When set to false, will bypass the confirm on Reload Box.',
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['BADDESTABORT'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Abort Config Gen on Bad Dest',
	'description' => 'Setting either of these to true will result in retrieve_conf aborting during a reload if an extension conflict is detected or a destination is detected. It is usually better to allow the reload to go through and then correct the problem but these can be set if a more strict behavior is desired.',
	'level' => 3,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['XTNCONFLICTABORT'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Abort Config Gen on Exten Conflict',
	'description' => 'Setting either of these to true will result in retrieve_conf aborting during a reload if an extension conflict is detected or a destination is detected. It is usually better to allow the reload to go through and then correct the problem but these can be set if a more strict behavior is desired.',
	'level' => 3,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['CUSTOMASERROR'] = array(
	'value' => true,
	'options' => '',
	'name' => 'Report Unknown Dest as Error',
	'description' => 'If false, then the Destination Registry will not report unknown destinations as errors. This should be left to the default true and custom destinations should be moved into the new custom apps registry.',
	'level' => 2,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['USE_FREEPBX_MENU_CONF'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Use freepbx_menu.conf Configuration',
	'description' => 'When set to true, the system will check for a freepbx_menu.conf file amongst the normal configuraiton files and if found, it will be used to define and remap the menu tabs and contents. See http://wiki.freepbx.org/x/6YDGAg for more details',
	'type' => CONF_TYPE_BOOL,
	);


	$category = 'Asterisk Manager';

	$settings[$category]['AMPMGRPASS'] = array(
	'value' => 'amp111',
	'options' => '',
	'name' => 'Asterisk Manager Password',
	'description' => 'Password for accessing the Asterisk Manager Interface (AMI), this will be automatically updated in manager.conf.',
	'type' => CONF_TYPE_TEXT,
	'level' => 2,
	);

	$settings[$category]['AMPMGRUSER'] = array(
	'value' => 'admin',
	'options' => '',
	'name' => 'Asterisk Manager User',
	'description' => 'Username for accessing the Asterisk Manager Interface (AMI), this will be automatically updated in manager.conf.',
	'type' => CONF_TYPE_TEXT,
	'level' => 2,
	);

	$settings[$category]['ASTMANAGERHOST'] = array(
	'value' => 'localhost',
	'options' => '',
	'name' => 'Asterisk Manager Host',
	'description' => 'Hostname for the Asterisk Manager',
	'readonly' => 1,
	'type' => CONF_TYPE_TEXT,
	'level' => 2,
	);

	$settings[$category]['ASTMANAGERPORT'] = array(
	'value' => '5038',
	'name' => 'Asterisk Manager Port',
	'description' => 'Port for the Asterisk Manager',
	'readonly' => 1,
	'type' => CONF_TYPE_INT,
	'options' => array(1024,65535),
	'level' => 2,
	);

	$settings[$category]['ASTMANAGERPROXYPORT'] = array(
	'value' => '',
	'name' => 'Asterisk Manager Proxy Port',
	'description' => 'Optional port for an Asterisk Manager Proxy',
	'readonly' => 1,
	'type' => CONF_TYPE_INT,
	'emptyok' => 1,
	'options' => array(1024,65535),
	'level' => 2,
	);

	$settings[$category]['ASTMGRWRITETIMEOUT'] = array(
	'value' => '5000',
	'name' => 'Asterisk Manager Write Timeout',
	'description' => 'Timeout, im ms, for write timeouts for cases where Asterisk disconnects frequently',
	'readonly' => 1,
	'type' => CONF_TYPE_INT,
	'emptyok' => 1,
	'options' => array(100,100000),
	'level' => 2,
	);


	$category = 'Developer and Customization';

	$settings[$category]['FPBXDBUGFILE'] = array(
	'value' => $amp_conf['ASTLOGDIR'] . '/freepbx_dbug',
	'options' => '',
	'name' => 'Debug File',
	'description' => 'Full path and name of FreePBX debug file. Used by the dbug() function by developers.',
	'level' => 2,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['FPBXDBUGDISABLE'] = array(
	'value' => true,
	'options' => '',
	'name' => 'Disable FreePBX dbug Logging',
	'description' => 'Set to true to stop all dbug() calls from writing to the Debug File (FPBXDBUGFILE)',
	'level' => 2,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['FPBXPERFLOGGING'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Enable Performance Logging',
	'description' => 'Set to true to enable Advanced Performance Logging into the dbug file',
	'level' => 2,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['DIE_FREEPBX_VERBOSE'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Provide Verbose Tracebacks',
	'description' => 'Provides a very verbose traceback when die_freepbx() is called including extensive object details if present in the traceback.',
	'level' => 2,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['DEVEL'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Developer Mode',
	'description' => 'This enables several debug features geared towards developers, including some page load timing information, some debug information in Module Admin, use of original CSS files and other future capabilities will be enabled.',
	'level' => 2,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['USE_PACKAGED_JS'] = array(
	'value' => true,
	'options' => '',
	'name' => 'Use Packaged Javascript Library ',
	'description' => 'FreePBX packages several javascript libraries and components into a compressed file called libfreepbx.javascript.js. By default this will be loaded instead of the individual uncompressed libraries. Setting this to false will force FreePBX to load all the libraries as individual uncompressed files. This is useful during development and debugging.',
	'level' => 2,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['FORCE_JS_CSS_IMG_DOWNLOAD'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Always Download Web Assets',
	'description' => 'FreePBX appends versioning tags on the CSS and javascript files and some of the main logo images. The versioning will help force browsers to load new versions of the files when module versions are upgraded. Setting this value to true will try to force these to be loaded to the browser every page load by appending an additional timestamp in the version information. This is useful during development and debugging where changes are being made to javascript and CSS files.',
	'level' => 2,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['DEVELRELOAD'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Leave Reload Bar Up',
	'description' => "Forces the 'Apply Configuration Changes' reload bar to always be present even when not necessary.",
	'level' => 2,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['PRE_RELOAD'] = array(
	'value' => '',
	'options' => '',
	'name' => 'PRE_RELOAD Script',
	'description' => 'Optional script to run just prior to doing an extension reload to Asterisk through the manager after pressing Apply Configuration Changes in the GUI.',
	'emptyok' => 1,
	'readonly' => 1,
	'level' => 2,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['POST_RELOAD'] = array(
	'value' => '',
	'options' => '',
	'name' => 'POST_RELOAD Script',
	'description' => 'Automatically execute a script after applying changes in the AMP admin. Set POST_RELOAD to the script you wish to execute after applying changes. If POST_RELOAD_DEBUG=true, you will see the output of the script in the web page.',
	'emptyok' => 1,
	'readonly' => 1,
	'level' => 2,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['POST_RELOAD_DEBUG'] = array(
	'value' => false,
	'options' => '',
	'name' => 'POST_RELOAD Debug Mode',
	'description' => 'Display debug output for script used if POST_RELOAD is used.',
	'level' => 2,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['AMPLOCALBIN'] = array(
	'value' => '',
	'options' => '',
	'name' => 'AMPLOCALBIN Dir for retrieve_conf',
	'description' => 'If this directory is defined, retrieve_conf will check for a file called <i>retrieve_conf_post_custom</i> and if that file exists, it will be included after other processing thus having full access to the current environment for additional customization.',
	'emptyok' => 1,
	'readonly' => 1,
	'level' => 2,
	'type' => CONF_TYPE_DIR,
	);

	$settings[$category]['DISABLE_CSS_AUTOGEN'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Disable Mainstyle CSS Compression',
	'description' => 'Stops the automatic generation of a stripped CSS file that replaces the primary sheet, usually mainstyle.css.',
	'level' => 2,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['MODULEADMIN_SKIP_CACHE'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Disable Module Admin Caching',
	'description' => 'Module Admin caches a copy of the online XML document that describes what is available on the server. Subsequent online update checks will use the cached information if it is less than 5 minutes old. To bypass the cache and force it to go to the server each time, set this to True. This should normally be false but can be helpful during testing.',
	'readonly' => 1,
	'level' => 2,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['ENABLE_MIRROR_LOG'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Enable Mirror Debug Log',
	'description' => 'Log the requests and responses from the Mirror server',
	'readonly' => 1,
	'hidden' => 1,
	'level' => 2,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['DISPLAY_MONITOR_TRUNK_FAILURES_FIELD'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Display Monitor Trunk Failures Option',
	'description' => 'Setting this to true will expose the "Monitor Trunk Failures" field on the Trunks page. This field allows for a custom AGI script to be called upon a trunk failure. This is an advanced field requiring a custom script to be properly written and installed. Existing trunk page entries will not be affected if this is set to false but if the settings are changed on those pages the field will go away.',
	'level' => 2,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['JQMIGRATE'] = array(
	'value' => true,
	'options' => '',
	'category' => 'Developer and Customization',
	'name' => 'Enable jQuery Migrate',
	'description' => 'This plugin can be used to detect and restore APIs or features that have been deprecated in jQuery and removed as of version 1.9',
	'type' => CONF_TYPE_BOOL,
	);


	$category = 'Flash Operator Panel';

	$settings[$category]['FOPWEBROOT'] = array(
	'value' => '',
	'options' => '',
	'name' => 'FOP Web Root Dir',
	'description' => 'Path to the Flash Operator Panel webroot or other modules providing such functionality (leave off trailing slash).',
	'emptyok' => 1,
	'readonly' => 1,
	'type' => CONF_TYPE_DIR,
	'level' => 4,
	);


	$category = 'Remote CDR Database';

	$settings[$category]['CDRDBHOST'] = array(
	'value' => '',
	'options' => '',
	'name' => 'Remote CDR DB Host',
	'description' => 'DO NOT set this unless you know what you are doing. Only used if you do not use the default values provided by FreePBX.<br>Hostname of db server if not the same as AMPDBHOST.',
	'emptyok' => 1,
	'readonly' => 1,
	'level' => 3,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['CDRDBNAME'] = array(
	'value' => '',
	'options' => '',
	'name' => 'Remote CDR DB Name',
	'description' => 'DO NOT set this unless you know what you are doing. Only used if you do not use the default values provided by FreePBX.<br>Name of database used for cdr records.',
	'emptyok' => 1,
	'readonly' => 1,
	'level' => 3,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['CDRDBPASS'] = array(
	'value' => '',
	'options' => '',
	'name' => 'Remote CDR DB Password',
	'description' => 'DO NOT set this unless you know what you are doing. Only used if you do not use the default values provided by FreePBX.<br>Password for connecting to db if its not the same as AMPDBPASS.',
	'emptyok' => 1,
	'readonly' => 1,
	'level' => 3,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['CDRDBPORT'] = array(
	'value' => '',
	'options' => array(1024,65536),
	'name' => 'Remote CDR DB Port',
	'description' => 'DO NOT set this unless you know what you are doing. Only used if you do not use the default values provided by FreePBX.<br>Port number for db host.',
	'emptyok' => 1,
	'readonly' => 1,
	'level' => 3,
	'type' => CONF_TYPE_INT,
	);

	$settings[$category]['CDRDBTABLENAME'] = array(
	'value' => '',
	'options' => '',
	'name' => 'Remote CDR DB Table',
	'description' => 'DO NOT set this unless you know what you are doing. Only used if you do not use the default values provided by FreePBX. Name of the table in the db where the cdr is stored. cdr is default.',
	'emptyok' => 1,
	'readonly' => 1,
	'level' => 3,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['CDRDBTYPE'] = array(
	'value' => '',
	'description' => 'DO NOT set this unless you know what you are doing. Only used if you do not use the default values provided by FreePBX. Defaults to your configured AMDBENGINE.',
	'name' => 'Remote CDR DB Type',
	'emptyok' => 1,
	'options' => ',mysql,postgres',
	'readonly' => 1,
	'level' => 3,
	'type' => CONF_TYPE_SELECT,
	);

	$settings[$category]['CDRDBUSER'] = array(
	'value' => '',
	'options' => '',
	'name' => 'Remote CDR DB User',
	'description' => 'DO NOT set this unless you know what you are doing. Only used if you do not use the default values provided by FreePBX. Username to connect to db with if it is not the same as AMPDBUSER.',
	'emptyok' => 1,
	'readonly' => 1,
	'level' => 3,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['CDRUSEGMT'] = array(
		'value' => false,
		'options' => '',
		'name' => 'Use GMT Time',
		'description' => 'Insert the date information into the CDR database using GMT time',
		'readonly' => 1,
		'type' => CONF_TYPE_BOOL,
	);

	$category = 'Styling and Logos';

	$settings[$category]['BRAND_IMAGE_FAVICON'] = array(
	'value' => 'images/favicon.ico',
	'options' => '',
	'name' => 'Favicon',
	'description' => 'Favicon',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 40,
	'type' => CONF_TYPE_TEXT,
	'level' => 1,
	);

	$settings[$category]['BRAND_TITLE'] = array(
	'value' => 'FreePBX Administration',
	'options' => '',
	'name' => 'Page Title',
	'description' => 'HTML title of all pages',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 40,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['BRAND_IMAGE_TANGO_LEFT'] = array(
	'value' => 'images/tango.png',
	'options' => '',
	'name' => 'Image: Left Upper',
	'description' => 'Left upper logo.Path is relative to admin.',
	'readonly' => 1,
	'sortorder' => 40,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['BRAND_IMAGE_FREEPBX_FOOT'] = array(
	'value' => 'images/freepbx_small.png',
	'options' => '',
	'name' => 'Image: Footer',
	'description' => 'Logo in footer.Path is relative to admin.',
	'readonly' => 1,
	'sortorder' => 50,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	'emptyok' => 1,
	);

	$settings[$category]['BRAND_IMAGE_SPONSOR_FOOT'] = array(
	'value' => 'images/sangoma-horizontal_thumb.png',
	'options' => '',
	'name' => 'Image: Footer',
	'description' => 'Logo in footer.Path is relative to admin.',
	'readonly' => 1,
	'sortorder' => 50,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	'emptyok' => 1,
	);

	$settings[$category]['BRAND_FREEPBX_ALT_LEFT'] = array(
	'value' => 'FreePBX',
	'options' => '',
	'name' => 'Alt for Left Logo',
	'description' => 'alt attribute to use in place of image and title hover value. Defaults to FreePBX',
	'readonly' => 1,
	'sortorder' => 70,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	'emptyok' => 1,
	);

	$settings[$category]['BRAND_FREEPBX_ALT_FOOT'] = array(
	'value' => 'FreePBX&reg;',
	'options' => '',
	'name' => 'Alt for Footer Logo',
	'description' => 'alt attribute to use in place of image and title hover value. Defaults to FreePBX',
	'readonly' => 1,
	'sortorder' => 90,
	'type' => CONF_TYPE_TEXT,
	'level' => 1,
	'emptyok' => 1,
	);

	$settings[$category]['BRAND_SPONSOR_ALT_FOOT'] = array(
	'value' => 'www.sangoma.com',
	'options' => '',
	'name' => 'Alt for Footer Logo',
	'description' => 'alt attribute to use in place of image and title hover value. Defaults to FreePBX',
	'readonly' => 1,
	'sortorder' => 90,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	'emptyok' => 1,
	);

	$settings[$category]['BRAND_IMAGE_FREEPBX_LINK_LEFT'] = array(
	'value' => 'http://www.freepbx.org',
	'options' => '',
	'name' => 'Link for Left Logo',
	'description' => 'link to follow when clicking on logo, defaults to http://www.freepbx.org',
	'readonly' => 1,
	'sortorder' => 100,
	'type' => CONF_TYPE_TEXT,
	'level' => 1,
	'emptyok' => 1,
	);

	$settings[$category]['BRAND_IMAGE_FREEPBX_LINK_FOOT'] = array(
	'value' => 'http://www.freepbx.org',
	'options' => '',
	'name' => 'Link for Footer Logo',
	'description' => 'link to follow when clicking on logo, defaults to http://www.freepbx.org',
	'readonly' => 1,
	'sortorder' => 120,
	'type' => CONF_TYPE_TEXT,
	'level' => 1,
	'emptyok' => 1,
	);

	$settings[$category]['BRAND_IMAGE_SPONSOR_LINK_FOOT'] = array(
	'value' => 'http://www.sangoma.com',
	'options' => '',
	'name' => 'Link for Sponsor Footer Logo',
	'description' => 'link to follow when clicking on sponsor logo',
	'readonly' => 1,
	'sortorder' => 120,
	'type' => CONF_TYPE_TEXT,
	'level' => 1,
	'emptyok' => 1,
	);

	$settings[$category]['BRAND_CSS_ALT_MAINSTYLE'] = array(
	'value' => '',
	'options' => '',
	'name' => 'Primary CSS Stylesheet',
	'description' => 'Set this to replace the default mainstyle.css style sheet with your own, relative to admin.',
	'readonly' => 1,
	'sortorder' => 160,
	'type' => CONF_TYPE_TEXT,
	'level' => 1,
	'emptyok' => 1,
	);

	$settings[$category]['BRAND_CSS_ALT_POPOVER'] = array(
	'value' => '',
	'options' => '',
	'name' => 'Primary CSS Popover Stylesheet Addtion',
	'description' => 'Set this to replace the default popover.css style sheet with your own, relative to admin.',
	'readonly' => 1,
	'sortorder' => 162,
	'type' => CONF_TYPE_TEXT,
	'level' => 1,
	'emptyok' => 1,
	);

	$settings[$category]['BRAND_CSS_CUSTOM'] = array(
	'value' => '',
	'options' => '',
	'name' => 'Optional Additional CSS Stylesheet',
	'description' => 'Optional custom CSS style sheet included after the primary one and any module specific ones are loaded, relative to admin.',
	'readonly' => 1,
	'sortorder' => 170,
	'type' => CONF_TYPE_TEXT,
	'level' => 1,
	'emptyok' => 1,
	);

	$settings[$category]['VIEW_FREEPBX_ADMIN'] = array(
	'value' => 'views/freepbx_admin.php',
	'options' => '',
	'name' => 'View: freepbx_admin.php',
	'description' => 'freepbx_admin.php view. This should never be changed except for very advanced layout changes.',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 180,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_FREEPBX'] = array(
	'value' => 'views/freepbx.php',
	'options' => '',
	'name' => 'View: freepbx.php',
	'description' => 'freepbx.php view. This should never be changed except for very advanced layout changes.',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 190,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_FREEPBX_RELOAD'] = array(
	'value' => 'views/freepbx_reload.php',
	'options' => '',
	'name' => 'View: freepbx_reload.php',
	'description' => 'freepbx_reload.php view. This should never be changed except for very advanced layout changes.',
	'readonly' => 1,
	'hidden' => 1,
	'level' => 1,
	'sortorder' => 200,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_FREEPBX_RELOADBAR'] = array(
	'value' => 'views/freepbx_reloadbar.php',
	'options' => '',
	'name' => 'View: freepbx_reloadbar.php',
	'description' => 'freepbx_reloadbar.php view. This should never be changed except for very advanced layout changes.',
	'readonly' => 1,
	'hidden' => 1,
	'level' => 1,
	'sortorder' => 210,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_WELCOME'] = array(
	'value' => 'views/welcome.php',
	'options' => '',
	'name' => 'View: welcome.php',
	'description' => 'welcome.php view. This should never be changed except for very advanced layout changes.',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 220,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_WELCOME_NONMANAGER'] = array(
	'value' => 'views/welcome_nomanager.php',
	'options' => '',
	'name' => 'View: welcome_nomanager.php',
	'description' => 'welcome_nomanager.php view. This should never be changed except for very advanced layout changes.',
	'readonly' => 1,
	'hidden' => 1,
	'level' => 1,
	'sortorder' => 230,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_MENUITEM_DISABLED'] = array(
	'value' => 'views/menuitem_disabled.php',
	'options' => '',
	'name' => 'View: menuitem_disabled.php',
	'description' => 'menuitem_disabled.php view. This should never be changed except for very advanced layout changes.',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 240,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_NOACCESS'] = array(
	'value' => 'views/noaccess.php',
	'options' => '',
	'name' => 'View: noaccess.php',
	'description' => 'noaccess.php view. This should never be changed except for very advanced layout changes.',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 250,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_UNAUTHORIZED'] = array(
	'value' => 'views/unauthorized.php',
	'options' => '',
	'name' => 'View: unauthorized.php',
	'description' => 'unauthorized.php view. This should never be changed except for very advanced layout changes.',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 260,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_BAD_REFFERER'] = array(
	'value' => 'views/bad_refferer.php',
	'options' => '',
	'name' => 'View: bad_refferer.php',
	'description' => 'bad_refferer.php view. This should never be changed except for very advanced layout changes.',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 270,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_LOGGEDOUT'] = array(
	'value' => 'views/loggedout.php',
	'options' => '',
	'name' => 'View: loggedout.php',
	'description' => 'loggedout.php view. This should never be changed except for very advanced layout changes.',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 280,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_PANEL'] = array(
	'value' => 'views/panel.php',
	'options' => '',
	'name' => 'View: panel.php',
	'description' => 'panel.php view. This should never be changed except for very advanced layout changes.',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 290,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_REPORTS'] = array(
	'value' => 'views/reports.php',
	'options' => '',
	'name' => 'View: reports.php',
	'description' => 'reports.php view. This should never be changed except for very advanced layout changes.',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 300,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_MENU'] = array(
	'value' => 'views/menu.php',
	'options' => '',
	'name' => 'View: menu.php',
	'description' => 'menu.php view. This should never be changed except for very advanced layout changes',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 310,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_BETA_NOTICE'] = array(
	'value' => 'views/beta_notice.php',
	'options' => '',
	'name' => 'View: beta_notice.php',
	'description' => 'beta_notice.php view. This should never be changed except for very advanced layout changes',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 312,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_OBE'] = array(
	'value' => 'views/obe.php',
	'options' => '',
	'name' => 'View: obe.php',
	'description' => 'obe.php view. This should never be changed except for very advanced layout changes',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 310,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['JQUERY_CSS'] = array(
	'value' => 'assets/css/jquery-ui.css',
	'options' => '',
	'name' => 'jQuery UI css',
	'description' => 'css file for jquery ui',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 320,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_LOGIN'] = array(
	'value' => 'views/login.php',
	'options' => '',
	'name' => 'View: login.php',
	'description' => 'login.php view. This should never be changed except for very advanced layout changes',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 330,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_HEADER'] = array(
	'value' => 'views/header.php',
	'options' => '',
	'name' => 'View: header.php',
	'description' => 'header.php view. This should never be changed except for very advanced layout changes',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 340,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_FOOTER'] = array(
	'value' => 'views/footer.php',
	'options' => '',
	'name' => 'View: freepbx.php',
	'description' => 'footer.php view. This should never be changed except for very advanced layout changes',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 350,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_FOOTER_CONTENT'] = array(
	'value' => 'views/footer_content.php',
	'options' => '',
	'name' => 'View: footer_content.php',
	'description' => 'footer_content.php view. This should never be changed except for very advanced layout changes',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 360,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['VIEW_POPOVER_JS'] = array(
	'value' => 'views/popover_js.php',
	'options' => '',
	'name' => 'View: popover_js.php',
	'description' => 'popover_js.php view. This should never be changed except for very advanced layout changes',
	'readonly' => 1,
	'hidden' => 1,
	'sortorder' => 355,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['BRAND_ALT_JS'] = array(
	'value' => '',
	'options' => '',
	'name' => 'Alternate JS',
	'description' => 'Alternate JS file, to supplement legacy.script.js',
	'readonly' => 1,
	'emptyok' => 1,
	'hidden' => 1,
	'sortorder' => 360,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['DASHBOARD_FREEPBX_BRAND'] = array(
	'value' => 'FreePBX',
	'options' => '',
	'name' => 'FreePBX Brand',
	'description' => 'The FreePBX Brand Name',
	'readonly' => 1,
	'emptyok' => 1,
	'hidden' => 1,
	'sortorder' => 360,
	'level' => 1,
	'type' => CONF_TYPE_TEXT,
	);


	$category = 'Device Settings';

	$settings[$category]['DEVICE_STRONG_SECRETS'] = array(
	'value' => true,
	'options' => '',
	'name' => 'Require Strong Secrets',
	'description' => 'Requires a strong secret on SIP and IAX devices requiring at least two numeric and non-numeric characters and 6 or more characters. This can be disabled if using devices that can not meet these needs, or you prefer to put other constraints including more rigid constraints that this rule actually considers weak when it may not be.',
	'type' => CONF_TYPE_BOOL,
	'sortorder' => 12,
	);

	$settings[$category]['DEVICE_REMOVE_MAILBOX'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Remove mailbox Setting when no Voicemail',
	'description' => 'If set to true, any fixed device associated with a user that has no voicemail configured will have the "mailbox=" setting removed in the generated technology configuration file such as sip_additional.conf. This will not affect the value in the GUI.',
	'type' => CONF_TYPE_BOOL,
	'sortorder' => 15,
	);

	$settings[$category]['DEVICE_SIP_CANREINVITE'] = array(
	'value' => 'no',
	'options' => array('no', 'yes', 'nonat', 'update'),
	'name' => 'SIP canrenivite (directmedia)',
	'description' => 'Default setting for (new Extension) SIP canreinvite (same as directmedia). See Asterisk documentation for details.',
	'type' => CONF_TYPE_SELECT,
	'sortorder' => 20,
	);

	$settings[$category]['DEVICE_SIP_DTMF'] = array(
	'value' => 'rfc2833',
	'options' => array('rfc2833', 'auto', 'shortinfo', 'info', 'inband'),
	'name' => 'SIP DTMF Signaling',
	'description' => 'The DTMF signaling mode used by this device, usually RFC for most phones. (Note: For PJSIP devices RFC-4733 supercedes the older RFC-2833 and will be used when RFC-2833 is selected for PJSIP devices)',
	'type' => CONF_TYPE_SELECT,
	'sortorder' => 20,
	);

	$settings[$category]['DEVICE_SIP_TRUSTRPID'] = array(
	'value' => 'yes',
	'options' => array('no', 'yes'),
	'name' => 'SIP trustrpid',
	'description' => 'Default setting for (new Extension) SIP trustrpid. See Asterisk documentation for details.',
	'type' => CONF_TYPE_SELECT,
	'sortorder' => 30,
	);

	$settings[$category]['DEVICE_SIP_SENDRPID'] = array(
	'value' => 'pai',
	'options' => array('no', 'yes', 'pai'),
	'name' => 'SIP sendrpid',
	'description' => "Default setting for (new Extension) SIP sendrpid. A value of 'yes' is equivalent to 'rpid' and will send the 'Remote-Party-ID' header. A value of 'pai' will send the 'P-Asserted-Identity' header. See Asterisk documentation for details.",
	'type' => CONF_TYPE_SELECT,
	'sortorder' => 40,
	);

	$settings[$category]['DEVICE_SIP_NAT'] = array(
	'value' => 'yes',
	'options' => array('no', 'yes', 'never', 'route'),
	'name' => 'SIP nat',
	'description' => "Default setting for (new Extension) SIP nat. A 'yes' will attempt to handle nat, also works for local (uses the network ports and address instead of the reported ports), 'no' follows the protocol, 'never' tries to block it, no RFC3581, 'route' ignores the rport information. See Asterisk documentation for details.",
	'type' => CONF_TYPE_SELECT,
	'sortorder' => 50,
	);

	$settings[$category]['DEVICE_SIP_ENCRYPTION'] = array(
	'value' => 'no',
	'options' => array('no', 'yes'),
	'name' => 'SIP encryption',
	'description' => "Default setting for (new Extension)  SIP encryption. Whether to offer SRTP encrypted media (and only SRTP encrypted media) on outgoing calls to a peer. Calls will fail with HANGUPCAUSE=58 if the peer does not support SRTP. See Asterisk documentation for details.",
	'type' => CONF_TYPE_SELECT,
	'sortorder' => 60,
	);

	$settings[$category]['DEVICE_SIP_QUALIFYFREQ'] = array(
	'value' => 60,
	'options' => array(15, 86400),
	'name' => 'SIP qualifyfreq',
	'description' => "Default setting for (new Extension) SIP qualifyfreq. Only valid for Asterisk 1.6 and above. Frequency that 'qualify' OPTIONS messages will be sent to the device. Can help to keep NAT holes open but not dependable for remote client firewalls. See Asterisk documentation for details.",
	'type' => CONF_TYPE_INT,
	'sortorder' => 70,
	);

	$settings[$category]['DEVICE_QUALIFY'] = array(
	'value' => 'yes',
	'options' => '',
	'name' => 'SIP and IAX qualify',
	'description' => "Default setting for (new Extension) SIP and IAX qualify. Whether to send periodic OPTIONS messages (for SIP) or otherwise monitor the channel, and at what point to consider the channel unavailable. A value of 'yes' is equivalent to 2000, time in msec. Can help to keep NAT holes open with SIP but not dependable for remote client firewalls. See Asterisk documentation for details.",
	'type' => CONF_TYPE_TEXT,
	'sortorder' => 80,
	);

	$settings[$category]['DEVICE_DISALLOW'] = array(
	'value' => '',
	'options' => '',
	'name' => 'SIP and IAX disallow',
	'description' => "Default setting for (new Extension) SIP and IAX disallow (for codecs). Codecs to disallow, can help to reset from the general settings by setting a value of 'all' and then specifically including allowed codecs with the 'allow' directive. Values van be separated with '&' e.g. 'g729&g722'. See Asterisk documentation for details.",
	'type' => CONF_TYPE_TEXT,
	'emptyok' => 1,
	'sortorder' => 90,
	);

	$settings[$category]['DEVICE_ALLOW'] = array(
	'value' => '',
	'options' => '',
	'name' => 'SIP and IAX allow',
	'description' => "Default setting for (new Extension) SIP and IAX allow (for codecs). Codecs to allow in addition to those set in general settings unless explicitly 'disallowed' for the device. Values van be separated with '&' e.g. 'ulaw&g729&g729' where the preference order is preserved. See Asterisk documentation for details.",
	'type' => CONF_TYPE_TEXT,
	'emptyok' => 1,
	'sortorder' => 90,
	);

	$settings[$category]['DEVICE_CALLGROUP'] = array(
	'value' => '',
	'options' => '',
	'name' => 'SIP and DAHDi callgroup',
	'description' => "Default setting for (new Extension) SIP, DAHDi (and Zap) callgroup. Callgroup(s) that the device is part of, can be one or more callgroups, e.g. '1,3-5' would be in groups 1,3,4,5. See Asterisk documentation for details.",
	'type' => CONF_TYPE_TEXT,
	'emptyok' => 1,
	'sortorder' => 100,
	);

	$settings[$category]['DEVICE_PICKUPGROUP'] = array(
	'value' => '',
	'options' => '',
	'name' => 'SIP and DAHDi pickupgroup',
	'description' => "Default setting for (new Extension) SIP, DAHDi (and Zap) pickupgroup. Pickupgroups(s) that the device can pickup calls from, can be one or more groups, e.g. '1,3-5' would be in groups 1,3,4,5. Device does not have to be in a group to be able to pickup calls from that group. See Asterisk documentation for details.",
	'type' => CONF_TYPE_TEXT,
	'emptyok' => 1,
	'sortorder' => 110,
	);


	$category = 'Internal Use';

	$settings[$category]['SIPUSERAGENT'] = array(
	'value' => 'FPBX',
	'options' => '',
	'name' => 'SIP User Agent',
	'description' => 'User Agent prefix',
	'readonly' => 1,
	'hidden' => 1,
	'level' => 10,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['MODULE_REPO'] = array(
	'value' => 'https://mirror.freepbx.org',
	'options' => '',
	'name' => 'Repo Server',
	'description' => 'repo server',
	'readonly' => 1,
	'hidden' => 1,
	'level' => 10,
	'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['NOTICE_BROWSER_STATS'] = array(
	'value' => false,
	'options' => '',
	'name' => 'Browser Stats Notice',
	'description' => 'Internal use to track if notice has been given that anonyous browser stats are being collected.',
	'readonly' => 1,
	'hidden' => 1,
	'level' => 10,
	'type' => CONF_TYPE_BOOL,
	);

	$settings[$category]['mainstyle_css_generated'] = array(
	'value' => (isset($amp_conf['mainstyle_css_generated']) && $amp_conf['mainstyle_css_generated']) ? $amp_conf['mainstyle_css_generated'] : '',
	'description' => 'internal use',
	'type' => CONF_TYPE_TEXT,
	'name' => 'Compressed Copy of Main CSS',
	'readonly' => 1,
	'hidden' => 1,
	'level' => 10,
	'emptyok' => 1,
	);

	$settings[$category]['SESSION_TIMEOUT'] = array(
	'value' => 2592000, //30 days in seconds
	'options' => array(1, 2147483647),
	'name' => 'Session Timeout',
	'description' => 'Amount of seconds to allow a session to stay open before logging a user out. For unlimited clear this box and save.',
	'level' => 10,
	'type' => CONF_TYPE_INT,
	'emptyok' => 1,
	);

	$settings[$category]['CACHE_CLEANUP_DAYS'] = array(
	'value' => 30, //30 days in seconds
	'options' => array(1, 2147483647),
	'name' => 'Browser Playback Day Cache',
	'description' => 'Amount of days to keep browser playback (HTML5 audio files) cache. Set this to a lower value to conserve disk space.',
	'level' => 10,
	'type' => CONF_TYPE_INT,
	'emptyok' => 0,
	);

	$settings[$category]['VIEW_ZEND_CONFIG'] = array(
		'value' => 'views/zend_config.php', //30 days in seconds
		'options' => '',
		'name' => 'View: zend_config.php',
		'description' => 'zend_config.php view. This should never be changed except for very advanced layout changes.',
		'readonly' => 1,
		'hidden' => 1,
		'level' => 10,
		'emptyok' => 0,
		'sortorder' => 180,
		'type' => CONF_TYPE_TEXT
	);

	// FREEPBX13 - Add Proxy Support
	$category = "Proxy Settings";

	$settings[$category]['PROXY_ENABLED'] = array(
		'name' => "Use HTTP(S) Proxy",
		'description' => "Enable this to send outbound HTTP and HTTPS requests via a proxy. This does not affect Voice or Video traffic.",
		'value' => false,
		'defaultval' => false,
		'emptyok' => 0,
		'readonly' => 0,
		'sortorder' => 1,
		'type' => CONF_TYPE_BOOL,
	);


	$settings[$category]['PROXY_ADDRESS'] = array(
		'name' => "Proxy Address",
		'description' => "Enter the address of the outbound proxy. This will be similar to http://10.1.1.1:3128",
		'value' => "",
		'defaultval' => "",
		'emptyok' => 1,
		'readonly' => 0,
		'sortorder' => 2,
		'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['PROXY_USERNAME'] = array(
		'name' => "Proxy Username",
		'description' => "If you need to authenticate to the proxy server, you must enter both a username and password. Leaving either (or both) blank disables Proxy Authentication",
		'value' => "",
		'defaultval' => "",
		'emptyok' => 1,
		'readonly' => 0,
		'sortorder' => 3,
		'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['PROXY_PASSWORD'] = array(
		'name' => "Proxy Password",
		'description' => "If you need to authenticate to the proxy server, you must enter both a username and password. Leaving either (or both) blank disables Proxy Authentication",
		'value' => "",
		'defaultval' => "",
		'emptyok' => 1,
		'readonly' => 0,
		'sortorder' => 4,
		'type' => CONF_TYPE_TEXT,
	);

	$settings[$category]['DASHBOARD_OVERRIDE'] = array(
		'description' => 'When no params specified, use this module',
		'name' => 'DASHBOARD_OVERRIDE',
		'value' => '',
		'options' => '',
		'readonly' => 1,
		'hidden' => 1,
		'level' => 10,
		'emptyok' => 1,
		'sortorder' => 180,
		'type' => CONF_TYPE_TEXT
	);

	$settings[$category]['DASHBOARD_OVERRIDE_BASIC'] = array(
		'description' => 'When no params specified, use this module in basic opmode',
		'name' => 'DASHBOARD_OVERRIDE_BASIC',
		'value' => '',
		'options' => '',
		'readonly' => 1,
		'hidden' => 1,
		'level' => 10,
		'emptyok' => 1,
		'sortorder' => 180,
		'type' => CONF_TYPE_TEXT
	);


	

	// The following settings are used in various modules prior to 2.9. If they are found in amportal.conf then we
	// retain their values until the individual modules are updated and their install scripts run where a full
	// configuration (descriptions, defaults, etc.) will be provided and maintained. This provides just enough to
	// carry the setting through the migration since most upgrades will run framework or install_amp followed by the
	// module install scripts.
	//
	$module_migrate = array(
		'AMPPLAYKEY' => CONF_TYPE_TEXT,
		'AMPBACKUPEMAILFROM' => CONF_TYPE_TEXT,
		'AMPBACKUPSUDO' => CONF_TYPE_BOOL,
		'USEQUEUESTATE' => CONF_TYPE_BOOL,
		'DASHBOARD_INFO_UPDATE_TIME' => CONF_TYPE_INT,
		'DASHBOARD_STATS_UPDATE_TIME' => CONF_TYPE_INT,
		'SSHPORT' => CONF_TYPE_INT,
		'MAXCALLS' => CONF_TYPE_INT,
		'AMPMPG123' => CONF_TYPE_BOOL,
	);

	foreach ($module_migrate as $setting => $type) {
		if (isset($amp_conf[$setting]) && !$freepbx_conf->conf_setting_exists($setting)) {
			$val = $amp_conf[$setting];

			// since this came from a conf file, change any 'false' that will otherwise turn to true
			if ($type == CONF_TYPE_BOOL) {
				switch (strtolower($val)) {
				case 'false':
				case 'no':
				case 'off':
					$val = false;
					break;
				}
			}

			if (in_array($setting, array('USEQUEUESTATE'))) {
				$val = true;
			}

			$category = 'Under Migration';
			$settings[$category][$setting] = array(
				'value' => $val,
				'defaultval' => '',
				'hidden' => 1,
				'level' => 10,
				'emptyok' => 1,
				'description' => 'This setting is being migrated and will be initialized by its module install script on upgrade.',
				'type' => $type,
			);
		}
	}

	$defaults = array(
		'value' => '',
		'options' => '',
		'module' => '',
		'level' => 0,
		'readonly' => 0,
		'hidden' => 0,
		'emptyok' => 0,
	);
	foreach ($settings as $category => $list) {
		foreach ($list as $name => $setting) {
			$setting = array_merge($defaults, $setting);

			$setting['category'] = $category;

			if (!isset($setting['defaultval'])) {
				$setting['defaultval'] = $setting['value'];
			}

			$freepbx_conf->define_conf_setting($name, $setting);
		}
	}

	if ($commit_to_db) {
		$freepbx_conf->commit_conf_settings();
	}

	$um = new \FreePBX\Builtin\UpdateManager();
	$um->updateCrontab();
	}
}
?>
