<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * Add and remove Entries from Asterisk Configuration Files.
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
namespace FreePBX;
class ConfigFile {

	public $config;
	private $file;


	/**
	 * ConfigFile Constructor.
	 * @param  {object} $freepbx = null The FreePBX Object
	 * @param  {string} $file    = null filename to load
	 */
	public function __construct($freepbx = null, $file = null) {
		if ($freepbx == null) {
			throw new \Exception("Not given a FreePBX Object");
		}
		$this->FreePBX = $freepbx;

		if ($file == null) {
			throw new \Exception("Not given a file to manage");
		}

		$this->config = $this->FreePBX->LoadConfig($file);
		$this->file = $file;
	}

	/**
	 * Add Entry to Configuration file that has been loaded
	 * @param {string} $section The section we are adding the value to
	 * @param {mixed} $entry = null The value, can be array or string
	 */
	public function addEntry($section, $entry = null) {
		// If we don't have an $entry, then we're just adding to the file
		if ($entry === null) {
			// However, if there are any items in the config that AREN'T just
			// under 'HEADER', then we can't add it, as it's extremely unlikely
			// that we want to append it to the end of the file, not caring about
			// which section its in.
			$myconfig = $this->config->ProcessedConfig;
			unset($myconfig['HEADER']);
			if (!empty($myconfig)) {
				throw new \Exception("Tried to add string '$entry' to ".$this->file.", but it has sections.");
			}

			$this->doAdd('HEADER', $section); // Not section. Entry.
		} elseif (is_string($entry)) {
			$this->doAdd($section, $entry);
		} elseif (is_array($entry)) {
			foreach ($entry as $key => $row) {
				if(is_string($key)) {
					$this->doAdd($section, $key, $row);
				} else {
					$this->doAdd($section, $row);
				}
			}
		} else {
			throw new \Exception("Unimplemented");
		}

		$this->updateConfig();
	}

	/**
	 * Remove an entry from the write buffer
	 * @param {string} $section The section we are removing the value from
	 * @param {string} $key     The key we are looking for
	 * @param {string} $val = null The value we are looking to remove, if blank then remove it regardless of value
	 */
	public function removeEntry($section, $key, $val = null) {
		// Lets find it!

		// Does the section exist? This is more of a 'The dev stuffed up' check.
		if (!isset($this->config->ProcessedConfig[$section])) {
			throw new \Exception("Tried to remove key $key from section $section, but that section doesn't exist");
		}

		if (isset($this->config->ProcessedConfig[$section][$key]) && is_array($this->config->ProcessedConfig[$section][$key])) {
			if ($val == null) {
				throw new \Exception("Sorry, you can't delete an entire section this way, as it's likely a bug");
			}
			$this->config->ProcessedConfig[$section][$key] = array_filter(
				$this->config->ProcessedConfig[$section][$key],
				function($v) use($val) {return ($v != $val);}
			);
			// Have we deleted everything from that $key?
			if (count($this->config->ProcessedConfig[$section][$key]) == 0) {
				unset($this->config->ProcessedConfig[$section][$key]);
			}
		} elseif (isset($this->config->ProcessedConfig[$section][$key])) {
			// OK, just one key, easy!
			if ($val == null || $this->config->ProcessedConfig[$section][$key] == $val) {
				unset($this->config->ProcessedConfig[$section][$key]);
			}
		}

		// Is there anything left in that section?
		if (count($this->config->ProcessedConfig[$section]) == 0) {
			unset($this->config->ProcessedConfig[$section]);
		}
		$this->updateConfig();
	}

	/**
	 * Add an individual setting, this should not be called externally!
	 * @param {string} $section The section to add to
	 * @param {string} $key     The key we are adding the value to
	 * @param {string} $val = false the value of the key
	 */
	private function doAdd($section, $key, $val = false) {
		// If $val is false, split the = in $key
		if ($val === false) {
			if (preg_match("/^(.+)(?:=|=>)(.+)$/", $key, $out)) {
				$key = trim($out[1]);
				$val = trim($out[2]);
			} else {
				throw new \Exception("Can't add '$item' to the config. No Equals??");
			}
		}

		// Now, lets check if this key already exists in this section.
		// A lot of the time it will. But we may be adding the second one..
		if (!isset($this->config->ProcessedConfig[$section][$key])) {
			// Easy. New one.
			$this->config->ProcessedConfig[$section][$key] = $val;
			return;
		}

		// It exists. If it's an array, then we append to that array..
		if (is_array($this->config->ProcessedConfig[$section][$key])) {
			// Also easy.
			$this->config->ProcessedConfig[$section][$key][] = $val;
			return;
		}

		// It exists, is NOT an array, and we want to add an identical key.
		// Poot. OK, make this an array, add the entry back, and then add
		// the new one.

		$tmpvar = $this->config->ProcessedConfig[$section][$key];
		unset($this->config->ProcessedConfig[$section][$key]);
		$this->config->ProcessedConfig[$section][$key][] = $tmpvar;
		$this->config->ProcessedConfig[$section][$key][] = $val;

		return;
	}

	/**
	 * Write out the configuration that we processed from memory
	 */
	private function updateConfig() {
		$this->FreePBX->WriteConfig(array($this->file => $this->config->ProcessedConfig));
	}
}
