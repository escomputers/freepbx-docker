<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * GPG Class for FreePBX's BMO.
 *
 * This is an interface to GPG, for validating FreePBX Modules.
 * It uses the GPG Web-of-trust to ensure modules are valid
 * and haven't been tampered with.
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
namespace FreePBX;
class GPG {

	// Statuses:
	// Valid signature.
	const STATE_GOOD = 1;
	// File has been tampered
	const STATE_TAMPERED = 2;
	// File is signed, but, not by a valid signature
	const STATE_INVALID = 4;
	// File is unsigned.
	const STATE_UNSIGNED = 8;
	// This is in an unsupported state
	const STATE_UNSUPPORTED = 16;
	// Signature has expired
	const STATE_EXPIRED = 32;
	// Signature has been explicitly revoked
	const STATE_REVOKED = 64;
	// Signature is Trusted by GPG
	const STATE_TRUSTED = 128;

	// This is the FreePBX Master Key.
	private $freepbxkey = '0BDE0BFA09946D732091E26E1588A7366BD35B34';

	// Other keys that we also need to trust.
	private $trustkeys = array(
		'2016349F5BC6F49340FCCAF99F9169F4B33B4659'
	);

	private $revokedkeys = array(
	);

	// additional filesystem keys
	private $fskeys = array(
		'456D051E9204C27C37D4811BB53D215A755231A3',
		'072410D159E9DA63A459AB203DDB2122FE6D84F7',
		'1013D73FECAC918A0A25823986CE877469D2EAD9'
	);

	// Will hold path to 'gpg' binary
	private $gpg = false;

	// Default options.
	private $gpgopts = "--no-permission-warning --keyserver-options auto-key-retrieve=true,timeout=10 --no-tty";

	// List of well-known keyservers.
	private $keyservers = array(
		"hkp://keys.openpgp.org",
		"hkp://keyserver.ubuntu.com:80",  // This is in case port 11371 is blocked outbound
		"pgp.mit.edu", // Other random keyservers
		"keyserver.pgp.com"  // Other random keyserver
	); // Yes. sks is there twice.

	// This is how long we should wait for GPG to run a command.
	// This may need to be tuned on things like the pi.
	public $timeout = 6;

	// Manually loaded keys are here (This gets initialized in
	// __construct, below, because PHP)
	public $keydir;

	// Constructor, to provide some per-OS values
	// Fail if gpg isn't in an expected place
	public function __construct() {
		// Make sure that the system location is checked first. It's possible
		// that anything in /usr/local/bin could be malicious.
		$locations = array("/usr/bin/gpg", "/usr/bin/gpg2", "/usr/local/bin/gpg", "/usr/local/bin/gpg2");

		// Loop through our GPG locations, and make sure they exist,
		// and they're a file.
		foreach ($locations as $loc) {
			if (!file_exists($loc) || filetype($loc) !== "file") {
				continue;
			}
			$this->gpg = $loc;
			break;
		}

		if (!$this->gpg) {
			throw new \Exception(_("Could not find gpg command!"));
		}

		$this->keydir = __DIR__."/signatures";

		// Try to make our Signatures directory, for module-installed keyfiles
		if (!is_dir($this->keydir)) {
			// It doesn't exit. Try weakly to make it, it doesn't
			// really matter if we can't.
			@mkdir($this->keydir);
		}
	}

