<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * Request_Helper provides a consistent way to catch and process $_REQUEST
 * accesses
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
namespace FreePBX;
class Request_Helper extends Self_Helper {

	private $overrides = array();
	public $classOverride = false;

	/**
	 * Processes $_REQUEST and imports the useful things to the KVstore.
	 *
	 * This loads everything provided in the GET/POST into the Key Value store.
	 * As this is implicitly safe, there is no need for extra sanity checking,
	 * and makes coding a pile easier.
	 * This does NOT use any of the Override features provided by
	 * getReq and setReq.
	 *
	 * @param array $ignoreVars Array of variables to not process.
	 * @param string $ignoreRgexp Regular expression to match exclusions against.
	 * @param string $id ID to store the contents against.
	 *
	 * @return array Returns any _REQUEST variables that haven't been processed.
	 */
	public function importRequest($ignoreVars = null, $ignoreRegexp = null, $id = "noid") {
		$request = $this->getSanitizedRequest();
		$ignored = array();

		// Default ignoreVars
		if (!$ignoreVars) {
			$ignoreVars = array("display", "type", "category", "Submit");
		}

		if (is_array($ignoreVars)) {
			// Remove any variables we've been told to ignore.
			foreach ($ignoreVars as $i) {
				if(isset($request[$i])) {
					// Hang onto it, we return it later.
					$ignored[$i] = $request[$i];
					unset($request[$i]);
				}
			}
		}

		// Now, loop through everything else.
		foreach ($request as $key => $var) {

			// Do we have a regexp to check against?
			if (!empty($ignoreRegexp) && preg_match($ignoreRegexp, $key, $match)) {
				// It matched. Add it to ignored to be returned.
				$ignored[$key] = $var;
				continue;
			}

			// Is it a Radio button?  It'll return $r['foo'] = 'foo=var';
			if (is_string($var) && preg_match("/^$key=(.+)$/", $var, $match)) {
				// It is.
				$this->setConfig($key, $match[1], $id);
				continue;
			}

			// Always replace _'s with .'s. Easier to do it here
			$key = str_replace("_", ".", $key);

			$this->setConfig($key, $var, $id);
		}

		// Now we return what was left over.
		if (!$ignored) {
			return array();
		} else {
			return $ignored;
		}
	}

	/**
	 * Get individual $_REQUEST variables, unsafely.
	 *
	 * @param string $var $_REQUEST variable to get
	 * @param bool|string $def Default to return if unset, bool false to not return defaults
	 *
	 * @return bool|string Returns the variable, or false if unset
	 */
	public function getReqUnsafe($var = null, $def = true) {
		return $this->getSingleRequestVariable($var, $def, false);
	}

	/**
	 * Get individual $_REQUEST variables, safely.
	 *
	 * @param string $var $_REQUEST variable to get
	 * @param bool|string $def Default to return if unset, bool false to not return defaults
	 *
	 * @return bool|string Returns the safe, processed variable, or false if unset
	 */
	public function getReq($var = null, $def = true) {
		return $this->getSingleRequestVariable($var, $def);
	}

	/**
	 * Get individual $_REQUEST variables, safely or unsafely.
	 *
	 * Does an implicit check for not set, and returns (bool) false if it doesn't exist.
	 * If a default is provided, return the default if not set. If no default, and not
	 * set, return (string) ""  (an empty string).  This currently encodes everything
	 * that could possibly be nasty. We may relax this later.
	 *
	 * @param string $var $_REQUEST variable to get
	 * @param bool|string $def Default to return if unset, bool false to not return defaults
	 * @param bool $safe Santitize Request or not
	 *
	 * @return bool|string Returns the safe, processed variable, or false if unset
	 */
	private function getSingleRequestVariable($var = null, $def = true, $safe = true) {
		if (!$var) {
			throw new \Exception("Wasn't given anything to get from REQUEST.");
		}

		// Do we have an override?
		if (isset($this->overrides[$var])) {
			return $this->overrides[$var];
		}

		if($safe) {
			$request = $this->getSanitizedRequest();
		} else {
			$request = $_REQUEST;
		}

		// If it doesn't exist...
		if (!isset($request[$var])) {
			if ($def === false) {
				// It doesn't exist, and we've been told not to return defaults.
				return null;
			}
			if ($def === true) {
				// We weren't given a default. Does the parent, or specified, class have a default
				// for this?
				if ($this->classOverride) {
					$class = $this->classOverride;
					$this->classOverride = false;
				} else {
					$class = get_class($this);
				}
				if (property_exists($class, "reqDefaults")) {
					$def = $class::$reqDefaults;
					if (isset($def[$var])) {
						return $def[$var];
					}
				}
				// No. No default, not set. Return an empty string
				return (string) "";
			} else {
				// We were given a default. Give it right back.
				return $def;
			}
		} else {
			// It exists!
			return $request[$var];
		}
	}

	/**
	 * Overrides or adds to whatever's in $_REQUEST
	 *
	 * This is used for backwards compatibility with previous modules, which used to
	 * add to $_REQUEST. This is a Read-Only variable in PHP 5.5 and higher, so we
	 * needed a way to replace it in the interim.  This is that way.  If you want to
	 * remove an override, set it to null. To delete a $_REQUEST variable, set it to
	 * (bool) false.
	 *
	 * @param string $var $_REQUEST variable to add/update
	 * @param bool|string $val value to set it to.
	 *
	 * @return void
	 */
	public function setReq($var = null, $val = null) {
		if ($var === null) {
			throw new \Exception("Don't know what you want me to update");
		}

		if ($val === null) {
			unset($this->overrides[$var]);
		} else {
			$this->overrides[$var] = $val;
		}
		return;
	}

	/**
	 * Get $_REQUEST sanitized through filter_input_array
	 *
	 * @method getSanitizedRequest
	 * @return array              $_REQUEST, sanitized
	 */
	public function getSanitizedRequest($definition = FILTER_SANITIZE_STRING, $add_empty = true) {
		$order = ini_get('request_order');
		$order = !empty($order) ? $order : ini_get('variables_order');
		$total = strlen($order);
		$request = array();
		for($i = 0; $i < $total; $i++) {
			switch($order[$i]) {
				case 'G':
					$GET = filter_input_array(INPUT_GET, $definition, $add_empty);
					if(is_array($GET)) {
						$request = array_merge($request,$GET);
					}
				break;
				case 'P':
					$POST = filter_input_array(INPUT_POST, $definition, $add_empty);
					if(is_array($POST)) {
						$request = array_merge($request,$POST);
					}
				break;
				case 'C':
					$COOKIE = filter_input_array(INPUT_COOKIE, $definition, $add_empty);
					if(is_array($COOKIE)) {
						$request = array_merge($request,$COOKIE);
					}
				break;
			}
		}

		return $request;
	}
}
