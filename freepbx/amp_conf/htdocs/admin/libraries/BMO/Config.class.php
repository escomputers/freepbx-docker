<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
namespace FreePBX;
use PDO;

//Legacy calls
define("CONF_TYPE_BOOL",   'bool');
define("CONF_TYPE_TEXTAREA",   'textarea');
define("CONF_TYPE_TEXT",   'text');
define("CONF_TYPE_DIR",    'dir');
define("CONF_TYPE_INT",    'int');
define("CONF_TYPE_SELECT", 'select');
define("CONF_TYPE_FSELECT",'fselect');
define("CONF_TYPE_CSELECT", 'cselect'); //custom entry select

class Config {

	const CONF_TYPE_BOOL = 'bool';
	const CONF_TYPE_TEXTAREA = 'textarea';
	const CONF_TYPE_TEXT = 'text';
	const CONF_TYPE_DIR = 'dir';
	const CONF_TYPE_INT = 'int';
	const CONF_TYPE_SELECT = 'select';
	const CONF_TYPE_FSELECT = 'fselect';
	const CONF_TYPE_CSELECT = 'cselect';

	/**
	 * $legacy_conf_defaults are used by parse_amprotal_conf to
	 * assure that a system being migrated has all the expected $amp_conf
	 * settings defined as the code expects them to be there.
	 */
	private $legacy_conf_defaults = array(
		'AMPDBENGINE'    => array(CONF_TYPE_SELECT, 'mysql'),
		'AMPDBNAME'      => array(CONF_TYPE_TEXT, 'asterisk'),
		'AMPENGINE'      => array(CONF_TYPE_SELECT, 'asterisk'),
		'ASTMANAGERPORT' => array(CONF_TYPE_INT, '5038'),
		'ASTMANAGERHOST' => array(CONF_TYPE_TEXT, 'localhost'),
		'AMPDBHOST'      => array(CONF_TYPE_TEXT, 'localhost'),
		'AMPDBUSER'      => array(CONF_TYPE_TEXT, 'asteriskuser'),
		'AMPDBPASS'      => array(CONF_TYPE_TEXT, 'amp109'),
		'AMPMGRUSER'     => array(CONF_TYPE_TEXT, 'admin'),
		'AMPMGRPASS'     => array(CONF_TYPE_TEXT, 'amp111'),
		'AMPSYSLOGLEVEL' => array(CONF_TYPE_SELECT, 'FILE'),
		'NOOPTRACE'      => array(CONF_TYPE_INT, '1'),
		'ARI_ADMIN_PASSWORD' => array(CONF_TYPE_TEXT, 'ari_password'),
		'CFRINGTIMERDEFAULT' => array(CONF_TYPE_SELECT, '0'),

		'AMPASTERISKWEBUSER'	=> array(CONF_TYPE_TEXT, 'asterisk'),
		'AMPASTERISKWEBGROUP'	=> array(CONF_TYPE_TEXT, 'asterisk'),
		'AMPASTERISKUSER'	=> array(CONF_TYPE_TEXT, 'asterisk'),
		'AMPASTERISKGROUP'	=> array(CONF_TYPE_TEXT, 'asterisk'),
		'AMPDEVUSER'	   => array(CONF_TYPE_TEXT, 'apache'),
		'AMPDEVGROUP'    => array(CONF_TYPE_TEXT, 'apache'),

		'ASTETCDIR'      => array(CONF_TYPE_DIR, '/etc/asterisk'),
		'ASTMODDIR'      => array(CONF_TYPE_DIR, '/usr/lib/asterisk/modules'),
		'ASTVARLIBDIR'   => array(CONF_TYPE_DIR, '/var/lib/asterisk'),
		'ASTAGIDIR'      => array(CONF_TYPE_DIR, '/var/lib/asterisk/agi-bin'),
		'ASTSPOOLDIR'    => array(CONF_TYPE_DIR, '/var/spool/asterisk/'),
		'ASTRUNDIR'      => array(CONF_TYPE_DIR, '/var/run/asterisk'),
		'ASTLOGDIR'      => array(CONF_TYPE_DIR, '/var/log/asterisk'),
		'AMPBIN'         => array(CONF_TYPE_DIR, '/var/lib/asterisk/bin'),
		'AMPSBIN'        => array(CONF_TYPE_DIR, '/usr/sbin'),
		'AMPWEBROOT'     => array(CONF_TYPE_DIR, '/var/www/html'),
		'MOHDIR'         => array(CONF_TYPE_DIR, 'mohmp3'),
		'FPBXDBUGFILE'	 => array(CONF_TYPE_DIR, '/tmp/freepbx_debug.log'),

		'ENABLECW'       => array(CONF_TYPE_BOOL, true),
		'CWINUSEBUSY'    => array(CONF_TYPE_BOOL, true),
		'AMPBADNUMBER'   => array(CONF_TYPE_BOOL, true),
		'DEVEL'          => array(CONF_TYPE_BOOL, false),
		'DEVELRELOAD'    => array(CONF_TYPE_BOOL, false),
		'CUSTOMASERROR'  => array(CONF_TYPE_BOOL, true),
		'DYNAMICHINTS'   => array(CONF_TYPE_BOOL, false),
		'BADDESTABORT'   => array(CONF_TYPE_BOOL, false),
		'SERVERINTITLE'  => array(CONF_TYPE_BOOL, false),
		'USEDEVSTATE'    => array(CONF_TYPE_BOOL, false),
		'MODULEADMINWGET'=> array(CONF_TYPE_BOOL, false),
		'AMPDISABLELOG'  => array(CONF_TYPE_BOOL, true),
		'CHECKREFERER'   => array(CONF_TYPE_BOOL, true),
		'RELOADCONFIRM'  => array(CONF_TYPE_BOOL, true),
		'DIVERSIONHEADER' => array(CONF_TYPE_BOOL, false),
		'ZAP2DAHDICOMPAT' => array(CONF_TYPE_BOOL, false),
		'XTNCONFLICTABORT' => array(CONF_TYPE_BOOL, false),
		'AMPENABLEDEVELDEBUG' => array(CONF_TYPE_BOOL, false),
		'DISABLECUSTOMCONTEXTS' => array(CONF_TYPE_BOOL, false),

		// Time Conditions (2.9 New)
		'TCINTERVAL'     => array(CONF_TYPE_INT, '60'),
		'TCMAINT'        => array(CONF_TYPE_BOOL, true),

		// Queues
		'USEQUEUESTATE'  => array(CONF_TYPE_BOOL, false),

		// Day Night (2.9 New)
		'DAYNIGHTTCHOOK' => array(CONF_TYPE_BOOL, false),

		// Music
		'AMPMPG123'      => array(CONF_TYPE_BOOL, true),
	);