	/**
	 * Validate a file using WoT
	 * @param string $file Filename (explicit or relative path)
	 * @return bool returns true or false
	 */
	public function verifyFile($filename, $retry = true) {
		if (!file_exists($filename)) {
			throw new \Exception(sprintf(_("Unable to open file %s"),$filename));
		}

		$out = $this->runGPG("--verify ".escapeshellarg($filename));

		if (strpos($out['status'][0], "[GNUPG:] BADSIG") === 0) {
			// File has been tampered.
			return false;
		}
		//start at line 1, increase looking for next lines
		for($i=1;$i<count($out['status']);$i++) {
			if (strpos($out['status'][$i], "[GNUPG:] NO_PUBKEY") === 0) {
				// This should never happen, as we try to auto-download
				// the keys. However, if the keyserver timed out, or,
				// was out of date, we'll try it manually.
				//
				// strlen("[GNUPG:] NO_PUBKEY ") == 19.
				//
				if ($retry && $this->getKey(substr($out['status'][$i], 19))) {
					return $this->verifyFile($filename, false);
				} else {
					return false;
				}
			}
		}

		// Now, how does it check out?
		$status = $this->checkStatus($out['status']);
		if ($status['trust'] == true) {
			// It's trusted!  For the interim, we want to make sure that it's signed
			// by the FreePBX Key, or, by a key that's been signed by the FreePBX Key.
			// This is going above-and-beyond the web of trust thing, and we may end up
			// removing it.
			array_pop($out['status']); // Remove leading blank line.
			$validline = explode(" ", array_pop($out['status']));
			$thissig = $validline[2];
			$longkeys = array();
			foreach (array_merge(array($this->freepbxkey),$this->trustkeys) as $currentkey) {
				$longkeys[] = substr($currentkey, -16);
			}
			$allsigs = $this->runGPG("--keyid-format long --with-colons --check-sigs ".escapeshellarg($thissig));
			$isvalid = false;
			foreach (explode("\n", $allsigs['stdout']) as $line) {
				if (!$line) {
					continue; // Ignore blank lines
				}
				$tmparr = explode(":", $line);
				if (!empty($tmparr[4]) && in_array($tmparr[4], $longkeys)) {
					$isvalid = true;
				}
			}

			return $isvalid;
		} // else
		return false;
	}

	/**
	 * Check the module.sig file against the contents of the
	 * directory
	 *
	 * @param string Module name
	 * @return array (status => GPG::STATE_whatever, details => array (details, details))
	 */
	public function verifyModule($modulename = null) {
		if (!$modulename) {
			throw new \Exception(_("No module to check"));
		}

		if (strpos($modulename, "/") !== false) {
			throw new \Exception(_("Path given to verifyModule. Only provide a module name"));
		}

		// Get the module.sig file.
		$file = \FreePBX::Config()->get('AMPWEBROOT')."/admin/modules/$modulename/module.sig";

		if (!file_exists($file)) {
			// Well. That was easy.
			return array("status" => GPG::STATE_UNSIGNED, "details" => array(_("unsigned")));
		}

		$module = $this->checkSig($file);
		// Is this a local module?
		if (isset($module['parsedout']) && $module['parsedout']['config']['version'] > "1" && $module['parsedout']['config']['type'] == "local") {
			// We need to actually validate the LOCAL SECURE module
			$module = $this->processLocalSig($modulename, $module['parsedout']);
		} else {
			// Check the signature on the module.sig
			if (isset($module['status'])) {
				return array("status" => $module['status'], "details" => array(sprintf(_("module.sig check failed! %s"), $module['trustdetails'][0])));
			}
		}

		// var_dump($module);
		// Has this signature expired? This is used to make sure QA/Testing modules with bugs can't be used for
		// more than a short period of time. Normal module signatures shouldn't have an expiry.
		if ($module['config']['version'] > "1" && !empty($module['config']['expiresafter'])) {
			// This is a utime
			$expiry = (int) $module['config']['expiresafter'];
			if ($expiry < time()) {
				return array("status" => self::STATE_REVOKED, 'trustdetails' => array(_("Module signature expired")));
			}
		}

		// OK, signature is valid. Let's look at the files we know
		// about, and make sure they haven't been touched.
		$retarr['status'] = GPG::STATE_GOOD | GPG::STATE_TRUSTED;
		$retarr['details'] = array();
		$module['hashes'] = is_array($module['hashes']) ? $module['hashes'] : array();
		foreach ($module['hashes'] as $file => $hash) {
			$dest = \FreePBX::Installer()->getDestination($modulename, $file, true);
			if ($dest === false) {
				// If the file is explicitly un-checkable, ignore it.
				continue;
			}
			if (!file_exists($dest)) {
				$retarr['details'][] = $dest." "._("missing");
				$retarr['status'] |= GPG::STATE_TAMPERED;
				$retarr['status'] &= ~GPG::STATE_GOOD;
			} elseif (hash_file('sha256', $dest) != $hash) {
				// If you i18n this string, also note that it's used explicitly
				// as a comparison of "altered" in modulefunctions.class, to
				// warn people about bin/fwconsole needing to be updated
				// with 'fwconsole chown'. Don't make them different!
				$retarr['details'][] = $dest." "._("altered");
				$retarr['status'] |= GPG::STATE_TAMPERED;
				$retarr['status'] &= ~GPG::STATE_GOOD;
			}
		}

		return $retarr;
		// Reminder for people doing i18n.
		if (false) { echo _("If you're i18n-ing this file, read the comment about 'altered' and 'missing'"); }
	}

