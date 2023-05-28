<?php
//
// License for all code of this FreePBX module can be found in the license file inside the module directory
// Copyright 2006-2014 Schmooze Com Inc.

class DashboardHooks {

	private static $pages = array();

	public static function genHooks($order) {
		self::$pages[] = array("pagename" => "Main", "entries" => self::getMainEntries($order));
		self::addExtraPages();
		return self::$pages;
	}

	private static function getMainEntries($order) {
		// If we have a registered system, change the layout.
		if (!function_exists('sysadmin_get_license')) {
			$dir= FreePBX::create()->Config->get_conf_setting('AMPWEBROOT');
			if (file_exists($dir."/admin/modules/sysadmin/functions.inc.php")) {
				// Woo. Lets load it!
				include $dir."/admin/modules/sysadmin/functions.inc.php";
			}
		}
		$reged = (function_exists('sysadmin_get_license') && sysadmin_get_license());

		$sections = array();
		foreach(glob(dirname(__DIR__).'/sections/*.class.php') as $file) {
			$class = "\\FreePBX\\modules\\Dashboard\\Sections\\".str_replace('.class','',pathinfo($file,PATHINFO_FILENAME));
			if (!class_exists($class)) {
				include $file ;
			}
			$class = new $class();
			foreach($class->getSections($order) as $section) {
				//avoid duplicate orders
				while(isset($sections[$section['order']])) {
					$section['order']++;
				}
				$sections[$section['order']] = array("group" => $section['group'], "title" => $section['title'], "width" => $section['width'], "rawname" => $class->rawname, "section" => $section['section']);
			}
		}
		ksort($sections);

		return array_values($sections);
	}

	private static function addExtraPages() {
		/*
		// No support for extra pages yet
		$retarr = array("pagename" => "FreePBX HA", "entries" =>
			array(100 => array("group" => "Status", "title" => "High Availability Status", "width" => 12, "func" => "freepbx_ha_status"))
		);
		self::$pages[] = $retarr;
		*/
		return false;
	}

	public static function runHook($hook) {
		if (strpos($hook, "builtin_") === 0) {
			// It's a builtin module.
			return FreePBX::create()->Dashboard->doBuiltInHook($hook);
		}

		if (strpos($hook, "freepbx_ha_") === 0) {
			return "This is not the hook you want";
		}

		throw new Exception("Extra hooks not done yet");
	}
}
