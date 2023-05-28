<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * Provides an 'easy unlock' function for automated remote login
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
namespace FreePBX;
class Unlock extends FreePBX_Helpers {

	public function __construct($freepbx = null, $var = null) {
		if ($var) {
			$this->checkUnlock($var);
		}
	}

	/**
	 * Generate a new unlock key and store it in the database
	 * @return {string} The new key
	 */
	public function genUnlockKey() {

		// Check to see that REMOTEUNLOCK is enabled
		if (!$this->Config->get_conf_setting('REMOTEUNLOCK')) {
			return false;
		}

		// PHP 5.3 or higher.
		// Get 256 random bits
		$rand = openssl_random_pseudo_bytes(32);
		// Hash it
		$key = hash("sha256", $rand);
		if (strlen($key) != 64) {
			throw new Exception("Severe PHP Error - A sha256 hash was not 64 characters long. I recieved '$key'");
		}
		$this->setConfig('unlockkey', $key);
		return $key;
	}

	/**
	 * Check the passed key to see if its valid,
	 * if it's valid then run unlockSession()
	 *
	 * @param {string} $key The passed key to check
	 * @return {bool} return status from unlockSession
	 */
	public function checkUnlock($key = null) {

		// Check to see that REMOTEUNLOCK is enabled
		if (!$this->Config->get_conf_setting('REMOTEUNLOCK')) {
			return false;
		}

		if (!$key) {
			return false;
		}

		$currentkey = $this->getConfig('unlockkey');
		if (!$currentkey) {
			return false;
		}

		if ($key !== $currentkey) {
			// Didn't match. Delete the key.
			$this->setConfig('unlockkey');
			return false;
		}

		// Woo. It passed. Unlock this session!
		return $this->unlockSession();
	}

	/**
	 * Unlock the user session and delete the unlock key
	 * @return {bool} Return true if unlocked, or false if not
	 */
	private function unlockSession() {
		if (!isset($_SESSION)) {
			return false;
		}

		// Delete this key, it's been used now.
		$this->setConfig('unlockkey');

		$_SESSION["AMP_user"] = new \ampuser('admin');
		$_SESSION["AMP_user"]->setAdmin();
		if (!defined('FREEPBX_IS_AUTH')) define('FREEPBX_IS_AUTH', 'TRUE');

		return true;
	}
}