	/**
	 * Process a *locally* signed module
	 *
	 * This is called when the module.sig says that the module is signed locally. Several
	 * integrity checks are done, including validating file ownership, and ensuring that
	 * both files are signed by the same key.
	 *
	 * @param string $modname Module rawname
	 * @param array $localmod Contents of module.sig to be validated against /etc/freepbx.secure/modulename.sig.
	 *
	 * @return array $config
	 */

	private function processLocalSig($modname, $modsig) {
		// Start by validating the local secure directory
		$sec = "/etc/freepbx.secure";
		if (is_link($sec)) {
			throw new \Exception("Secure directory ($sec) is a link");
		}
		if (!is_dir($sec)) {
			// well, wat.
			return $modsig;
		}
		$stat = stat($sec);
		if ($stat['uid'] !== 0) {
			throw new \Exception("Secure directory ($sec) is not owned by root");
		}

		// Validate the file
		$sigfile = "$sec/$modname.sig";

		if (is_link($sigfile)) {
			throw new \Exception("Local module signature file ($sigfile) is a link");
		}

		$sigstat = stat($sigfile);
		if ($sigstat['uid'] !== 0) {
			throw new \Exception("Local module signature file ($sigfile) is not owned by root");
		}

		// Now that everything looks sane, we can process with validating the contents of the files.
		if (!isset($modsig['hashes']["$modname.sig"])) {
			throw new \Exception("Can't find validation key in module.sig");
		}
		$vhash = $modsig['hashes']["$modname.sig"];
		$localhash = hash_file("sha256", $sigfile);
		if ($vhash !== $localhash) {
			throw new \Exception("Local hash validation failed ($vhash != $localhash)");
		}
		$localsig = $this->checkSig($sigfile);

		// if ($localsig['rawstatus']['signedby'] !== $modsig['rawstatus']['signedby']) {
		//		throw new \Exception("Module signatories differ");
		// }
		$modsig['hashes'] = $localsig['parsedout']['hashes'];
		return $modsig;
	}


	/**
	 * getKey function to download and install a specified key
	 *
	 * If no key is provided, install the FreePBX key.
	 * Throws an \Exception if unable to find the key requested
	 * @param string $key The key to get?
	 */
	public function getKey($key = null) {
		// Check our permissions
		$this->checkPermissions();

		// If we weren't given one, then load the FreePBX Key
		$key = !empty($key) ? $key : $this->freepbxkey;

		// Lets make sure we don't already have that key.
		$out = $this->runGPG("--list-keys $key");

		if ($out['exitcode'] == 0) {
			// We already have this key
			return true;
		}

		if (strlen($key) > 16) {
			$key = substr($key, -16);
		}

		if (!ctype_xdigit($key)) {
			throw new \Exception(sprintf(_("Key provided - %s - is not hex"),$key));
		}

		// Before we try online, Do we have this key in a local file?
		if ($this->getKeyFromFs($key)) {
			return true;
		}

		// No. OK, so try online.
		foreach ($this->keyservers as $ks) {
			try {
				$retarr = $this->runGPG("--keyserver $ks --recv-keys $key");
			} catch (\RuntimeException $e) {
				// Took too long. We'll just try the next one.
				continue;
			}

			if ($retarr['status'][0] == "[GNUPG:] NODATA 1") {
				// not found on this keyserver. Try the next!
				continue;
			}
			// We found it. And loaded it. Yay!
			$this->checkPermissions();
			return true;
		}

		// We weren't able to find it.
		throw new \Exception(sprintf(_("Unable to download GPG key %s, or find %s or %s"), $key, $longkey, $shortkey));
	}

