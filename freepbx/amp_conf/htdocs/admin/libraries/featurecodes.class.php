<?php
class featurecode {
	var $_modulename;	// Module name
	var $_featurename;	// Feature name
	var $_description;	// Description (i.e. what the user will see)
	var $_defaultcode;	// Default code if user doesn't pick one
	var $_customcode;	// Custom code
	var $_enabled;		// Enabled/Disabled (0=disabled; 1=enabled; -1=unknown)
	var $_providedest;	// 1=provide a featurecode destination for this code to modules
	var $_loaded;		// If this feature code was succesfully loaded from the DB
	var $_overridecodes;// Overide defaults from featurecodes.conf
	var $_helptext = '';//Help Text for popup bubbles, set to nothing because the table doesnt accept NULLs
	var $_depend = '';	// Dependencies for this feature cod

	private $db;

	/**
	 * Define a feature code to add or update
	 *
	 * @param string $modulename rawname of module
	 * @param string $featurename Unique Name for this feature code
	 */
	function __construct($modulename, $featurename) {
		global $amp_conf;

		if ($modulename == '' || $featurename == '')
			die_freepbx('feature code class must be called with ModuleName and FeatureName');

		$fd = $amp_conf['ASTETCDIR'].'/freepbx_featurecodes.conf';
		$this->_overridecodes = array();
		if (file_exists($fd)) {
			$this->_overridecodes = parse_ini_file($fd,true);
		}

		$this->_modulename = $modulename;
		$this->_featurename = $featurename;
		$this->_enabled = -1;  // -1 means not initialised
		$this->_providedest = 0;  // no destination by default
		$this->_loaded = false;

		$this->db = \FreePBX::Database();
	}

	/**
	 * Checks to see if the function $this->init has been run or not
	 *
	 * @return bool true if yes or false if not yes
	 */
	function isReady() {
		return (!($this->_enabled == -1));
	}

	/**
	 * Reads from the database of featurecodes
	 *
	 * @param int $opt 0 -- called by user code (i.e. outside this class), 1 -- called automatically by this class,  2 -- called by user code, run even if called once already
	 * @return bool true if good data, false if we want to cry
	 */
	function init($opt = 0) {
		if ($this->isReady()) {
			if ($opt < 2)
				die_freepbx('FeatureCode: init already called!');
		}

		$sql = "SELECT `description`, `defaultcode`, `customcode`, `enabled`, `providedest`, `depend` FROM `featurecodes` WHERE `modulename` = ? AND `featurename` = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($this->_modulename, $this->_featurename));
		$res = $sth->fetch(\PDO::FETCH_ASSOC);

		if (is_array($res)) { // found something, read it
			$this->_description = $res['description'];

			if (isset($this->_overridecodes[$this->_modulename][$this->_featurename]) && trim($this->_overridecodes[$this->_modulename][$this->_featurename]) != '') {
				$this->_defaultcode = $this->_overridecodes[$this->_modulename][$this->_featurename];
				if ($this->_defaultcode != $res['defaultcode'])
				{
					$sql = 'UPDATE `featurecodes` SET `defaultcode` = ? WHERE `modulename` = ? AND `featurename` = ?';
					$sth = $this->db->prepare($sql);
					$sth->execute(array($this->_defaultcode, $this->_modulename, $this->_featurename));
				}
			} else {
				$this->_defaultcode = $res['defaultcode'];
			}
			$this->_customcode 	= $res['customcode'];
			$this->_enabled 	= $res['enabled'];
			$this->_providedest = $res['providedest'];
			$this->_depend 		= $res['depend'];

			$this->_loaded 		= true;

			return true;
		} else {

			return false;
		}
	}

	/**
	 * Update the feature code from the provided settings to the database
	 *
	 * @return bool true if good data, false if we want to cry
	 */
	function update() {
		global $amp_conf;
		if ($this->_enabled == -1) {
			// not explicitly set, old default was to enable by default, we will preserve that behaviour
			$this->_enabled = 1;
		}

		if (!$this->isReady())
			die_freepbx('FeatureCode: class function init never called...will not update');

		$cc = isset($this->_customcode) ? $this->_customcode : "";
		if ($this->_loaded)
		{
			$sql = 'UPDATE `featurecodes` SET `description` = ?, `helptext` = ?, `defaultcode` = ?, `customcode` = ?, `enabled` = ?, `providedest` = ?, `depend` = ? WHERE `modulename` = ? AND `featurename` = ?';
		}
		else
		{
			$sql = "INSERT INTO `featurecodes` (`description`, `helptext`, `defaultcode`, `customcode`, `enabled`, `providedest`, `depend`, `modulename`, `featurename`) VALUES (?,?,?,?,?,?,?,?,?)";
		}

		$sql_data = array(
			$this->_description,
			$this->_helptext,
			$this->_defaultcode,
			$cc,
			$this->_enabled,
			$this->_providedest,
			isset($this->_depend) ? $this->_depend : '',
			$this->_modulename,
			$this->_featurename,
		);
		
		$sth = $this->db->prepare($sql);
		$sth->execute($sql_data);

		return true;
	}

	/**
	 * Sets the Help Text for a feature code
	 *
	 * @param string $helptext The help text, used in help bubbles (?)
	 */
	function setHelpText($helptext) {
		if (!$this->isReady())
			$this->init(1);

		if ($helptext == '') {
			unset($this->_helptext);
		} else {
			$this->_helptext = $helptext;
		}
	}

