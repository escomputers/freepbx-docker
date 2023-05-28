<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is part of the FreePBX Big Module Object.
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2015 Sangoma Technologies
 */
class OOBE extends FreePBX_Helpers {

	// Is the out of box experience complete?
	public function isComplete($type = "auth") {
		$complete = $this->getConfig("completed");
		if ($type == "noauth") {
			// We only care about framework if we're unauthed
			if (isset($complete['framework'])) {
				return true;
			} else {
				return false;
			}
		}
		// Otherwise....
		$pending = $this->getPendingModules($type);

		if ($pending) {
			return false;
		} else {
			return true;
		}
	}

	// Which modules have pending OOBE pages to show?
	public function getPendingModules($type = "auth") {

		if ($type == "noauth") {
			$all = array("framework" => "framework");
		} else {
			$all = $this->getOOBEModules();
		}

		if (isset($all['framework'])) {
			// Make sure we don't try to run framework when we
			// don't need to.
			if (!$this->isFrameworkOOBENeeded()) {
				$this->completeOOBE('framework');
				unset($all['framework']);
			}
		}

		$complete = $this->getConfig("completed");
		if (!is_array($complete)) {
			return $all;
		}

		// Remove ones that are complete
		foreach ($complete as $m) {
			unset($all[$m]);
		}

		return $all;
	}

	private function completeOOBE($mod = false) {
		if (!$mod) {
			throw new \Exception("No module given to mark as complete");
		}
		$complete = $this->getConfig("completed");
		if (!is_array($complete)) {
			$complete = array($mod => $mod);
		} else {
			$complete[$mod] = $mod;
		}

		$this->setConfig("completed", $complete);
	}

	// Which modules are providing OOBE pages?
	public function getOOBEModules() {
		$mods = module_functions::create();
		$i = $mods->getinfo(false, array(MODULE_STATUS_ENABLED), true);

		// Make sure framework always runs.
		$tmparr = array("framework" => 10);
		foreach ($i as $modname => $arr) {
			if (!isset($arr['oobe'])) {
				continue;
			}
			// We've found a module with OOBE hooks!
			$xml = \FreePBX::Modules()->getXML($modname);

			if (!$xml->oobe->attributes()->priority) {
				$priority = 100;
			} else {
				$priority = (int) $xml->oobe->attributes()->priority;
			}
			$tmparr[$modname] = $priority;
		}

		// Sort the list of found hooks by priority.
		asort($tmparr);
		// And add them to $retarr
		$retarr = array();
		foreach ($tmparr as $modname => $null) {
			$xml = \FreePBX::Modules()->getXML($modname);
			$retarr[$modname] = (string) $xml->name;
		}
		return $retarr;
	}

	// Call a module's OOBE Hook
	public function runModulesOOBE($modname = false) {
		if (!$modname) {
			throw new \Exception("You didn't ask for a module");
		}

		$bmo = FreePBX::create();
		$mod = ucfirst($modname);

		// Firstly. Is that module already loaded? Pretty unlikely
		// at this stage of play..
		if (!class_exists("\\FreePBX\\modules\\$mod")) {
			// Unsurprisingly, it didn't. Let's load it.
			// We need to manually load it, as the autoloader WON'T.
			$hint = FreePBX::Config()->get("AMPWEBROOT")."/admin/modules/$modname/$mod.class.php";
			$this->injectClass($mod, $hint);
		}

		// Now we can instantiate it
		$obj = FreePBX::$mod();

		// Awesome. Now, what was that oobe function again...?
		$xml = \FreePBX::Modules()->getXML($modname);
		$func = (string) $xml->oobe->attributes()->method;

		// Is someone taking crazy pills?
		if (!method_exists($obj, $func)) {
			// Do not i18n. This is for support, not end users.
			print "I'm sorry. The module $modname said that it was providing an OOBE, but when I actually asked it for '$func', it didn't exist. ";
			print "Please try again.\n";
			$this->completeOOBE($modname);
			return false;
		}

		// Awesome. Off you go then!
		return $obj->$func();
	}