	/**
	 * revokeKey function
	 *
	 * Marks a key as untrusted/revoked
	 */
	private function revokeKey($key = null) {
		if ($key === null) {
			throw new \Exception(_("Cannot revoke null key!"));
		}
		throw new \Exception(_("Key revokation/trust removal is not currently implemented."));
	}

	/**
	 * trustKey function
	 *
	 * Marks a key as ultimately trusted
	 */
	private function trustKey($key = null) {
		if ($key === null) {
			throw new \Exception(_("Cannot trust null key!"));
		}

		// We need to trust this Key
		$stdout[] = $key.":6:";
		$stdout[] = "# Trailing comment";
		// Create our temporary file.
		$fd = fopen("php://temp", "r+");
		fwrite($fd, join("\n", $stdout));
		fseek($fd, 0);
		$out = $this->runGPG("--import-ownertrust", $fd);
		if ($out['exitcode'] != 0) {
			throw new \Exception(sprintf(_("Unable to trust the FreePBX Key! -- %s"),json_encode($out)));
		}
		fclose($fd);
	}

	/**
	 * trustFreePBX function
	 *
	 * Specifically marks the FreePBX Key and other known-trusted keys as ultimately trusted
	 */
	public function trustFreePBX() {
		// Grab the keys, if we don't have them already
		foreach(array_merge(array($this->freepbxkey), $this->trustkeys) as $currentkey) {
			$this->getKey($currentkey);
		}

		// Ensure the FreePBX Key is trusted.
		$out = $this->runGPG("--export-ownertrust");
		$stdout = explode("\n", $out['stdout']);
		array_pop($stdout); // Remove trailing blank line.
		if (isset($stdout[0]) && strpos($stdout[0], "# List of assigned trustvalues") !== 0) {
			throw new \Exception(sprintf(_("gpg --export-ownertrust didn't return sane stuff - %s"), json_encode($out)));
		}

		$ownertrusts = array();
		foreach ($stdout as $line) {
			if (!$line || $line[0] == "#") {
				continue;
			}

			// We now have a trust line that looks like "0BDE0BFA09946D732091E26E1588A7366BD35B34:6:"
			$trust = explode(':', $line);
			$ownertrusts[] = $trust[0];
		}

		$needtrust = array_diff(array_merge(array($this->freepbxkey), $this->trustkeys), $ownertrusts);
		if (!empty($needtrust)) {
			foreach($needtrust as $currentkey) {
				$this->trustKey($currentkey);
			}
		}

		/*
		 * While we're here, let's add the structure to revoke keys.
		 * We may want this in the future.
		 */
		$needrevoke = array_intersect($ownertrusts, $this->revokedkeys);
		if (!empty($needrevoke)) {
			foreach($needrevoke as $currentkey) {
				$this->revokeKey($currentkey);
			}
		}

		// Ensure no permissions have been changed
		$this->checkPermissions();
		return true;
	}

	/**
	 * Strips signature from .gpg file
	 *
	 * This saves the file, minus the .gpg extension, to the same directory
	 * the .gpg file is in. It returns the filename of the output file if
	 * valid, throws an \Exception if unable to validate
	 * @param string $filename The filename to check
	 */
	public function getFile($filename) {
		// Trust that we have the key?

		if (substr($filename, -4) == ".gpg") {
			$output = substr($filename, 0, -4);
		} else {
			throw new \Exception(_("I can only do .gpg files at the moment"));
		}

		$out = $this->runGPG("--batch --yes --out $output --decrypt $filename");
		if ($out['exitcode'] == 0) {
			return $output;
		}
		throw new \Exception(sprintf(_("Unable to strip signature - result was: %s"),json_encode($out)));
	}

