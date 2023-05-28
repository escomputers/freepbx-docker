<?php
//
// License for all code of this FreePBX module can be found in the license file inside the module directory
// Copyright 2006-2014 Schmooze Com Inc.

class MemInfo {

	public $info = array();
	public $systemtype = "unknown";

	public function __construct() {
		// This sets $this->systemtype
		include 'systemdetect.inc.php';
	}

	public function getAll() {
		$retarr = array();

		if ($this->systemtype=="linux") {
			$this->parseLinuxMemInfo();
			$retarr['swap'] = $this->getSwapUsage();
			$retarr['mem'] = $this->getMemUsage();
			$retarr['raw'] = $this->info;
		}
		return $retarr;
	}

	public function getSwapUsage() {
		$retarr['free'] = $this->info['SwapFree'];
		$retarr['total'] = $this->info['SwapTotal'];
		$retarr['used'] = $retarr['total'] - $retarr['free'];
		$retarr['usedpct'] = ceil($retarr['used']/$retarr['total']/100);
		$retarr['freepct'] = 100-$retarr['usedpct'];
		return $retarr;
	}

	public function getMemUsage() {
		$retarr['free'] = $this->info['Buffers'] + $this->info['Cached'] + $this->info['MemFree'];
		$retarr['buffers'] = $this->info['Buffers'];
		$retarr['cached'] = $this->info['Cached'];
		$retarr['memfree'] = $this->info['MemFree'];
		$retarr['free'] = $this->info['Buffers'] + $this->info['Cached'] + $this->info['MemFree'];
		$retarr['total'] = $this->info['MemTotal'];
		$retarr['used'] = $retarr['total'] - $retarr['free'];
		return $retarr;
	}

	public function parseLinuxMemInfo() {

		$rawfile = file("/proc/meminfo", FILE_IGNORE_NEW_LINES);

		foreach ($rawfile as $line) {
			if (preg_match('/([\w_\(\)]+):\s+(\d+) kB/', $line, $out)) {
				$this->info[$out[1]] = $out[2];
			}
		}
	}
}