	public function showOOBE($auth = "auth") {
		$pending = $this->getPendingModules($auth);

		// If there aren't any pending modules, return false
		// so config.php knows we didn't do anything, and can
		// continue on.
		if (!$pending) {
			return true;
		}

		$current = key($pending);

		if ($current == "framework") {
			// That's us!
			return $this->createInitalSetup($auth);
		}

		// It's an external OOBE request.
		// This displays the output (if there is any)
		$ret = $this->runModulesOOBE($current);
		// If this returns bool true, then that means it's completed
		// its processing, and didn't output anything. We can mark it
		// as complete, and proceed on to the next one.
		if ($ret === true) {
			$this->completeOOBE($current);
			return $this->showOOBE($auth);
		}

		// Otherwise, don't do anything else.
	}

	private function createInitalSetup($auth) {
		//Need to pretend we are AUTH as this is the initial setup
		if (!defined('FREEPBX_IS_AUTH')) {
			define('FREEPBX_IS_AUTH', 'TRUE');
		}
		$view = FreePBX::Config()->get("AMPWEBROOT")."/admin/views/oobe.php";
		if (!isset($_REQUEST['username'])) {
			// Just show the view
			echo load_view($view);
		} else {
			$errors = $this->validateFrameworkOOBE($results);
			if ($errors) {
				// Something failed. Try again
				$results['errors'] = $errors;
				echo load_view($view, $results);
			} else {
				$this->addInitalSettings($results);
				FreePBX::Framework()->setConfig("navbarToogle", "yes");
				$this->completeOOBE("framework");
				return $this->showOOBE($auth);
			}
		}
	}

	private function validateFrameworkOOBE(&$results) {
		if (isset($_REQUEST['username'])) {
			$username = trim($_REQUEST['username']);
		} else {
			$username = "";
		}

		if (isset($_REQUEST['password1'])) {
			$password1 = trim($_REQUEST['password1']);
		} else {
			$password1 = "";
		}

		if (isset($_REQUEST['password2'])) {
			$password2 = trim($_REQUEST['password2']);
		} else {
			$password2 = "";
		}

		if (isset($_REQUEST['email'])) {
			$email = trim($_REQUEST['email']);
		} else {
			$email = "";
		}

		if (isset($_REQUEST['system_ident'])) {
			$system_ident = trim($_REQUEST['system_ident']);
		} else {
			$system_ident = "";
		}

		$results = array();
		$errors = array();

		if(!$system_ident){
			$errors[] = _('Please enter a System Indentifier');
		}  else {
			$results['system_ident'] = $system_ident;
		}

		if (!$username){
			$errors[] = _('Please enter a username');
		}  else {
			$results['username'] = $username;
		}

		if (!$password1 || !$password2 || $password1 != $password2) {
			$errors[] = _('Password error. Please re-check');
		} elseif (strlen($password1) < 4) {
			$errors[] = _('Password too short');
		} else {
			$results['password'] = $password1;
		}

		$results['auto_system_updates'] = isset($_REQUEST['auto_system_updates']) ? $_REQUEST['auto_system_updates'] : 'disabled';
		$results['auto_module_security_updates'] = $_REQUEST['auto_module_security_updates'];
		$results['auto_module_updates'] = $_REQUEST['auto_module_updates'];
		$results['unsigned_module_emails'] = $_REQUEST['unsigned_module_emails'];
		$results['update_every'] = $_REQUEST['update_every'];
		$results['update_period'] = $_REQUEST['update_period'];

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$errors[] = _('Email addresses error');
		} else {
			$results['email'] = $email;
		}

		return $errors;
	}

	private function addInitalSettings($settings) {
		// This will never, ever, overwrite an existing admin.
		$db = FreePBX::Database();
		if (!$this->isFrameworkOOBENeeded()) {
			throw new \Exception("Tried to add an admin user, but some users ($count) already exist.");
		}

		$sth = $db->prepare("INSERT INTO `ampusers` (`username`, `password_sha1`, `sections`) VALUES ( ?, ?, '*')");

		$username = htmlentities(strip_tags($settings['username']));

		$sth->execute(array($username, sha1($settings['password'])));

		$um = new \FreePBX\Builtin\UpdateManager();
		$um->updateUpdateSettings($settings);
		$um->setNotificationEmail($settings['email']);
	}

	private function isFrameworkOOBENeeded() {
		$db = FreePBX::Database();
		$count = (int) $db->query("SELECT COUNT(`username`) FROM `ampusers`")->fetchColumn();
		if ($count !== 0) {
			return false;
		}
		return true;
	}
}