	/**
	 * $db_conf_store is the resident internal store for settings
	 * and is backed by the freepbx_settings SQL table.
	 *
	 * hashed on keyword and fields include:
	 *
	 *                [keyword]     Setting
	 *                [value]       Value
	 *                [defaultval]  Default value
	 *                [type]        Type of setting, used defines above
	 *                [name]        Friendly Short Description
	 *                [description] Long description for tooltip
	 *                [category]    Category description of setting
	 *                [module]      Module setting belongs to, optional
	 *                [level]       Level of setting
	 *                [options]     select options, or validation options
	 *                [emptyok]     boolean if value can be blank
	 *                [readonly]    boolean for readonly
	 *                [hidden]      boolean for hidden fields
	 *                [sortorder]   'primary' sort key for presentation
	 */
	private $db_conf_store;

	/**
	 * simple key => value store for settings. Also augmented with boostrap settings
	 * if provided which are not included in db_conf_store.
	 * Note: this is referenced in modulefunctions.class.php in _ampconf_string_replace
	 * so it needs to remain public
	 */
	public $conf = array();

	/**
	 * legacy $asterisk_conf that we need to obsolete
	 */
	private $asterisk_conf = array();

	/**
	 * This will be set with any update/define to provide feedback that can be optionally
	 * used inside or outside of the class. The structure should be:
	 * $last_update_status[$keyword]['validated']   true/false
	 * $last_update_status[$keyword]['saved']       true/false
	 * $last_update_status[$keyword]['orig_value']  value submitted
	 * $last_update_status[$keyword]['saved_value'] value submitted
	 * $last_update_status[$keyword]['msg']         error message
	 */
	private $last_update_status;

	/**
	 * Internal reference pointer to the internal $last_update_status[$keyword]
	 * e.g. $this->_last_update_status =& $last_update_status[$keyword];
	 */
	private $_last_update_status;

	// TODO: move to static var in method?
	/**
	 * internal tracker used by parse_amportal_conf
	 */
	private $parsed_from_db = false;

	/**
	 * status of the amportal.conf file passed in and if it can be written to
	 */
	private $amportal_canwrite;

	/**
	 * Depreciated settings that will be forced to output this value
	 * @type {array}
	 */
	private $depreciatedSettings = array(
		"USEDEVSTATE" => 1,
		"USEQUEUESTATE" => 1,
		"ALWAYS_SHOW_DEVICE_DETAILS" => 1,
		"AST_FUNC_DEVICE_STATE" => "DEVICE_STATE",
		"AST_FUNC_EXTENSION_STATE" => "EXTENSION_STATE",
		"AST_FUNC_PRESENCE_STATE" => "PRESENCE_STATE",
		"AST_FUNC_SHARED" => "SHARED",
		"AST_FUNC_CONNECTEDLINE" => "CONNECTEDLINE",
		"AST_FUNC_MASTER_CHANNEL" => "MASTER_CHANNEL"
		/* "DYNAMICHINTS" => 0 */
	);

