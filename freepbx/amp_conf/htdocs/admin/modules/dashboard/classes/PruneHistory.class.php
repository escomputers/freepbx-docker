<?php
// vim: set ai ts=4 sw=4 ft=php:
//
// License for all code of this FreePBX module can be found in the license file inside the module directory
// Copyright 2006-2014 Schmooze Com Inc.

class PruneHistory {

	private $db = false;
	private $dash = false;
	private $periods = array('MINUTES' => 60, 'HALFHR' => 1800, 'HOUR' => 3600, 'QTRDAY' => 21600, 'DAY' => 86400);
	// Keeping 1 hour of Minutes, 1 day of Half Hours, 2 Days of Hours, 1 Week of Quarter Days, 3 months of Days.
	private $keep = array('MINUTES' => 60, 'HALFHR' => 48, 'HOUR' => 48, 'QTRDAY' => 28, 'DAY' => 70);
	private $last = false;
	private $pnext = array();

	public function __construct($freepbx) {
		$db = $freepbx->Database;
		$dash = $freepbx->Dashboard;

		if (!is_object($db)) {
			throw new Exception("DB isn't a Database?");
		}
		if (!is_object($dash)) {
			throw new Exception("Dash isn't an Object?");
		}

		$this->db = $db;
		$this->dash = $dash;

		// Generate a temporary 'what's next' array for lookups.
		foreach ($this->periods as $p => $i) {
			if ($this->last) {
				$this->pnext[$this->last] = $p;
				$this->last = $p;
			} else {
				$this->last = $p;
			}
		}
	}

	public function getPeriodBase($t, $period) {
		// Find the base period to work from.
		if (!isset($this->periods[$period])) {
			throw new Exception("Unknown period $period");
		}
		if (!$t) {
			throw new Exception("Wasn't given a time");
		}

		// Now, find the base time for this period.
		$base = $t % $this->periods[$period];
		return $t - $base;
	}

	public function doPrune() {
		foreach (array_keys($this->periods) as $p) {
			if ($p != $this->last) {
				$this->avgPeriod($p);
			}
			// Now it's saved, we can delete them from the old period
			// How many do we want to keep?
			if (!isset($this->keep[$p])) {
				throw new Exception("Don't know how many $p periods to keep");
			}
			$cutoff = time() - $this->keep[$p] * $this->periods[$p];
			$keys = $this->dash->getAllKeys($p);

			foreach ($keys as $k) {
				if ($k < $cutoff) {
					$this->dash->setConfig($k, false, $p);
				}
			}
		}
	}

	public function avgPeriod($p) {
		if (!isset($this->periods[$p])) {
			throw new Exception("Unknown period $p");
		}

		if (!isset($this->pnext[$p])) {
			throw new Exception("Don't know the next one for $p\n");
		} else {
			$next = $this->pnext[$p];
		}

		// Grab all the keys for this period
		$keys = $this->dash->getAllKeys($p);
		if (!isset($keys[0])) {
			// Huh. No results? New install possibly?
			return;
		}

		sort($keys);

		// This is the first period that our entries will be
		// averaged into
		$currentperiod = $this->getPeriodBase($keys[0], $next);

		// Ignore any that haven't completed a full $next period yet
		$ignoreafter = $this->getPeriodBase(time(), $next);

		$currentarr = array();

		$commitable = array();

		// Now, go through our keys
		foreach ($keys as $t) {

			if ($t > $ignoreafter) {
				continue;
			}

			// Are we in a different period?
			if ($currentperiod != $this->getPeriodBase($t, $next)) {
				// We need to submit the current array
				$commitable[] = array('currentarr' => $currentarr, 'currentperiod' => $currentperiod, 'period' => $p);
				// Then reset and continue.
				$currentarr = array($t);
				$currentperiod = $this->getPeriodBase($t, $next);
			} else {
				// We're in the same period. Add it to the array
				$currentarr[] = $t;
			}
		}

		usort($commitable,function($a,$b) {
			if ($a['currentperiod'] == $b['currentperiod']) {
				return 0;
			}
			return ($a['currentperiod'] > $b['currentperiod']) ? -1 : 1;
		});

		//Only update the lastest current period per period.
		//Updating anything else is a waste of db transactions because
		//the database never changes
		if(!empty($commitable[0])) {
			$this->commitAvg($commitable[0]['currentarr'], $commitable[0]['currentperiod'], $commitable[0]['period']);
		}
	}

	public function commitAvg($arr, $currentperiod, $period) {
		$z = $this->calcAverages($arr, $period);
		if (!isset($this->pnext[$period])) {
			throw new Exception("How did you get here with an invalid next period?");
		}
		$next = $this->pnext[$period];
		$this->dash->setConfig($currentperiod, $z, $next);
	}

	public function calcAverages($timestamps, $id) {

		$myarr = array();
		$avgs = array();
		$totals = array();
		$retarr = array();

		// Grab all our entries.
		foreach ($timestamps as $ts) {
			$ret = $this->dash->getConfig($ts, $id);
			if ($ret === false) {
				throw new Exception("Severe Error getting $ts. This could indicate a database error");
			}
			// Check to see if values can be averaged
			foreach ($ret as $k => $v) {
				if (is_numeric($v)) {

					// First time we've found this value
					if (!isset($avgs[$k])) {
						$myarr[$k] = 0;
						$avgs[$k] = 0;
					}

					// If it's not already marked as a string, we can average it.
					if ($avgs[$k] !== false) {
						$myarr[$k] = $myarr[$k] + $v;
						$avgs[$k]++;
					}
				} else {
					// It's not a number. Stick the first one we find into the array.
					$avgs[$k] = false;
					if (!isset($retarr[$k])) {
						$retarr[$k] = $v;
					}
				}
			}
		}

		// Now we go through everything that CAN be averaged, and average it!
		foreach ($myarr as $k => $v) {
			if ($avgs[$k] == 1) {
				$retarr[$k] = $v;
			} else {
				$retarr[$k] = $v / $avgs[$k];
			}
		}

		return $retarr;

	}
}
