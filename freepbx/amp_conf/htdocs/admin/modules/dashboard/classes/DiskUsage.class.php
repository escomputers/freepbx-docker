<?php
//
// License for all code of this FreePBX module can be found in the license file inside the module directory
// Copyright 2006-2014 Schmooze Com Inc.

class DiskUsage {

	public $systemtype = "unknown";

	public function __construct() {
		// This sets $this->systemtype
		include 'systemdetect.inc.php';
	}

	public function getAll() {
		$retarr = array();

		if ($this->systemtype == "linux") {
			$retarr['df']=$this->parsedf();
		}

		return $retarr;
	}

	public function parsedf() {
		exec("/bin/df -P -hl", $output, $retcode);
		foreach ($output as $line) {
			// If the first char isn't a /, we dont' care.
			if ($line[0] != "/") {
				continue;
			}

			$arr = preg_split('/\s+/', $line);
			$name = array_shift($arr);
			$retarr[$name]['size'] = $arr[0];
			$retarr[$name]['used'] = $arr[1];
			$retarr[$name]['avail'] = $arr[2];
			$retarr[$name]['usepct'] = $arr[3];
			$retarr[$name]['mountpoint'] = $arr[4];
		}
		return $retarr;
	}
}

