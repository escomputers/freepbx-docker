<?php
//
// License for all code of this FreePBX module can be found in the license file inside the module directory
// Copyright 2006-2014 Schmooze Com Inc.

class CPUInfo {

	public $systemtype = "unknown";

	public function __construct() {
		// This sets $this->systemtype
		include 'systemdetect.inc.php';
	}

	public function getAll() {
		$retarr = array();

		if ($this->systemtype == "linux") {
			$retarr['cpuinfo'] = $this->parseProcCPU();
			$retarr['loadavg'] = $this->parseProcLoadavg();
		} elseif ($this->systemtype == "freebsd") {
			$retarr['cpuinfo'] = $this->parseSysctlCPU();
			$retarr['loadavg'] = $this->parseSysctlLoadavg();
		}

		return $retarr;
	}

	private function parseProcCPU() {
		$retarr = array();
		$rawfile = file("/proc/cpuinfo", FILE_IGNORE_NEW_LINES);
		$procnum = 0;

		foreach ($rawfile as $line) {
			if (strpos($line, "processor") === 0) {
				$procnum = substr($line, 12);
				continue;
			}
			if (strpos($line, "model name") === 0) {
				$retarr[$procnum]['modelname'] = substr($line, 13);
			}
			if (strpos($line, "cpu MHz") === 0) {
				$retarr[$procnum]['mhz'] = substr($line, 11);
			}
			if (strpos($line, "physical id") === 0) {
				$socketid = (int)substr($line,13) + 1;
				$retarr['sockets'] = $socketid;
			}
		}
		$retarr['cores'] = $procnum+1;

		return $retarr;
	}

	private function parseProcLoadavg() {
		$retarr = array();

		$line = file_get_contents("/proc/loadavg");
		$arr = explode(" ", $line);
		$retarr['util1'] = $arr[0];
		$retarr['util5'] = $arr[1];
		$retarr['util15'] = $arr[2];
		$retarr['runningprocs'] = $arr[3];
		$retarr['highestpid'] = $arr[4];

		return $retarr;
	}

	private function parseSysctlCPU() {
		$retarr = array();

		$ncpu = shell_exec("sysctl -n hw.ncpu 2>/dev/null");
		$model = shell_exec("sysctl -n hw.model 2>/dev/null");

		// depending on type of machine, the cpu frequency
		// might be located via different sysctl oids
		$mhz = shell_exec("sysctl -n hw.clockrate 2>/dev/null");
		if (!$mhz) {
			$mhz = shell_exec("sysctl -n hw.freq.cpu 2>/dev/null");
		}
		if (!$mhz) {
			$mhz = "unknown";
		}

		for ($procnum = 1; $procnum <= $ncpu; $procnum++) {
			$retarr[$procnum]['modelname'] = $model;
			$retarr[$procnum]['mhz'] = $mhz;
		}
		$retarr['sockets'] = 1; // hack
		$retarr['cores'] = $ncpu;

		return $retarr;
	}

	private function parseSysctlLoadavg() {
		$retarr = array();

		$arr = sys_getloadavg();
		$retarr['util1'] = $arr[0];
		$retarr['util5'] = $arr[1];
		$retarr['util15'] = $arr[2];
		$line = shell_exec("ps -aux 2>/dev/null | wc -l");
		$retarr['runningprocs'] = trim($line);
		$lastpid = shell_exec("sysctl -n kern.lastpid 2>/dev/null");
		$retarr['highestpid'] = $lastpid;

		return $retarr;
	}
}
