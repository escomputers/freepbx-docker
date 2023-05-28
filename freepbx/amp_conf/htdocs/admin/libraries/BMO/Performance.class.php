<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * Performance logging
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
namespace FreePBX;
class Performance {

	private $doperf = false;
	private $lasttick = false;
	private $lastmem = false;
	private $mode = 'print';

	private $current = array();

	/**
	 * Turn Performance Logging on
	 */
	public function On($mode='print',$lasttick=null) {
		switch($mode) {
			case 'print':
				$this->mode = 'print';
			break;
			case 'dbug':
			default:
				$this->mode = 'dbug';
			break;
		}
		$this->doperf = true;
		if(!empty($lasttick) && function_exists('bcadd')) {
			list($msec, $utime) = explode(' ', $lasttick);
			$this->lasttick = bcadd($msec, $utime, 6);
		}
		$this->Stamp("~==========~Starting Performance tuning~==========~","GLOBAL START");
	}

	/**
	 * Turn Performance logging off
	 */
	public function Off() {
		if($this->doperf) {
			$this->Stamp("~==========~Stopping Performance tuning~==========~","GLOBAL STOP");
		}
		$this->doperf = false;
	}

	/**
	 * Generate a stamp to the output
	 *
	 * Prints out microtime and memory usage from PHP
	 * Note that the PHP Compiler optimizes this specific code
	 * extremely well. Don't stress about adding lots of calls
	 * to Performace->Stamp(), it won't cause any issues if
	 * $this->doperf is false.
	 *
	 * @param {string} $str The stamp send out
	 * @example "PERF/$str/".microtime()."/".memory_get_usage()."\n"
	 */
	public function Stamp($str, $type = "PERF", $from = false) {
		if (!$this->doperf) {
			return;
		}

		$mem = memory_get_usage();

		// Have we been given something to calculate from?
		if (is_array($from)) {
			$timefrom = $from['now'];
			$memfrom = $from['mem'];
		} else {
			$timefrom = $this->lasttick;
			if ($this->lastmem === false) {
				$this->lastmem = $mem;
			}
			$memfrom = $this->lastmem;
		}

		$now = microtime(); // String. Not float.

		// Let's try to be sensible here. If they don't have the php-bcmath stuff,
		// then don't even bother. It's too hard.
		if (function_exists('bcadd')) {
			// Yay. They do.
			list($msec, $utime) = explode(' ', $now);
			$now = bcadd($msec, $utime, 6);
			if ($timefrom === false) {
				$this->lasttick = $now;
				$timefrom = $now;
			}
			$timediff = bcsub($now, $timefrom, 6);
		} else {
			// No arbitrary precision maths. Don't even try.
			$timediff = "ERROR_INSTALL_PHP-BCMATH";
		}

		$memdiff = $mem - $memfrom;

		$this->lasttick = $now;
		$this->lastmem = $mem;

		if($this->mode == 'print') {
			print "$type/$str/$now,$timediff/$mem,$memdiff<br/>\n";
		} elseif($this->mode == 'dbug') {
			$backtrace = debug_backtrace();
			dbug(array(
				"type" => $type,
				"str" => $str,
				"now" => $now,
				"timediff" => $timediff,
				"mem" => $this->formatBytes($mem),
				"memdiff" => $this->formatBytes($memdiff),
				"file" => $backtrace[1]['file'].":".$backtrace[1]['line']
			));
		}

		// This is grabbed by Start and Stop.
		return array("now" => $now, "mem" => $mem, "str" => $str);
	}

	/**
	 * Start a performance counter
	 *
	 * Prints a timestamp, and records the start time and memory use.
	 */

	public function Start($str = false) {
		if (!$this->doperf) {
			return;
		}

		if (!$str) {
			$str = "Unknown! ".json_encode(debug_backtrace());
		}

		if (isset($this->current[$str])) {
			throw new \Exception("Start was called twice with the same key '$str'");
		}

		$this->current[$str] = $this->Stamp($str, "START");
		return true;
	}

	/**
	 * Stop a performance counter
	 *
	 * Prints a timestamp, and the difference between when it was started and now.
	 * Note that time is not automatically calculated if php-bcmath is not installed,
	 * and will need to be done manually.
	 */

	public function Stop($str = false) {
		if (!$this->doperf) {
			return;
		}

		if (!$str) {
			// array_pop ALWAYS RETURNS the last variable added to an array.
			// Well, it does in this version of php. It may break in another.
			// There is a test for this in framework/utests.
			$start = array_pop($this->current);
			$str = $start['str'];
		} else {
			if (!isset($this->current[$str])) {
				throw new \Exception("Unable to find START for $str");
			}
			$start = $this->current[$str];
			unset($this->current[$str]);
		}

		$this->Stamp($str, "STOP", $start);
	}

	private function formatBytes($bytes, $precision = 6) {
		$n = "";
		if($bytes < 0) {
			$bytes = abs($bytes);
			$n = "-";
		}
		$units = array('B', 'KB', 'MB', 'GB', 'TB');

		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);

		// Uncomment one of the following alternatives
		$bytes /= pow(1024, $pow);
		// $bytes /= (1 << (10 * $pow));
		return $n.round($bytes, $precision) . ' ' . $units[$pow];
}
}