	/**
	 * freepbx_conf constructor
	 * The class when initialized is filled populated from the SQL store
	 * along with some level of validation in case corrupted data has
	 * been put into the store form outside sources. It does not write back
	 * upon detecting corrupted data though.
	 *
	 * Along with populating the db_conf_store hash, it also populates the
	 * key => value conf hash by reference so that changes to db_conf_store
	 * will be reflected. (Since $amp_conf should be assigned as a reference
	 * to the conf hash).
	 */
	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception("Need to be instantiated with a FreePBX Object");
		}
		// For translation (need to be in english in the DB, translated when pulled out
		// TODO: is there a better place to put these like in install script?
		//
		if (false) {
			_('No Description Provided');
			_('Undefined Category');
		}

		$this->freepbx = $freepbx;
		$this->db = $freepbx->Database;

		$sql = 'SELECT s.keyword, s.*  FROM freepbx_settings as s ORDER BY category, sortorder, name';
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$db_raw = $sth->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
		array_walk($db_raw, function(&$val, $key){
			$val = $val[0];
		});

		unset($this->last_update_status);
		foreach($db_raw as $keyword => $setting) {
			$this->last_update_status[$keyword]['validated'] = false;
			$this->last_update_status[$keyword]['saved'] = false;
			$this->last_update_status[$keyword]['orig_value'] = $setting['value'];
			$this->last_update_status[$keyword]['saved_value'] = $setting['value'];
			$this->last_update_status[$keyword]['msg'] = '';
			$this->_last_update_status =& $this->last_update_status[$keyword];

			$this->db_conf_store[$keyword] = $setting;
			$this->db_conf_store[$keyword]['modified'] = false;
			// setup the conf array also
			// note the reference assignment, if it's actually the authoritative source
			$this->conf[$keyword] =& $this->db_conf_store[$keyword]['value'];

			// The assumption is that the database settings were validated on input. We are not going to throw errors when
			// reading them back but the last_update_status array is available for debugging purposes to review.
			//
			if (!$setting['emptyok'] && $setting['value'] == '') {
				$this->db_conf_store[$keyword]['value'] = $this->prepare_conf_value($setting['defaultval'], $setting['type'], $setting['emptyok'], $setting['options']);
			} else {
				$this->db_conf_store[$keyword]['value'] = $this->prepare_conf_value($setting['value'], $setting['type'], $setting['emptyok'], $setting['options']);
			}
		}
		unset($db_raw);

		$dp = array();
		foreach($this->depreciatedSettings as $keyword => $value) {
			if(isset($this->db_conf_store[$keyword])) {
				$dp[] = $keyword;
			} else {
				$this->db_conf_store[$keyword] = array(
					"value" => $value,
					"hidden" => true
				);
			}
			$this->conf[$keyword] = $value;
		}
		if(!empty($dp)) {
			$this->remove_conf_settings($dp);
		}
	}

	public function exists($keyword) {
		return $this->conf_setting_exists($keyword);
	}

	public function set($keyword, $value, $commit=true, $override_readonly=true) {
		return $this->update($keyword, $value, $commit, $override_readonly);
	}

	public function update($keyword, $value, $commit=true, $override_readonly=true) {
		return $this->set_conf_values(array($keyword => $value), $commit, $override_readonly);
	}

	public function get($keyword, $passthru=false) {
		return $this->get_conf_setting($keyword, $passthru);
	}

	public function conf_setting($keyword) {
		return !empty($this->db_conf_store[$keyword]) ? $this->db_conf_store[$keyword] : array();
	}

	/**
	 * Generate an amportal.conf file from the db_conf_store settings loaded.
	 *
	 * @param bool    true if a verbose file should be written that includes some documentation.
	 * @return string returns the amportal.conf text that can be written out to a file.
	 */
	public function amportal_generate($verbose=true) {
		// purposely lcoalized the '---------' lines, if someone translates this, theymay want to keep it 'neat'
		// Only localize text, not special characters, and dont add the end ";" as localized text can be of any length
		$conf_string  = "#;--------------------------------------------------------------------------------\n";
		$conf_string .= "#; ";
		$conf_string .= _("Do NOT edit this file as it is auto-generated by FreePBX. All modifications to");
		$conf_string .= "\n#; ";
		$conf_string .= _("this file must be done via the Web GUI. This file is IGNORED BY FreePBX");
		$conf_string .= "\n#; ";
		$conf_string .= _("The purpose of this file is to allow legacy applications to still function by just reading ampportal.conf");
		$conf_string .= "\n#; ";
		$conf_string .= "#;--------------------------------------------------------------------------------\n\n\n";
		$conf_string .= "#;--------------------------------------------------------------------------------\n#; ";
		$conf_string .= _("All settings can be set from the Advanced Settings page accessible in FreePBX");
		$conf_string .=  "\n#;--------------------------------------------------------------------------------\n\n\n\n";
		$comments = '';

		// Note, No localization of the name field, this is a conf file! DON'T MESS WITH THIS!
		$category = '';
		foreach ($this->conf as $keyword => $value) {
			if(isset($this->depreciatedSettings[$keyword])) {
				$default_val = $this->depreciatedSettings[$keyword];
				$this_val    = $this->depreciatedSettings[$keyword];
			} elseif ($this->conf_setting_exists($keyword)) {
				if ($this->db_conf_store[$keyword]['hidden']) {
					continue;
				}
				if ($this->db_conf_store[$keyword]['type'] == CONF_TYPE_BOOL) {
					$default_val = $this->db_conf_store[$keyword]['defaultval'] ? 'TRUE' : 'FALSE';
					$this_val    = $value ? 'TRUE' : 'FALSE';
				} else {
					$default_val = $this->db_conf_store[$keyword]['defaultval'];
					$this_val    = $value;
				}
			} else {
				$this_val = $value;
			}
			if ($verbose) {
				if(isset($this->depreciatedSettings[$keyword])) {
					$comments = "#\n# --- DEPRECIATED SETTINGS ---\n#\n\n";
					$default_val = $this->depreciatedSettings[$keyword];
					$this_val    = $this->depreciatedSettings[$keyword];
				} elseif ($this->conf_setting_exists($keyword)) {
					$comments = '';
					if ($this->db_conf_store[$keyword]['category'] != $category) {
						$category = $this->db_conf_store[$keyword]['category'];
						$comments = "#\n# --- CATEGORY: $category ---\n#\n\n";
					}
					$comments .= "# " . $this->db_conf_store[$keyword]['name'] . "\n";
					//avoid newline issues
					$default_val = str_replace(array("\r", "\n", "\r\n"), "\\n", $default_val);
					$comments .= "# Default Value: $default_val\n";
				} else {
					$comments = "#\n";
					if ($category != 'Bootstrapped or Legacy Settings') {
						$category = 'Bootstrapped or Legacy Settings';
						$comments = "#\n# --- CATEGORY: $category ---\n#\n\n#\n";
					}
				}
			}
			$this_val = str_replace(' ','\ ',$this_val);
			$default_val = str_replace(array("\r", "\n", "\r\n"), "\\n", $default_val);
			$conf_string .= $comments . "$keyword=$this_val\n\n";
		}
		return $conf_string;
	}

	public function get_asterisk_conf() {
		//deprecated
	}

	public function amportal_canwrite() {
		if(file_exists('/etc/amportal.conf')) {
			return is_writeable('/etc/amportal.conf');
		} else {
			return is_writable('/etc');
		}
	}

	/**
	 * Parse AMPORTAL.conf file
	 *
	 * Legacy, dont really parse it, its read only
	 *
	 * @param string $filename
	 * @param array $bootstrap_conf
	 * @param boolean $file_is_authority
	 * @return void
	 */
	public function parse_amportal_conf($filename, $bootstrap_conf = array(), $file_is_authority=false) {
		//Load runtime settings into returned array
		$valid = [
			'AMPDBUSER',
			'AMPDBPASS',
			'AMPDBHOST',
			'AMPDBNAME',
			'AMPDBENGINE',
			'AMPDBSOCK',
			'AMPDBPORT',
			'datasource'
		];
		foreach($bootstrap_conf as $keyword => $value) {
			if(!in_array($keyword,$valid)) {
				continue;
			}
			$this->conf[$keyword] = $value;
		}
		//dont load deprecicated settings into memory though
		$final = $this->conf;
		foreach($this->depreciatedSettings as $keyword => $value) {
			$final[$keyword] = $value;
		}
		return $final;
	}

	/**
	 * Returns a hash of the full $db_conf_store, getter for that object.
	 *
	 * @return array   a copy of the db_conf_store
	 */
	public function get_conf_settings() {
		$db_conf_store = $this->db_conf_store;
		foreach ($db_conf_store as $k => $s) {
			if (isset($s['type']) && $s['type'] == CONF_TYPE_FSELECT) {
				$db_conf_store[$k]['options'] = unserialize($s['options']);
			}
		}
		return $db_conf_store;
	}

	/**
	 * Determines if a setting exists in the configuration database.
	 *
	 * @return bool   True if the setting exists.
	 */
	public function conf_setting_exists($keyword) {
		return isset($this->depreciatedSettings[$keyword]) || isset($this->db_conf_store[$keyword]);
	}

	/**
	 * Get's the current value of a configuration setting from the database store.
	 *
	 * @param string  The setting to fetch.
	 * @param boolean Optional forces the actual database variable to be fetched
	 * @return mixed  returns the value of the setting, or boolean false if the
	 *                setting does not exist. Since configuration booleans are
	 *                returned as '0' and '1', they can be differentiated by a
	 *                true boolean false (use === operator) if a setting does
	 *                not exist.
	 */
	public function get_conf_setting($keyword, $passthru=false) {
		if(isset($this->depreciatedSettings[$keyword])) {
			return $this->depreciatedSettings[$keyword];
		}
		if($keyword == "FPBXOPMODE" && is_object($_SESSION['AMP_user']) && method_exists($_SESSION['AMP_user'], "getOpMode")) {
			switch($_SESSION['AMP_user']->getOpMode()) {
				case "basic":
					return "basic";
				break;
				case "advanced":
					return "advanced";
				break;
				//passthru if not valid
			}
		}
		if ($passthru) {
			// This is a special case situation, do I need to confirm if the setting
			// actually exists so I can return a boolean false if not?
			//

			$sql = "SELECT `value` FROM freepbx_settings WHERE `keyword` = :keyword";
			$sth = $this->db->prepare($sql);
			$sth->execute(array("keyword" => $keyword));
			$value = $sth->fetchColumn();
			if (isset($this->db_conf_store[$keyword])) {
				$this->db_conf_store[$keyword]['value'] = $value;
			}
			return $value;
		} elseif (isset($this->db_conf_store[$keyword])) {
			return $this->db_conf_store[$keyword]['value'];
		} else {
			return false;
		}
	}

		/** Get's the default value of a configuration setting from the database store.
	 *
	 * @param string  The setting to fetch.
	 * @return mixed  returns the default of the setting, or boolean false if the
	 *                setting does not exist. Since configuration booleans are
	 *                returned as '0' and '1', they can be differentiated by a
	 *                true boolean false (use === operator) if a setting does
	 *                not exist.
	 */
	public function get_conf_default_setting($keyword) {
		if(isset($this->depreciatedSettings[$keyword])) {
			return $this->depreciatedSettings[$keyword];
		}
		if (isset($this->db_conf_store[$keyword])) {
			return $this->db_conf_store[$keyword]['defaultval'];
		} else {
			return false;
		}
	}

	/** Reset all conf settings specified int the passed in array to their defaults.
	 *
	 * @param array   An array of the settings that should be reset.
	 * @param array   Boolean set to true if the db_conf_store should be commited to
	 *                the database after reseting it.
	 * @return int    returns the number of settings that differed from the current
	 *                values.
	 */
	public function reset_conf_settings($settings, $commit=false) {
		$update_arr = array();
		foreach ($settings as $keyword) {
			$update_arr[$keyword] = $this->db_conf_store[$keyword]['defaultval'];
		}
		return $this->set_conf_values($update_arr,$commit,true);
	}

	/** Set's configuration store values with an option to commit and an option to
	 * override readonly settings.
	 *
	 * @param array   A hash of key/value settings to update.
	 * @param bool    Boolean set to true if the db_conf_store should be commited to
	 *                the database after reseting it.
	 * @param bool    Boolean set to true if readonly settings should be allowed
	 *                to be changed.
	 * @return int    returns the number of settings that differed from the current
	 *                values and are marked dirty unless written out.
	 */
	public function set_conf_values($update_arr, $commit=false, $override_readonly=false) {
		global $amp_conf;
		$cnt = 0;
		if (!is_array($update_arr)) {
			die_freepbx(_("called set_conf_values with a non-array"));
		}
		unset($this->last_update_status);
		foreach($update_arr as $keyword => $value) {
			if(isset($this->depreciatedSettings[$keyword])) {
				continue;
			}
			if (!isset($this->db_conf_store[$keyword])) {
				die_freepbx(sprintf(_("trying to set keyword [%s] to [%s] on uninitialized setting"),$keyword, $value));
			}
			$this->last_update_status[$keyword]['validated'] = false;
			$this->last_update_status[$keyword]['saved'] = false;
			$this->last_update_status[$keyword]['orig_value'] = $value;
			$this->last_update_status[$keyword]['saved_value'] = $value;
			$this->last_update_status[$keyword]['msg'] = '';
			$this->_last_update_status =& $this->last_update_status[$keyword];

			$prep_value = $this->prepare_conf_value($value, $this->db_conf_store[$keyword]['type'], $this->db_conf_store[$keyword]['emptyok'], $this->db_conf_store[$keyword]['options']);

			// If we reported saved then even if we didn't validate, we still were able to rectify
			// it into something and therefore will use it. For example, if we set an integer out of
			// range then we will still save the value. If the calling function wants to be strict
			// they can not supply the commit flag and check the validation status and not save/commit
			// the value based on their own decision criteria.
			//
			if ($this->_last_update_status['saved']
				&& $prep_value != $this->db_conf_store[$keyword]['value']
				&& ($prep_value !== '' || $this->db_conf_store[$keyword]['emptyok'])
				&& ($override_readonly || !$this->db_conf_store[$keyword]['readonly'])) {

				$this->db_conf_store[$keyword]['value'] = $prep_value;
				$this->db_conf_store[$keyword]['modified'] = true;
				$cnt++;
			}

			// Make sure it get's update in amp_conf
			//
			$amp_conf[$keyword] = $prep_value;
			// Process some specific keywords that require further actions
			//
			$this->setting_change_special($keyword, $prep_value);

		}
		if ($commit) {
			$this->commit_conf_settings();
		}
		return $cnt;
	}

	/**
	 * Get's the results of the last update and can be used to get errors,
	 * values if settings were altered from validation, etc.
	 *
	 * @return array  returns the last_update_status hash
	 */
	public function get_last_update_status() {
		return $this->last_update_status;
	}

	// TODO should I remove (or ignore) need for value. Or should I provide the option
	//      of setting the current and default values different as there are some migration
	//      scenarios that would support this?
	/**
	 * used to insert or update an existing setting such as in an install
	 * script. $vars will include some required fields and we are strict
	 * with a die_freebpx() if they are missing.
	 *
	 * the value parameter will not be altered in memory or in the database if
	 * the setting has already been defined, but most of the other settings can
	 * be changed with the exception of the type setting which must be the same
	 * once created, or you must remove the setting entirely if the type is to
	 * be changed.
	 *
	 * @param string  the setting keyword
	 * @param array   a parameter array with all the settings
	 *                [value]       required, value of the setting
	 *                [name]        required, Friendly Short Description
	 *                [level]       optional, default 0, level of setting
	 *                [description] required, long description for tooltip
	 *                [type]        required, type of setting
	 *                [options]     conditional, required for selects, optional
	 *                              for others. For INT a 2 place array
	 *                              indicates the allowed range, for others
	 *                              it is a REGEX validation, for BOOL, nothing
	 *                [emptyok]     optional, default true, if setting can be blank
	 *                [defaultval]  required and same as value
	 *                [readonly]    optional, default false, if readonly
	 *                [hidden]      optional, default false, if hidden
	 *                [category]    required, category of the setting
	 *                [module]      optional, module name that owns the setting
	 *                              and if the setting should only exist when
	 *                              the module is installed. If set, uninstalling
	 *                              the module will automatically remove this.
	 *                [sortorder]   'primary' sort order key for presentation
	 * @param bool    set to true if a commit back to the database should be done
	 */
	public function define_conf_setting($keyword,$vars,$commit=false) {
		global $amp_conf;
		if(isset($this->depreciatedSettings[$keyword])) {
			return true;
		}

		unset($this->last_update_status);
		$this->last_update_status[$keyword]['validated'] = false;
		$this->last_update_status[$keyword]['saved'] = false;
		$this->last_update_status[$keyword]['orig_value'] = $vars['value'];
		$this->last_update_status[$keyword]['saved_value'] = $vars['value'];
		$this->last_update_status[$keyword]['msg'] = '';

		$this->_last_update_status =& $this->last_update_status[$keyword];

		$attributes = array(
			'keyword' => '',
			'value' => '',
			'name' => '',
			'level' => 0,
			'description' => 'No Description Provided', // Don't gettext this
			'type' => '',
			'options' => '',
			'defaultval' => '',
			'readonly' => 0,
			'hidden' => 0,
			'category' => 'Undefined Category', // Don't gettext this
			'module' => '',
			'emptyok' => 1,
			'sortorder' => 0,
			'modified' => false, // set to false to compare against existing array
			);
		// Got to have a type and value, if no type, _prepared_conf_value will throw an error
		$new_setting = !isset($this->db_conf_store[$keyword]);
		// If not a new setting, default appropriate values that have not been set for us
		//
		if (!$new_setting) {
			if (!isset($vars['defaultval'])) {
				$vars['defaultval'] = $this->db_conf_store[$keyword]['defaultval'];
			}
			if (!isset($vars['name'])) {
				$vars['name'] = $this->db_conf_store[$keyword]['name'];
			}
			if (!isset($vars['level'])) {
				$vars['level'] = $this->db_conf_store[$keyword]['level'];
			}
			if (!isset($vars['type'])) {
				$vars['type'] = $this->db_conf_store[$keyword]['type'];
			}
			if (!isset($vars['description'])) {
				$vars['description'] = $this->db_conf_store[$keyword]['description'];
			}
			if (!isset($vars['options'])) {
				$vars['options'] = $this->db_conf_store[$keyword]['options'];
			}
			if (!isset($vars['readonly'])) {
				$vars['readonly'] = $this->db_conf_store[$keyword]['readonly'];
			}
			if (!isset($vars['hidden'])) {
				$vars['hidden'] = $this->db_conf_store[$keyword]['hidden'];
			}
			if (!isset($vars['category'])) {
				$vars['category'] = $this->db_conf_store[$keyword]['category'];
			}
			if (!isset($vars['sortorder'])) {
				$vars['sortorder'] = $this->db_conf_store[$keyword]['sortorder'];
			}
		}
		if (!$new_setting && $vars['type'] != $this->db_conf_store[$keyword]['type']) {
			die_freepbx(sprintf(_("you can't convert an existing type, keyword [%s]"),$keyword));
		}
		if (!isset($vars['value']) || !isset($vars['defaultval'])) {
			die_freepbx(sprintf(_("missing value and/or defaultval required for [%s]"),$keyword));
		} else {
			$attributes['keyword'] = $keyword;
			$attributes['type'] = $vars['type'];
		}
		switch ($vars['type']) {
		case CONF_TYPE_CSELECT:
		case CONF_TYPE_SELECT:
			if (!isset($vars['options']) || $vars['options'] == '') {
				die_freepbx(sprintf(_("missing options for keyword [%s] required if type is select"),$keyword));
			} else {
				$opt_array =  is_array($vars['options']) ? $vars['options'] : explode(',',$vars['options']);
				foreach($opt_array as $av) {
					$trim_options[] = trim($av);
				}
				$attributes['options'] = implode(',',$trim_options);
				unset($opt_array);
				unset($trim_options);
			}
		break;
		case CONF_TYPE_FSELECT:
			if (!isset($vars['options']) || !is_array($vars['options'])) {
				die_freepbx(sprintf(_("missing options array for keyword [%s] required if type is select"),$keyword));
			} else {
				$attributes['options'] = serialize($vars['options']);
			}
		break;
		case CONF_TYPE_INT:
			if (isset($vars['options']) && $vars['options'] != '') {
				$validate_options = !is_array($vars['options']) ? explode(',',$vars['options']) : $vars['options'];
				if (count($validate_options) != 2 || !is_numeric($validate_options[0]) || !is_numeric($validate_options[1])) {
					die_freepbx(sprintf(_("invalid validation options provided for keyword %s: %s"),$keyword,implode(',',$validate_options)));
				} else {
					$attributes['options'] = (int) $validate_options[0] . ',' . (int) $validate_options[1];
				}
			}
		break;
		case CONF_TYPE_TEXTAREA:
		case CONF_TYPE_TEXT:
		case CONF_TYPE_DIR:
			if (isset($vars['options'])) {
				$attributes['options'] = $vars['options'];
			}
		break;
		}

		if (isset($vars['level'])) {
			$attributes['level'] = (int) $vars['level'] > 0 ? ((int) $vars['level'] < 10 ? (int) $vars['level'] : 10) : 0;
		}
		if (isset($vars['category']) && $vars['category']) {
			$attributes['category'] = $vars['category'];
		}
		$optional = array('readonly', 'hidden', 'emptyok');
		foreach ($optional as $atrib) {
			if (isset($vars[$atrib])) {
				$attributes[$atrib] = $vars[$atrib] ? '1' : '0';
			}
		}
		$optional = array('name', 'description', 'module', 'sortorder');
		foreach ($optional as $atrib) {
			if (isset($vars[$atrib])) {
				$attributes[$atrib] = $vars[$atrib];
			}
		}
		if ($attributes['name'] == '') {
			$attributes['name'] = $attributes['keyword'];
		}

		// validate even if already set, catches coding errors early even though we don't use it
		$value = $this->prepare_conf_value($vars['value'], $vars['type'] ,$attributes['emptyok'], $attributes['options']);
		$attributes['value'] = $new_setting ? $value : $this->db_conf_store[$keyword];
		$attributes['defaultval'] = $this->prepare_conf_value($vars['defaultval'], $vars['type'] ,$attributes['emptyok'], $attributes['options']);

		// Let's be really stict here, if anything violated validation, we fail!
		// This method is only called to define new settings, this catches programming errors early on.
		//
		if (!$this->_last_update_status['validated']) {
			die_freepbx(
				sprintf(_("method define_conf_setting() failed to pass validation for keyword [%s] setting value [%s], error msg if supplied [%s]"),
				$keyword, $vars['value'], $this->_last_update_status['msg'])
			);
		}

		if ($new_setting || $attributes != $this->db_conf_store[$keyword]) {
			if (!$new_setting) {
				if(isset($attributes['keyword'])) {
					unset($attributes['keyword']);
				}
				if(isset($attributes['value'])) {
					unset($attributes['value']);
				}
				if(isset($attributes['type'])) {
					unset($attributes['type']);
				}
				if(isset($attributes['modified'])) {
					unset($attributes['modified']);
				}
			}
			foreach ($attributes as $key => $val) {
				$this->db_conf_store[$keyword][$key] = $val;
			}
			if ($new_setting) {
				$this->conf[$keyword] =& $this->db_conf_store[$keyword]['value'];
				$amp_conf[$keyword] =& $this->conf[$keyword];
			}
			$this->db_conf_store[$keyword]['modified'] = true;
		}
		if ($commit) {
			$this->commit_conf_settings();
		}
	}


	/**
	 * Removes a set of settings from the db_conf_store, used in functions like
	 * uninstall scripts if settings are no longer needed.
	 *
	 * @param  array  array of settings to be removed
	 */
	public function remove_conf_settings($settings) {
		global $amp_conf;
		if (!is_array($settings)) {
			$settings = array($settings);
		}
		$sql = "DELETE FROM freepbx_settings WHERE keyword = :keyword";
		$sth = $this->db->prepare($sql);
		foreach ($settings as $setting) {
			if (isset($this->db_conf_store[$setting]) ) {
				unset($this->db_conf_store[$setting]);
			}
			if (isset($this->conf[$setting])) {
				unset($this->conf[$setting]);
			}
			//for legacy sakes
			if (isset($amp_conf[$setting])) {
				unset($amp_conf[$setting]);
			}
			$sth->execute(array(":keyword" => $setting));
			$this->removeSetting($setting);
		}
	}

	/**
	 * Commit back to database all in memory settings that have been marked as modified.
	 *
	 * @return int    The number of modified settings it committed back.
	 */
	public function commit_conf_settings() {
		$update_array = array();
		if(empty($this->db_conf_store)) {
			return 0;
		}

		foreach ($this->db_conf_store as $keyword => $atrib) {

			// This hasn't been changed, no need to update
			if (!isset($atrib['modified']) || !$atrib['modified']) {
				continue;
			}

			$update_array[] = array(
				":keyword" => $keyword,
				":value" => $atrib['value'],
				":name" => $atrib['name'],
				":level" => $atrib['level'],
				":description" => $atrib['description'],
				":type" => $atrib['type'],
				":options" => $atrib['options'],
				":defaultval" => $atrib['defaultval'],
				":readonly" => $atrib['readonly'],
				":hidden" => $atrib['hidden'],
				":category" => $atrib['category'],
				":module" => $atrib['module'],
				":emptyok" => $atrib['emptyok'],
				":sortorder" => $atrib['sortorder'],
			);
			unset($this->db_conf_store[$keyword]['modified']);
		}
		if ($update_array) {
			$sql = "INSERT INTO `freepbx_settings` (keyword, value, name, level, description, type, options,
					defaultval, readonly, hidden, category, module, emptyok, sortorder) VALUES (:keyword, :value, :name,
					:level, :description, :type, :options, :defaultval, :readonly, :hidden, :category, :module, :emptyok, :sortorder)
					ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), `name`=VALUES(`name`),
					`level`=VALUES(`level`), `description`=VALUES(`description`), `type`=VALUES(`type`),
					`options`=VALUES(`options`), `defaultval`=VALUES(`defaultval`), `readonly`=VALUES(`readonly`),
					`hidden`=VALUES(`hidden`), `category`=VALUES(`category`), `module`=VALUES(`module`),
					`emptyok`=VALUES(`emptyok`), `sortorder`=VALUES(`sortorder`)";
			$sth = $this->db->prepare($sql);
			foreach ($update_array as $row) {
				$sth->execute($row);
				$this->updateSetting($row[':keyword'], $row[':value']);
			}
		}
		return count($update_array);
	}

	/**
	 * Remove all settings with the indicated module ownership, used
	 * during functions like uninstalling modules.
	 *
	 * @param  array  array of settings to be removed
	 */
	public function remove_module_settings($module) {
		$sql = "SELECT `keyword` FROM freepbx_settings WHERE module = :module";
		$sth = $this->db->prepare($sql);
		$sth->execute(array(":module" => $module));
		$settings = $sth->fetchAll(\PDO::FETCH_COLUMN);
		if(!empty($settings)) {
			$this->remove_conf_settings($settings);
		}
	}

	/**
	 * Exact same as remove_conf_setting() method, either can be
	 * used since they both detect a single or multiple settings.
	 *
	 * @param  array  array of settings to be removed
	 */
	public function remove_conf_setting($setting) {
		return $this->remove_conf_settings($setting);
	}

	/**
	 * Reset all the db_conf_store settings to their defaults and
	 * optionally commit them back to the database.
	 *
	 * @param bool    Resets all the settings to their default values.
	 */
	public function reset_all_conf_settings($commit=false) {
		$update_arr = array();
		foreach ($this->db_conf_store as $keyword => $atribs) {
			if (!$atribs['hidden']) {
				$update_arr[$keyword] = $atribs['defaultval'];
			}
			return $this->set_conf_values($update_arr,$commit,true);
		}
	}

	/**
	 * Prepares a value to be inserted into the configuration settings using the
	 * type information and any provided validation rules. Integers that are out
	 * of range will be set to the lowest or highest values. Validation issues
	 * are recorded and can be examined with the get_last_update_status() method.
	 *
	 * @param mixed   integer, string or boolean to be prepared
	 * @param type    the type being validated
	 * @param bool    emptyok attribute of this setting
	 * @param mixed   options string or array used for validating the type
	 *
	 * @return string value to be inserted into the store
	 *                last_update_status is updated with any relevant issues
	 */
	private function prepare_conf_value($value, $type, $emptyok, $options = false) {
		switch ($type) {

		case self::CONF_TYPE_BOOL:
			$ret = $value ? 1 : 0;
			$this->_last_update_status['validated'] = true;
			break;

		case self::CONF_TYPE_SELECT:
			$val_arr = explode(',',$options);
			if (in_array($value,$val_arr)) {
				$ret = $value;
				$this->_last_update_status['validated'] = true;
			} else {
				$ret = null;
				$this->_last_update_status['validated'] = false;
				$this->_last_update_status['msg'] = _("Invalid value supplied to select");
				$this->_last_update_status['saved_value'] = $ret;
				$this->_last_update_status['saved'] = false;
				//
				// NOTE: returning from function early!
				return $ret;
			}
			break;

		case self::CONF_TYPE_FSELECT:
			if (!is_array($options)) {
				$options = unserialize($options);
			}
			if (array_key_exists($value, $options)) {
				$ret = $value;
				$this->_last_update_status['validated'] = true;
			} else {
				$ret = null;
				$this->_last_update_status['validated'] = false;
				$this->_last_update_status['msg'] = _("Invalid value supplied to select");
				$this->_last_update_status['saved_value'] = $ret;
				$this->_last_update_status['saved'] = false;
				//
				// NOTE: returning from function early!
				return $ret;
			}
		break;
		case self::CONF_TYPE_CSELECT:
			if ($value == '' && !$emptyok) {
				$this->_last_update_status['validated'] = false;
				$this->_last_update_status['msg'] = _("Empty value not allowed for this field");
			} else {
				$ret = $value;
				$this->_last_update_status['validated'] = true;
			}
		break;
		case self::CONF_TYPE_DIR:
			// we don't consider trailing '/' in a directory an error for validation purposes
			$value = rtrim($value,'/');
			// NOTE: fallthrough to CONF_TYPE_TEXT, NO break on purpose!
			//       |
			//       |
			//       V
		case self::CONF_TYPE_TEXT:
		case self::CONF_TYPE_TEXTAREA:
			if ($value == '' && !$emptyok) {
				$this->_last_update_status['validated'] = false;
				$this->_last_update_status['msg'] = _("Empty value not allowed for this field");
			} else if ($options != '' && $value != '') {
				if (preg_match($options,$value)) {
					$ret = $value;
					$this->_last_update_status['validated'] = true;
				} else {
					$ret = null;
					$this->_last_update_status['validated'] = false;
					$this->_last_update_status['msg'] = sprintf(_("Invalid value supplied violates the validation regex: %s"),$options);
					$this->_last_update_status['saved_value'] = $ret;
					$this->_last_update_status['saved'] = false;
					//
					// NOTE: returning from function early!
					return $ret;
				}
			} else {
				$ret = $value;
				$this->_last_update_status['validated'] = true;
			}
			//if cli then dont echo out newlines its confusing
			if(php_sapi_name() === 'cli' && $type === CONF_TYPE_TEXTAREA) {
				//$ret = str_replace(array("\r", "\n", "\r\n"), ",", $ret);
			}
			break;

		case self::CONF_TYPE_INT:
			$ret = !is_numeric($value) && $value != '' ? '' : $value;
			$ret = $emptyok && (string) trim($ret) === '' ? '' : (int) $ret;

			if ($options != '' && (string) $ret !== '') {
				$range = is_array($options) ? $options : explode(',',$options);
				switch (true) {
				case $ret < $range[0]:
					$ret = $range[0];
					$this->_last_update_status['validated'] = false;
					$this->_last_update_status['msg'] = sprintf(_("Value [%s] out of range, changed to [%s]"),$value,$ret);
				break;
				case $ret > $range[1]:
					$ret = $range[1];
					$this->_last_update_status['validated'] = false;
					$this->_last_update_status['msg'] = sprintf(_("Value [%s] out of range, changed to [%s]"),$value,$ret);
				break;
				default:
					$this->_last_update_status['validated'] = (string) $ret === (string) $value;
				break;
				}
			} else {
				$this->_last_update_status['validated'] = (string) $ret === (string) $value;
			}
			break;

		default:
			$this->_last_update_status['validated'] = false;
			freepbx_log(FPBX_LOG_ERROR,sprintf(_("unknown type: [%s]"),$type));
			break;
		}
		$this->_last_update_status['saved_value'] = $ret;
		$this->_last_update_status['saved'] = true;
		return $ret;
	}

	/**
	 * Deal with corner case Settings that change and need further actions
	 *
	 * Some settings require further actions when they change. Any time we set, reset,
	 * etc the settings we should call this function.
	 *
	 * @param string $keyword the setting that needs to be addressed
	 * @param string $value the new value for the setting that was just changed
	 *
	 * @return null
	 */
	private function setting_change_special($keyword, $prep_value) {
		switch ($keyword) {
			case 'AMPMGRPASS':
				fpbx_ami_update(false, true);
			break;
			case 'AMPMGRUSER':
				fpbx_ami_update(true, false);
			break;
			case 'ASTMGRWRITETIMEOUT':
				fpbx_ami_update(false, false, true);
			break;
			default:
			break;
		}
	}

	/**
	 * Process Hook call for updating Settings
	 *
	 * @param string $keyword
	 * @param mixed $value
	 * @return void
	 */
	private function updateSetting($keyword, $value) {
		$this->freepbx->Hooks->processHooks($keyword, $value);
	}

	/**
	 * Process Hook call for removing Settings
	 *
	 * @param string $keyword
	 * @return void
	 */
	private function removeSetting($keyword) {
		$this->freepbx->Hooks->processHooks($keyword);
	}
}
