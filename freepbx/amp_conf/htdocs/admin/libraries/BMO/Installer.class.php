<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * This is the first stage of the installer rewrite.
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
namespace FreePBX;
class Installer {

	private $agidir;
	private $varlibdir;
	private $mohdir;
	private $etcdir;
	private $logdir;
	private $moddir;
	private $rundir;
	private $spooldir;
	private $soundsdir;

	private $webroot;
	private $sbindir;
	private $bindir;

	public function __construct($test = false) {
		// Asterisk Directories
		$this->agidir = \FreePBX::Config()->get('ASTAGIDIR');
		$this->varlibdir = \FreePBX::Config()->get('ASTVARLIBDIR');
		$moh = \FreePBX::Config()->get('MOHDIR');
		$this->mohdir = $this->varlibdir . "/" . (!empty($moh) ? $moh : "moh");
		$this->etcdir = \FreePBX::Config()->get('ASTETCDIR');
		$this->logdir = \FreePBX::Config()->get('ASTLOGDIR');
		$this->moddir = \FreePBX::Config()->get('ASTMODDIR');
		$this->rundir = \FreePBX::Config()->get('ASTRUNDIR');
		$this->spooldir = \FreePBX::Config()->get('ASTSPOOLDIR');

		$this->webroot = \FreePBX::Config()->get('AMPWEBROOT');
		$this->sbindir = \FreePBX::Config()->get('AMPSBIN');
		$this->bindir = \FreePBX::Config()->get('AMPBIN');


		$vars = array("agidir", "varlibdir", "mohdir", "etcdir", "logdir", "moddir", "rundir", "spooldir", "webroot", "sbindir", "bindir");
		foreach ($vars as $v) {
			if (empty($this->$v)) {
				throw new \Exception("I couldn't find $v");
			}
			if (substr($this->$v, -1) != "/") { // If it doesn't end with a slash
				$this->$v = $this->$v."/"; // Add it.
			}
		}
		// Assumptions...
		$this->soundsdir = $this->varlibdir."sounds/";
	}

	public function getDestination($modulename = false, $src = false, $validation = false) {
		if (!$modulename || !$src) {
			throw new \Exception("No modulename or source provided");
		}

		if (method_exists($this, $modulename)) {
			return $this->$modulename($src, $validation);
		}

		return $this->defaultModule($modulename, $src, $validation);
	}

	private function defaultModule($modulename, $src, $validation) {
		return $this->webroot."admin/modules/$modulename/$src";
	}

	private function framework($file, $validation) {
		// This is broken into multiple ifs as it seems to be more readable that way.
		if (substr($file,0,16) == "amp_conf/astetc/") {
			// If we're validating, then we don't want to check ANY of these files,
			// as they will get modified
			if ($validation) {
				return false;
			} else {
				return $this->etcdir.substr($file,16);
			}
		} elseif (substr($file,0,16) == "amp_conf/sounds/") {
			return $this->soundsdir.substr($file,16);
		} elseif (substr($file,0,13) == "amp_conf/moh/") {
			// If we're validating, then we don't want to check ANY of these files,
			// as they will get modified
			if ($validation) {
				return false;
			} else {
				return $this->mohdir.substr($file,13);
			}
		} elseif (substr($file,0,14) == "amp_conf/sbin/") {
			return $this->sbindir.substr($file,14);
		} elseif (substr($file,0,13) == "amp_conf/bin/") {
			return $this->bindir.substr($file,13);
		} elseif (substr($file,0,16) == "amp_conf/htdocs/") {
			return $this->webroot.substr($file,16);
		} elseif (substr($file,0,17) == "amp_conf/agi-bin/") {
			return $this->agidir.substr($file,17);
		} elseif (substr($file,0,9) == "upgrades/") {
			return false;  // Don't install. This is only needed as part of the installer
		}
		// Everything else isn't moved.
		return $this->defaultModule("framework", $file, $validation);
	}
}
