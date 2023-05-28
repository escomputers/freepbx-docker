<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * LoadConfig class
 * This class represents a way to load Asterisk Format Configuration files were
 * [section]key=value into a PHP hash such as array('section' => array('key' => 'value'))
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
namespace FreePBX;
class LoadConfig {

	private $RawConfigContents;
	public $PlainConfig;
	public $BaseConfig;
	public $ProcessedConfig;

	private $Filename;

	/**
	 * Setup the call to load config, same as loadConfig() method below
	 * just more direct
	 *
	 * @param object $freepbx the FreePBX BMO Object
	 * @param string $file The basename of the file to load
	 * @param string $hint The directory where the file lives
	 */
	public function __construct($freepbx = null, $file = null, $hint = "") {
		if ($freepbx == null) {
			throw new \Exception(_("Need to be instantiated with a FreePBX Object"));
		}

		$this->freepbx = $freepbx;

		$hint = !empty($hint) ? $hint : $this->freepbx->Config->get('ASTETCDIR');

		if ($file !== null) {
			$this->loadConfig($file, $hint);
		}
	}

	/**
	 * Loads and Processes a Configuration in the Asterisk Format
	 *
	 * This will attempt to load a file and then parse it
	 * the file must be in the asterisk configuration file format!
	 *
	 * Note: this function does not return said file!
	 *
	 * @param string $file The basename of the file to load
	 * @param string $hint The directory where the file lives
	 * @return bool True if pass
	 */
	public function loadConfig($file = null, $hint = "") {
		$hint = !empty($hint) ? $hint : $this->freepbx->Config->get('ASTETCDIR');
		//clear old contents out
		$this->ProcessedConfig = $this->BaseConfig = $this->PlainConfig = $this->RawConfigContents = "";

		if ($file === null) {
			throw new \Exception(_("No file given to load"));
		}

		$filename = $this->validateFilename($file,$hint);

		$this->Filename = $filename;
		$config = file($filename, FILE_SKIP_EMPTY_LINES|FILE_IGNORE_NEW_LINES);
		$this->RawConfigContents = $config;

		// Remove the header
		$this->stripHeader($config);
		$this->PlainConfig = $config;

		// Now remove the comments
		$this->stripComments($config);
		$this->BaseConfig = $config;

		// And break it into elements.
		$this->explodeConfig($config);
		return true;
	}

	/**
	 * Get Raw Contents of a Configuration File
	 *
	 * This will get the raw unprocessed contents of a configuration file
	 *
	 * Note: This will only work AFTER loadConfig has run
	 *
	 * @param string $file The basename of the file to load
	 * @return string Raw Contents of said file
	 */
	public function getRaw($file = null) {
		if ($file === null && !isset($this->RawConfigContents)) {
			throw new \Exception(_("Asked for raw contents of a file, but was never asked to read a file"));
		}

		return $this->RawConfigContents;
	}

	/**
	 * Get The Processed Contents of a Configuration File
	 *
	 * This will process and return a configuration file in the Asterisk Configuration
	 * file format in a hashed format for processing
	 *
	 * @param string $file The basename of the file to load
	 * @param string $hint The directory where the file lives
	 * @param string $context The specific context to return, if not set then return all
	 * @return array The hashed configuration file
	 */
	public function getConfig($file = null, $hint = "", $context = null) {
		if ($file === null) {
			throw new \Exception(_("No file given to load"));
		}

		$hint = !empty($hint) ? $hint : $this->freepbx->Config->get('ASTETCDIR');

		$this->loadConfig($file, $hint);

		return (!empty($context) && isset($this->ProcessedConfig[$context])) ? $this->ProcessedConfig[$context] : $this->ProcessedConfig;
	}

	/**
	 * Validate Filename
	 *
	 * This will validate the provided file name to make sure there isn't some hackery-dackery going on
	 *
	 * @param string $file The basename of the file to load
	 * @param string $hint The directory where the file lives
	 * @return string The complete file path
	 */
	private function validateFilename($file, $hint = "") {
		$hint = !empty($hint) ? $hint : $this->freepbx->Config->get('ASTETCDIR');
		// Check to make sure it doesn't have any /'s or ..'s
		// in it. We're only allowed to write to /etc/asterisk or our hint

		if (strpos($file, "/") !== false) {
			throw new \Exception(sprintf(_("%s contains a /"),$file));
		}
		if (strpos($file, "..") !== false) {
			throw new \Exception(sprintf(_("%s contains .."),$file));
		}

		$filename = $hint."/".$file;
		return $filename;
	}

	/**
	 * Strip Headers
	 *
	 * This completely Strips the header from the configuration file
	 *
	 * @param array $arr The Config File's array to remove headers from
	 */
	private function stripHeader(&$arr) {
		// Remove all headers in this file
		// First, take a copy of the array
		$myarr = $arr;
		$comment_block = false;

		// Now, go through my copy..
		foreach ($myarr as $id => $line) {
			//Trim whitespace
			$line = trim($line);
			if ($comment_block === true) {
				if (($pos = strpos($line, "--;")) !== false) {
					// End of the comment block, carry on with the rest of the line
					$comment_block = false;
					$line = substr($line, $pos + 3);
				} else {
					// The comment block continues
					$line = "";
				}
			}
			// Negative lookbehind assertion matches unescaped semicolons only
			if ($comment_block === false && preg_match("/(?<!\\\);--.*?--;/", $line)) {
				// Line contains a whole comment block
				$line = preg_replace("/(?<!\\\);--.*?--;/", "", $line);
			}
			if ($comment_block === false && preg_match("/(?<!\\\);--/", $line)) {
				// Line starts a comment block
				$comment_block = true;
				$line = preg_replace("/(?<!\\\);--.*/", "", $line);
			}
			if ($comment_block === false && preg_match("/(?<!\\\);/", $line)) {
				// Line contains a standard comment
				$line = preg_replace("/(?<!\\\);.*/", "", $line);
			}
			if (empty($line)) {
				unset($arr[$id]);
			} else {
				$arr[$id] = $line;
				// It's not a comment, which means we're past the header.
				// Stop now.
				break;
			}
		}
	}