	/**
	 * Actually run GPG
	 * @param string Params to pass to gpg
	 * @param fd File Descriptor to feed to stdin of gpg
	 * @return array returns assoc array consisting of (array)status, (string)stdout, (string)stderr and (int)exitcode
	 */
	public function runGPG($params, $stdin = null) {

		// Ensure our proxy settings are set, if needed.
		\FreePBX::Curl()->setEnvVariables();

		$fds = array(
			array("file", "/dev/null", "r"), // stdin
			array("pipe", "w"), // stdout
			array("pipe", "w"), // stderr
			array("pipe", "w"), // Status
		);

		// If we need to send stuff to stdin, then do it!
		if ($stdin) {
			$fds[0] = $stdin;
		}

		$webuser = \FreePBX::Config()->get('AMPASTERISKWEBUSER');
		$gpgdir = $this->getGpgLocation();
		$homediropt = "--homedir $gpgdir";
		$home = preg_replace('/\/\.gnupg$/', '', $gpgdir);

		// We need to ensure that our environment variables are sane.
		// Luckily, we know just the right things to say...
		if (!isset($this->gpgenv)) {
			$this->gpgenv['PATH'] = "/bin:/usr/bin:/usr/local/bin";
			$this->gpgenv['USER'] = $webuser;
			$this->gpgenv['HOME'] = $home;
			if (file_exists('/bin/bash')) {
				$this->gpgenv['SHELL'] = "/bin/bash";
			} elseif (file_exists('/usr/local/bin/bash')) {
				$this->gpgenv['SHELL'] = "/usr/local/bin/bash";
			} else {
				$this->gpgenv['SHELL'] = "/bin/sh";
			}
		}

		$cmd = $this->gpg." $homediropt ".$this->gpgopts." --status-fd 3 $params";
		$proc = proc_open($cmd, $fds, $pipes, "/tmp", $this->gpgenv);

		if (!is_resource($proc)) { // Unable to start!
			freepbx_log(FPBX_LOG_FATAL, "Tried to run command and failed: " . $cmd);
			throw new \Exception(sprintf(_("Unable to start GPG, the command was: [%s]"),$cmd));
		}

		// Wait $timeout seconds for it to finish.
		$tmp = null;
		$r = array($pipes[3]);
		if (!stream_select($r , $tmp, $tmp, $this->timeout)) {
			freepbx_log(FPBX_LOG_FATAL, "GPG command took too long to run. Command was '$cmd'");
			throw new \RuntimeException(sprintf(_("GPG took too long to run the command: [%s]"),$cmd));
		}
		// We grab stdout and stderr first, as the status fd won't
		// have completed and closed until those FDs are emptied.
		$retarr['stdout'] = stream_get_contents($pipes[1]);
		$retarr['stderr'] = stream_get_contents($pipes[2]);

		$status = explode("\n", stream_get_contents($pipes[3]));
		array_pop($status);  // Remove trailing blank line
		$retarr['status'] = $status;
		$exitcode = proc_close($proc);
		$retarr['exitcode'] = $exitcode;

		return $retarr;
	}

	/**
	 * Return array of all of my private keys
	 */
	public function getMyKeys() {
		$out = $this->runGPG("-K --with-colons");
		$keys = explode("\n", $out['stdout']);
		array_pop($keys);

		$mykeys = array();
		foreach ($keys as $k) {
			$line = explode(":", $k);
			if ($line[0] == "sec") { // This is a key!
				$mykeys[] = $line[4];
			}
		}
		return $mykeys;
	}

	/**
	 * Get list of files in a directory
	 * @param string $dir The directory to get the file list of/from
	 */
	private function getFileList($dir) {
		// When we require PHP5.4, use RecursiveDirectoryIterator.
		// Until then..

		$retarr = array();
		$this->recurseDirectory($dir, $retarr, strlen($dir)+1);
		return $retarr;
	}

