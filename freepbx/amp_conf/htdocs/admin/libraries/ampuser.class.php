<?php
/**
 * Login functionality and user session management for FreePBX
 */
class ampuser {
	public $username;
	public $id;
	private $password;
	private $extension_high;
	private $extension_low;
	private $sections;
	private $mode = "database";
	private $opmode;

	public function __construct($username, $mode="database") {
		$this->username = $username;
		$this->mode = $mode;
		$user = $this->getAmpUser($username);
		if (!empty($user)) {
			$this->password = $user["password_sha1"];
			$this->extension_high = $user["extension_high"];
			$this->extension_low = $user["extension_low"];
			$this->sections = $user["sections"];
			$this->id = isset($user['id']) ? $user['id'] : null;
			$this->opmode = isset($user['opmode']) ? $user['opmode'] : null;
			$this->lang = isset($user['lang']) ? $user['lang'] : null;
			$this->tz = isset($user['tz']) ? $user['tz'] : null;
			$this->dateformat = isset($user['dateformat']) ? $user['dateformat'] : null;
			$this->timeformat = isset($user['timeformat']) ? $user['timeformat'] : null;
			$this->datetimeformat = isset($user['datetimeformat']) ? $user['datetimeformat'] : null;
		} else {
			// user doesn't exist
			$this->password = false;
			$this->extension_high = "";
			$this->extension_low = "";
			$this->sections = array();
		}
	}

	/**
	 * Give this usr full admin access
	 */
	public function setAdmin() {
		$this->extension_high = "";
		$this->extension_low = "";
		$this->deptname = "";
		$this->sections = array("*");
	}

	/**
	 * Check password
	 * @param  string $password The password to Check
	 * @return bool           True if accepted false otherwise
	 */
	public function checkPassword($password) {
		$password = (string)$password;

		// strict checking so false will never match
		switch($this->mode) {
			case "usermanager":
				try {
					return FreePBX::Userman()->getCombinedGlobalSettingByID($this->id,'pbx_login') && FreePBX::Userman()->checkCredentials($this->username,$password);
				} catch(Exception $e) {}
				//fail-through
			case "database":
			default:
				return ($this->password === sha1($password));
		}
	}

	/**
	 * Check to see if the user can view said section
	 * @param  string $section The section name
	 * @return bool          True of False
	 */
	public function checkSection($section) {
		// if they have * then it means all sections
		if(empty($this->sections) || !is_array($this->sections)) {
			//section is empty try to convert it maybe?
			if(!$this->convertAmpUser()) {
				//there was nothing to convert fail
				return false;
			}
			//check to see if converted sections have anything
			if(empty($this->sections) || !is_array($this->sections)) {
				return false;
			}
		}
		return in_array("*", $this->sections) || in_array($section, $this->sections);
	}

	/**
	 * Get the AMP User from the username
	 * @param  string $username the username
	 * @return mixed           False is false otherwise array of user
	 */
	public function getAmpUser($username) {
		switch($this->mode) {
			case "usermanager":
				try {
					$um = FreePBX::Userman()->getUserByUsername($username, null, false);
					if(!empty($um)) {
						$user = array();
						$user['id'] = $um['id'];
						$user["username"] = $um['username'];
						$user["password_sha1"] = $um['password'];
						$pbl = FreePBX::Userman()->getCombinedGlobalSettingByID($um['id'],'pbx_low');
						$user["extension_low"] = (trim($pbl) !== "") ? $pbl : "";
						$pbh = FreePBX::Userman()->getCombinedGlobalSettingByID($um['id'],'pbx_high');
						$user["extension_high"] = (trim($pbh) !== "") ? $pbh : "";
						$sections = FreePBX::Userman()->getCombinedGlobalSettingByID($um['id'],'pbx_modules');
						$user["sections"] = !empty($sections) && is_array($sections) ? $sections : array();
						$user["opmode"] = FreePBX::Userman()->getCombinedGlobalSettingByID($um['id'],'opmode');
						$user["lang"] = FreePBX::Userman()->getLocaleSpecificSetting($um['id'],"language");
						$user["tz"] = FreePBX::Userman()->getLocaleSpecificSetting($um['id'],"timezone");
						$user["dateformat"] = FreePBX::Userman()->getLocaleSpecificSetting($um['id'],"dateformat");
						$user["timeformat"] = FreePBX::Userman()->getLocaleSpecificSetting($um['id'],"timeformat");
						$user["datetimeformat"] = FreePBX::Userman()->getLocaleSpecificSetting($um['id'],"datetimeformat");
						$user['mode'] = $this->mode;
						return $user;
					}
				} catch(Exception $e) {}
				//fail-through
			case "database":
			default:
				$sql = "SELECT username, password_sha1, extension_low, extension_high, deptname, sections FROM ampusers WHERE username = ?";
				$sth = FreePBX::Database()->prepare($sql);
				$sth->execute(array($username));
				$results = $sth->fetchAll(\PDO::FETCH_ASSOC);
				if (count($results) > 0) {
					$user = array();
					$user["username"] = $results[0]['username'];
					$user["password_sha1"] = $results[0]['password_sha1'];
					$user["extension_low"] = $results[0]['extension_low'];
					$user["extension_high"] = $results[0]['extension_high'];
					$user["sections"] = explode(";",$results[0]['sections']);
					$user['mode'] = $this->mode;
					return $user;
				} else {
					return false;
				}
			break;
		}
	}

	/**
	 * Convert old PHP4 style ampuser to PHP5
	 * @return bool	True if this function converted something
	 */
	private function convertAmpUser() {
		$converts = array(
			"password",
			"extension_high",
			"extension_low",
			"sections"
		);
		$status = false;
		foreach($converts as $c) {
			$b = "_".$c;
			if(isset($this->$b)) {
				$this->$c = $this->$b;
				unset($this->$b);
				$status = true;
			}
		}
		return $status;
	}
	public function getExtensionHigh(){
		return isset($this->extension_high)?$this->extension_high:'';
	}
	public function getExtensionLow(){
		return isset($this->extension_low)?$this->extension_low:'';
	}
	public function getOpMode() {
		return $this->opmode;
	}
	public function getMode() {
		return $this->mode;
	}
	public function getSections() {
		return $this->sections;
	}
}