	/**
	 * Strip Comments
	 *
	 * This completely Strips Comments from a file
	 *
	 * @param array $arr The Config File's array to remove comments from
	 */
	private function stripComments(&$arr) {
		// Remove all comments.
		// First, take a copy of the array
		$myarr = $arr;
		$comment_block = false;

		// Again, go through my copy...
		foreach ($myarr as $id => $line) {
			//Trim whitespace
			$line = trim($line);
			if ($comment_block === true) {
				if (($pos = strpos($line, "--;")) !== false) {
					// End of the comment block, carry on with the rest of the line
					$comment_block = false;
					$line = substr($line, $pos + 3);
				} else {
					// The comment block continues
					$line = "";
				}
			}
			// Negative lookbehind assertion matches unescaped semicolons only
			if ($comment_block === false && preg_match("/(?<!\\\);--.*?--;/", $line)) {
				// Line contains a whole comment block
				$line = preg_replace("/(?<!\\\);--.*?--;/", "", $line);
			}
			if ($comment_block === false && preg_match("/(?<!\\\);--/", $line)) {
				// Line starts a comment block
				$comment_block = true;
				$line = preg_replace("/(?<!\\\);--.*/", "", $line);
			}
			if ($comment_block === false && preg_match("/(?<!\\\);/", $line)) {
				// Line contains a standard comment
				$line = preg_replace("/(?<!\\\);.*/", "", $line);
			}
			if (empty($line)) {
				unset($arr[$id]);
			} else {
				$arr[$id] = $line;
			}
		}
	}

	/**
	 * Explode Config
	 *
	 * This Explodes the Configuration File into arrays where <key>=<value> will be turned into ['key'] => value
	 *
	 * @param array $conf The Config File's array to parse
	 */
	private function explodeConfig($conf) {
		// Process the config we've been given, and return a useful array
		//
		// Ensure ProcessedConfig is an array (PHP 7.2 compatibility)
		if (!is_array($this->ProcessedConfig)) {
			$this->ProcessedConfig = [];
		}

		// Anything prior to the first section is in the magic 'HEADER' section
		$section = "HEADER";
		foreach ($conf as $entry) {
			$entry = trim($entry);
			if (preg_match("/^\[(\S+)\](?:\((.*)\))?/", $entry, $out)) {
				$section = $out[1];
				$modifier = isset($out[2]) ? $out[2] : "+";
				if ($modifier === "!") {
					// This is a template section, not part of the configuration
					//
					// UNLESS it's in 'directories' which is a mistake in Ubuntu's
					// Asterisk build (Seen in Asterisk 13.18.3~dfsg-1ubuntu4)
					if ($section !== "directories") {
						$section .= "__TEMPLATE__";
					}
				} else if ($modifier !== "+") {
					$modifier = explode(",", $modifier);
					foreach ($modifier as $template) {
						if (isset($this->ProcessedConfig[$template . "__TEMPLATE__"])) {
							foreach ($this->ProcessedConfig[$template . "__TEMPLATE__"] as $k=>$v) {
								$this->ProcessedConfig[$section][$k] = $v;
							}
						}
					}
				}
				continue;
			}

			if (preg_match("/^([^+=]+)\s*(?:(\+)?=>?)\s*(.+)?$/", $entry, $out)) {
				$out = array_map("trim", $out);
				if(!isset($out[3])) {
					$out[3] = "";
				}
				// If it doesn't have anything set, then we don't care.
				if (empty($out[3]) && $out[3] !== "0") {
					continue;
				}

				// Replace any escaped semicolons
				$out[3] = str_replace("\\;", ";", $out[3]);

				// Are we appending to an existing value?
				$append_value = ($out[2] === "+");

				if (isset($this->ProcessedConfig[$section]) && isset($this->ProcessedConfig[$section][$out[1]])) {
					// This already exists. Multiple definitions.
					if ($append_value) {
						$this->ProcessedConfig[$section][$out[1]] .= $out[3];
					} else {
						if (!is_array($this->ProcessedConfig[$section][$out[1]])) {
							// This is the first time we've found this, so make it an array.

							$tmp = $this->ProcessedConfig[$section][$out[1]];
							unset($this->ProcessedConfig[$section][$out[1]]);
							$this->ProcessedConfig[$section][$out[1]][] = $tmp;
						}
						// It's an array, so we can just append to it.
						$this->ProcessedConfig[$section][$out[1]][] = $out[3];
					}
				} else {
					$this->ProcessedConfig[$section][$out[1]] = $out[3];
				}
			} else if (preg_match("/^#(include|exec)/", $entry)) {
				$this->ProcessedConfig[$section][] = $entry;
			} else if ($entry === "") {
				continue;
			} else {
				throw new \Exception(sprintf(_("Coding Error - don't understand '%s' from %s"), $entry, $this->Filename));
			}
		}
		// We're done, remove all the templates
		if(is_array($this->ProcessedConfig)) {
			foreach (array_keys($this->ProcessedConfig) as $key) {
				if (strpos($key, "__TEMPLATE__") !== false) {
					unset($this->ProcessedConfig[$key]);
				}
			}
		}
	}
}