	/**
	 * Recursive routine for getFileList
	 * @param string $dir The directory to recurse into
	 * @param array $retarry The returned array
	 * @param string $strip What to strip off of the directory
	 */
	private function recurseDirectory($dir, &$retarr, $strip) {

		$dirarr = scandir($dir);
		foreach ($dirarr as $d) {
			// Always exclude hidden files.
			if ($d[0] == ".") {
				continue;
			}
			$fullpath = "$dir/$d";

			if (is_dir($fullpath)) {
				$this->recurseDirectory($fullpath, $retarr, $strip);
			} else {
				$retarr[] = substr($fullpath, $strip);
			}
		}
	}

	/**
	 * Generate list of hashes to validate
	 * @param string $dir the directory
	 */
	public function getHashes($dir) {
		if (!is_dir($dir)) {
			throw new \Exception(sprintf(_("getHashes was given %s which is not a directory!"),$dir));
		}

		$hasharr = array();

		$files = $this->getFileList($dir);
		foreach ($files as $file) {
			$hasharr[$file] = hash_file('sha256', "$dir/$file");
		}

		return $hasharr;
	}

	/**
	 * Refresh all stored keys
	 */
	public function refreshKeys() {
		//combine all of our known local keys
		$fskeys = array_unique(array_merge(array($this->freepbxkey), $this->trustkeys, $this->fskeys));
		foreach($fskeys as $key) {
			//import our local keys from our filesystem
			$this->getKeyFromFs($key);
		}

		//list out all the keys we do know about
		$out = $this->runGPG("--list-keys --with-colons --fingerprint");
		$lines = explode("\n",$out['stdout']);
		$refreshKeys = array();
		foreach($lines as $line) {
			//https://git.gnupg.org/cgi-bin/gitweb.cgi?p=gnupg.git;a=blob_plain;f=doc/DETAILS
			//scrub out the fingerprint
			if(preg_match('/^fpr:::::::::([0-9a-z]{40}):$/i', $line, $key)) {
				//dont refresh our local keys
				if(in_array($key[1],$fskeys, true)) {
					continue;
				}
				$refreshKeys[] = $key[1];
			}
		}

		if(empty($refreshKeys)) {
			return true;
		}

		foreach ($this->keyservers as $ks) {
			try {
				$retarr = $this->runGPG("--keyserver $ks --refresh-keys ".implode(" ",$refreshKeys));
			} catch (\RuntimeException $e) {
				freepbx_log(FPBX_LOG_ERROR, "Tried to refresh keys and failed with: ".$e->getMessage());
				// Took too long. We'll just try the next one.
				continue;
			}
			if ($retarr['exitcode'] > 0) {
				freepbx_log(FPBX_LOG_ERROR, "Tried to refresh keys and failed with: ".$retarr['stderr']);
				//There was some sort of error so try the next one
				continue;
			} else {
				$this->checkPermissions();
				return true;
			}
		}
		return false;
	}

	/**
	 * Check the module.sig file
	 *
	 * If it's valid, return the processed contents of the sig file.
	 * If it's not valid, return false.
	 * @param string $sigfile The signature file we will check against
	 */
	public function checkSig($sigfile) {
		if (!is_file($sigfile)) {
			throw new \Exception(sprintf(_("checkSig was given %s, which is not a file"),$sigfile));
		}

		$out = $this->runGPG("--output - ".escapeshellarg($sigfile));
		
		// Check to see if we don't know about this signature..
		for($i=1;$i<count($out['status']);$i++) {
			if ( preg_match('/NO_PUBKEY (.+)/', $out['status'][$i], $keyarr) ) {
				// We don't. Try to grab it.
				try {
					$this->getKey($keyarr[1]);
				} catch (\Exception $e) {
					// Couldn't download the key.
					return array("status" => self::STATE_INVALID);
				}
				// And now run the validation again.
				$out = $this->runGPG("--output - ".escapeshellarg($sigfile));
			}
		}

		$status = $this->checkStatus($out['status']);
		if (!$status['trust']) {
			$sigout = $this->runGPG("--keyid-format long --with-colons --check-sigs ".escapeshellarg($status['signedby']));
			$longkeys = array();
			foreach (array_merge(array($this->freepbxkey),$this->trustkeys) as $currentkey) {
				$longkeys[] = substr($currentkey, -16);
			}
			foreach ($longkeys as $currentkey) {
				if(preg_match('/^rev:!::1:'.$currentkey.'/m',$sigout['stdout'])) {
					return array("status" => self::STATE_REVOKED, 'trustdetails' => array("Signed by Revoked Key"));
				}
			}
			//locally signed
			$status['parsedout'] = parse_ini_string($out['stdout'], true);
			return $status;
		}

		$modules = parse_ini_string($out['stdout'], true);
		$modules['rawstatus'] = $status;
		return $modules;
	}


