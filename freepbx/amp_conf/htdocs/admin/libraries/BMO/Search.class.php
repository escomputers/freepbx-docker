<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */

namespace FreePBX;
class Search extends FreePBX_Helpers {

	public function ajaxRequest($cmd, &$settings) {
		//$settings['allowremote'] = true;
		//$settings['authenticate'] = false;
		return true;
	}

	public function ajaxHandler() {
		$search = $this->getSearch();
		if ($search == "global") {
			return $this->globalSearch();
		} elseif ($search == "local") {
			return $this->moduleSearch();
		} else {
			return "Derp";
		}
	}
	public function globalSearch() {
		$modules = \FreePBX::Modules()->getActiveModules();

		// If I'm in device and user mode, don't display Extensions, and vice-versa.
		$mode = \FreePBX::Config()->get('AMPEXTENSIONS');

		$retarr = array();
		foreach ($modules as $rawname => $m) {
			if (isset($m['items'])) {
				foreach ($m['items'] as $k => $v) {
					if ($mode == "deviceanduser" && $v['name'] == "Extensions") {
						continue;
					} elseif ($mode == "extensions" && ($v['name'] == "Devices" || $v['name'] == "Users")) {
						continue;
					}
					\modgettext::push_textdomain(strtolower($m['rawname']));
					$retarr[] = array("rawname" => $rawname, "rawtext" => $v['name'], "text" => _($v['name']), "type" => "get", "dest" => !empty($v['href']) ? $v['href'] : "?display=$k");
					\modgettext::pop_textdomain();
				}
			}
		}

		$hooks = $this->FreePBX->Hooks->returnHooks();
		foreach($hooks as $hook) {
			$mod = $hook['module'];
			$hook = $hook['method'];
			$out = \FreePBX::$mod()->$hook($retarr);
			if(!empty($out)) {
				$retarr = $out;
			}
		}

		return $retarr;
	}

	public function moduleSearch() {

		$results = array();

		if (!isset($_REQUEST['query'])) {
			return array();
		}
		// Make the query string usable.
		$qs = htmlentities($_REQUEST['query'], ENT_QUOTES, 'UTF-8', false);

		$mods = \FreePBX::Modules()->getModulesByMethod("search");
		foreach($mods as $mod) {
			\modgettext::push_textdomain(strtolower($mod));
			$this->FreePBX->$mod->search($qs, $results);
			\modgettext::pop_textdomain();
		}

		// Remove any results from the search that are unneeded.
		foreach ($results as $i => $r) {
			if ($r['type'] == "text" || isset($r['force'])) {
				// Always return text fields that were given back to us, or if the result
				// was forced to display.
				continue;
			}
			// We should try to use UTF-8 sensible matching if possible.
			if (function_exists("mb_stripos")) {
				if (mb_stripos($r['text'], $qs) === false) {
					// Doesn't match? Remove.
					unset($results[$i]);
				}
			} else {
				// Use UTF-8 unsafe check.
				if (stripos($r['text'], $qs) === false) {
					// Doesn't match? Remove.
					unset($results[$i]);
				}
			}
		}
		return $results;
	}

	private function getSearch() {
		if (!isset($_REQUEST['command'])) {
			return false;
		}
		return $_REQUEST['command'];
	}

	private function whichModule() {
		return "core";
	}
}
