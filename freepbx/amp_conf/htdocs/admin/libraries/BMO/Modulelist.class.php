<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2018 Schmooze Com Inc.
 */
namespace FreePBX;
class Modulelist {
	private $modules = array();

	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception("Need to be instantiated with a FreePBX Object");
		}
		$this->FreePBX = $freepbx;

		$this->get();
	}

	public function is_loaded() {
		return !empty($this->modules);
	}

	public function initialize($module_list) {
		// strip out extraneous fields (help especially when printing out debugs
		//
		foreach ($module_list as $mod_key => &$mod) {
			if (isset($mod['changelog'])) {
				//unset($this->module_array[$mod_key]['changelog']);
			}
			if (isset($mod['attention'])) {
				unset($mod['attention']);
			}
			if (!isset($mod['license'])) {
				$mod['license'] = '';
			}
			if (isset($mod['location'])) {
				unset($mod['location']);
			}
			if (isset($mod['md5sum'])) {
				unset($mod['md5sum']);
			}
			if (isset($mod['sha1sum'])) {
				unset($mod['sha1sum']);
			}
			if (!isset($mod['track'])) {
				$mod['track'] = 'stable';
			}
		}
		$this->FreePBX->Cache->save('modulelist_modules',$module_list);
		$this->modules = $module_list;
	}

	public function invalidate() {
		$this->FreePBX->Cache->delete('modulelist_modules');
		$this->modules = array();
	}

	public function get() {
		if(!empty($this->modules)) {
			return $this->modules;
		}
		if ($this->FreePBX->Cache->contains('modulelist_modules')) {
			$this->modules = $this->FreePBX->Cache->fetch('modulelist_modules');
			return $this->modules;
		}
		return array();
	}

	public function __get($var) {
		switch($var) {
			case 'module_array':
				return $this->get();
			break;
		}
	}
}