	/**
	 * Check the return status of GPG to validate
	 * a signature
	 * @param string $status the status to check
	 */
	private function checkStatus($status) {
		if (!is_array($status)) {
			throw new \Exception(_("No status was given to checkStatus"));
		}

		$retarr['valid'] = false;
		$retarr['trust'] = false;
		$retarr['trustdetails'] = array();
		$retarr['status'] = 0;

		foreach ($status as $l) {
			if (strpos($l, "[GNUPG:] VALIDSIG") === 0) {
				$retarr['valid'] = true;
				$retarr['status'] |= GPG::STATE_GOOD;
				$tmparr = explode(' ', $l);
				$retarr['signedby'] = $tmparr[2];
				$retarr['timestamp'] = $tmparr[4];
			}
			if (strpos($l, "[GNUPG:] BADSIG") === 0) {
				$retarr['trustdetails'][] = "Bad Signature, Tampered! ($l)";
				$retarr['status'] |= GPG::STATE_TAMPERED;
			}
			if (strpos($l, "[GNUPG:] TRUST_UNDEFINED") === 0) {
				$retarr['trustdetails'][] = "Signed by unknown, untrusted key.";
				$retarr['status'] |= GPG::STATE_TAMPERED;
				$retarr['status'] |= GPG::STATE_INVALID;
			}
			if (strpos($l, "[GNUPG:] ERRSIG") === 0) {
				$retarr['trustdetails'][] = "Unknown Signature ($l)";
				$retarr['status'] |= GPG::STATE_INVALID;
			}
			if (strpos($l, "[GNUPG:] EXPKEYSIG") === 0) {
				$retarr['trustdetails'][] = "Signed by Expired Key ($l)";
				$retarr['status'] |= GPG::STATE_EXPIRED;
			}
			if (strpos($l, "[GNUPG:] TRUST_ULTIMATE") === 0 || strpos($l, "[GNUPG:] TRUST_FULLY") === 0) {
				$retarr['trust'] = true;
				$retarr['status'] |= GPG::STATE_TRUSTED;
			}
		}
		return $retarr;
	}

	public function getGpgLocation() {
		// Re #7429 - Always use the AMPASTERISKWEBUSER homedir for gpg
		$webuser = \FreePBX::Config()->get('AMPASTERISKWEBUSER');

		if (!$webuser) {
			throw new \Exception(_("I don't know who I should be running GPG as."));
		}

		// We need to ensure that we can actually read the GPG files.
		$web = posix_getpwnam($webuser);
		if (!$web) {
			throw new \Exception(sprintf(_("I tried to find out about %s, but the system doesn't think that user exists"),$webuser));
		}
		$home = trim($web['dir']);
		if (!is_dir($home)) {
			// Well, that's handy. It doesn't exist. Let's use ASTSPOOLDIR instead, because
			// that should exist and be writable.
			$home = \FreePBX::Config()->get('ASTSPOOLDIR');
			if (!is_dir($home)) {
				// OK, I give up.
				throw new \Exception(sprintf(_("Asterisk home dir (%s) doesn't exist, and, ASTSPOOLDIR doesn't exist. Aborting"),$home));
			}
		}

		// If $home doesn't end with /, add it.
		if (substr($home, -1) != "/") {
			$home .= "/";
		}

		// Make sure that home exists
		if (!is_dir($home)) {
			$ret = @mkdir($home);
			if (!$ret) {
				throw new \Exception(sprintf(_("Home directory %s doesn't exist, and I can't create it"),$home));
			}
		}

		$dir = $home.".gnupg";

		if (!is_dir($dir)) {
			// That's worrying. Can I make it?
			$ret = @mkdir($dir);
			if (!$ret) {
				throw new \Exception(sprintf(_("Directory %s doesn't exist, and I can't make it (getGpgLocation)."),$dir));
			}
		}

		if (is_writable($dir)) {
			return $dir;
		} else {
			throw new \Exception(sprintf(_("Don't have permission/can't write to %s"),$dir));
		}
	}