	/**
	 * Gets the help text from the database
	 *
	 * @return string Help Text string, blank if not defined
	 */
	function getHelpText() {
		if (!$this->isReady())
			$this->init(1);

		return (isset($this->_helptext) ? $this->_helptext : '');
	}

	/**
	 * Sets the visual description of the feature code (not to be confused with Help Text, this is always displayed on module admin)
	 *
	 * @param string $description The text for the description
	 */
	function setDescription($description) {
		if (!$this->isReady())
			$this->init(1);

		if ($description == '') {
			unset($this->_description);
		} else {
			$this->_description = $description;
		}
	}

	/**
	 * Reads from the database of featurecodes
	 *
	 * @return string The text for the description
	 */
	function getDescription() {
		if (!$this->isReady())
			$this->init(1);

		$desc = (isset($this->_description) ? $this->_description : '');

		return ($desc != '' ? $desc : $this->_featurename);
	}

	/**
	 * Sets the default feature code number for this Feature Code
	 *
	 * @param string $defaultcode The default feature code, can be '*NN' or 'NN' or 'NNN' it doesnt matter
	 * @param bool $defaultenabled Whether the setting is enabled for not (by default)
	 */
	function setDefault($defaultcode, $defaultenabled = true) {
		if (!$this->isReady())
			$this->init(1);

		if (isset($this->_overridecodes[$this->_modulename][$this->_featurename])) {
			$defaultcode = $this->_overridecodes[$this->_modulename][$this->_featurename];
		}

		if (trim($defaultcode) == '') {
			unset($this->_defaultcode);
		} else {
			$this->_defaultcode = $defaultcode;
		}

		if ($this->_enabled == -1) {
			$this->_enabled = ($defaultenabled) ? 1 : 0;
		}

	}

	/**
	 * Gets the default code for this feature code
	 *
	 * @return string Get the default code for this feature code
	 */
	function getDefault() {
		if (!$this->isReady())
			$this->init(1);

		$def = (isset($this->_defaultcode) ? $this->_defaultcode : '');

		return $def;
	}

	/**
	 * Sets the custom feature code number, this is set in feature code admin usually
	 * This happens when the user changes said code manually away from whatever it was before
	 *
	 * @param string $customcode The custom code can be any dialable thing in Asterisk
	 */
	function setCode($customcode) {
		if (!$this->isReady())
			$this->init(1);

		if ($customcode == '') {
			unset($this->_customcode);
		} else {
			$this->_customcode = $customcode;
		}
	}

	/**
	 * Get the user defined feature code
	 * This happens when the user changes said code manually away from whatever it was before
	 *
	 * @return string The feature code the user changed
	 */
	function getCode() {
		if (!$this->isReady())
			$this->init(1);

		$curcode = (isset($this->_customcode) ? $this->_customcode : '');
		$defcode = (isset($this->_defaultcode) ? $this->_defaultcode : '');

		return ($curcode == '' ? $defcode : $curcode);
	}

	/**
	 * Get the user defined feature code but only if it's enabled from feature code admin
	 * This happens when the user changes said code manually away from whatever it was before
	 *
	 * @return string The feature code the user changed
	 */
	function getCodeActive() {
		if ($this->isEnabled()) {
			return $this->getCode();
		} else {
			return '';
		}
	}

	/**
	 * Enable the feature code
	 *
	 * @return bool $b True if enable, False if disable
	 */
	function setEnabled($b = true) {
		if (!$this->isReady())
			$this->init(1);

		$this->_enabled = ($b ? 1 : 0);
	}

	/**
	 * Checks to see if said feature code is enabled or not
	 *
	 * @return string The feature code the user changed
	 */
	function isEnabled() {
		if (!$this->isReady())
			$this->init(1);

		return ($this->_enabled == 1);
	}

	/**
	 * Set the ability for this feature code to be a destination
	 *
	 * @param $b bool True if we should provide the destination throughout freepbx or false if not
	 */
	function setProvideDest($b = true) {
		if (!$this->isReady())
			$this->init(1);

		$this->_providedest = ($b ? 1 : 0);
	}

	/**
	 * Checks to see if this feature code is a desintation
	 *
	 * @return bool True if it is, false if it's not
	 */
	function isProvideDest() {
		if (!$this->isReady())
			$this->init(1);

		return ($this->_providedest == 1);
	}

	/**
	 * Deletes the feature code from the system
	 *
	 * @return bool True if it's deleted
	 */
	function delete(){

		$sql = 'DELETE FROM `featurecodes` WHERE `modulename` = ? AND `featurename` = ?';
		$sth = $this->db->prepare($sql);
		$sth->execute(array($this->_modulename, $this->_featurename));

		$this->_enabled = -1; // = not ready

		return true;
	}

	public static function getAll($modulename) {
		$sql = "SELECT * FROM `featurecodes` WHERE `modulename` = ?";
		$db = \FreePBX::Database();
		$sth = $db->prepare($sql);
		$sth->execute(array($modulename));
		return $sth->fetchAll(\PDO::FETCH_ASSOC);
	}

	public static function CodeActive($modulename, $featurename) {
		$fcc = new \featurecode($modulename, $featurename);
		$code = $fcc->getCodeActive();
		unset($fcc);
		return $code;
	}
	
	function setDepend($new_depend) {
		if (!$this->isReady())
			$this->init(1);

		if ($new_depend == '') {
			unset($this->_depend);
		} else {
			$this->_depend = $new_depend;
		}
	}

	function getDepend() {
		if (!$this->isReady())
			$this->init(1);

		return (isset($this->_depend) ? $this->_depend : '');
	}
}
