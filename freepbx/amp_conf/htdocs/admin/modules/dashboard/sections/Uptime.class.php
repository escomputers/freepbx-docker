<?php
// vim: set ai ts=4 sw=4 ft=php:
//
// License for all code of this FreePBX module can be found in the license file inside the module directory
// Copyright 2006-2014 Schmooze Com Inc.

namespace FreePBX\modules\Dashboard\Sections;

class Uptime {
	public $rawname = 'Uptime';

	public function getSections($order) {
		return array(
			array(
				"title" => _("Uptime"),
				"group" => _("Statistics"),
				"width" => "550px",
				"order" => isset($order['uptime']) ? $order['uptime'] : '400',
				"section" => "uptime"
			)
		);
	}

	public function getContent($section) {
		if (!class_exists('\CPUInfo')) {
			include dirname(__DIR__).'/classes/CPUInfo.class.php';
		}
		if (!class_exists('\TimeUtils')) {
			include dirname(__DIR__).'/classes/TimeUtils.class.php';
		}

		$cpu = new \CPUInfo();
		$time = \TimeUtils::getReadable($this->getUptimeSecs());

		return load_view(dirname(__DIR__).'/views/sections/uptime.php',array("cpu" => $cpu->getAll(), "time" => $time));
	}

	public function getUptimeSecs() {
		if (PHP_OS == "FreeBSD") {
			$line = shell_exec("sysctl -n kern.boottime 2>/dev/null");
			$arr = explode(" ", $line);
			$boottime = trim($arr[3], ",");
			$secs = time() - $boottime;
		} else {
			$uptime = file_get_contents("/proc/uptime");
			list($secs, $null) = explode(" ", $uptime);
		}
		return round($secs);
	}
}