	private function checkPermissions($dir = false) {
		if (!$dir) {
			// No directory specified. Let's use the default.
			$dir = $this->getGpgLocation();
		}

		// If it ends in a slash, remove it, for sanity
		$dir = rtrim($dir, "/");

		if (!is_dir($dir)) {
			// That's worrying. Can I make it?
			$ret = @mkdir($dir);
			if (!$ret) {
				throw new \Exception(sprintf(_("Directory %s doesn't exist, and I can't make it. (checkPermissions)"),$dir));
			}
		}

		// Now, who should be running gpg normally?
		$freepbxuser = \FreePBX::Config()->get('AMPASTERISKWEBUSER');
		$pwent = posix_getpwnam($freepbxuser);
		$uid = $pwent['uid'];
		$gid = $pwent['gid'];

		// What are the permissions of the GPG home directory?
		$stat = stat($dir);
		if ($uid != $stat['uid']) {
			// Permissions are wrong on the GPG directory. Hopefully, I'm root, so I can fix them.
			if (posix_geteuid() !== 0) {
				throw new \Exception(sprintf(_("Permissions error on directory %s (is %s:%s, should be %s:%s)- please run 'fwconsole chown' as root to repair"),$dir, $stat['uid'], $stat['gid'], $uid, $gid));
			}
			// We're root. Yay.
			chown($dir, $uid);
			chgrp($dir, $gid);
		}

		// Check the permissions of the files inside the .gpg directory
		$allfiles = glob($dir."/*");
		foreach ($allfiles as $file) {
			$stat = stat($file);
			if ($uid != $stat['uid']) {
				// Permissions are wrong on the file inside the .gnupg directory.
				if (posix_geteuid() !== 0) {
					throw new \Exception(sprintf(_("Permissions error on %s - please run 'fwconsole chown' as root to repair"),$dir));
				}
				// We're root. Yay.
				chown($file, $uid);
				chgrp($file, $gid);
			}
		}
	}

	private function getKeyFromFs($key) {
		// Try in the BMO directory first
		$longkey = __DIR__."/${key}.key";
		if (file_exists($longkey)) {
			$out = $this->runGPG("--import $longkey");
			$this->checkPermissions();
			return true;
		}
		// Short?
		$shortkey = __DIR__."/".substr($key, -16).".key";
		if (file_exists($shortkey)) {
			$out = $this->runGPG("--import $shortkey");
			$this->checkPermissions();
			return true;
		}
		// Shorter?
		$shortkey = __DIR__."/".substr($key, -8).".key";
		if (file_exists($shortkey)) {
			$out = $this->runGPG("--import $shortkey");
			$this->checkPermissions();
			return true;
		}
		//  OK, now look in the proper directory
		$longkey = $this->keydir."/${key}.key";
		if (file_exists($longkey)) {
			$out = $this->runGPG("--import $longkey");
			$this->checkPermissions();
			return true;
		}
		// Short?
		$shortkey = $this->keydir."/".substr($key, -16).".key";
		if (file_exists($shortkey)) {
			$out = $this->runGPG("--import $shortkey");
			$this->checkPermissions();
			return true;
		}
		// Shorter?
		$shortkey = $this->keydir."/".substr($key, -8).".key";
		if (file_exists($shortkey)) {
			$out = $this->runGPG("--import $shortkey");
			$this->checkPermissions();
			return true;
		}

		// Didn't find it on the filesystem. Continue on..
		return false;
	}
}
