<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
namespace FreePBX;
class FileHooks {

	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception("Need to be instantiated with a FreePBX Object");
		}

		$this->FreePBX = $freepbx;
	}

	public function processFileHooks($active_modules = null) {
		if ($active_modules == null)
			throw new \Exception("BMO Doesn't know about modules yet. It needs to be told about them");

		$this->processOldHooks($active_modules);

		$this->processNewHooks();

	}

	private function processOldHooks($active_modules) {
		if(empty($active_modules)) {
			return;
		}
		// Moved from retrieve_conf, and slightly tidied up
		foreach($active_modules as $mod) {
			\modgettext::push_textdomain(strtolower($mod));
			$classname = $mod."_conf";

			if(class_exists($classname)) {
				if (method_exists($classname, "get_filename")) {

					// Now we need one of these objects. Some of them support
					// ::create(), some don't.
					if (method_exists($classname, "create")) {
						$module = $classname::create();
					} else {
						$module = new $classname;
					}

					$fn = $module->get_filename();

					if (empty($fn)) {
						// This module doesn't want to generate a file at the moment.
						continue;
					}

					//Check if module does not want the header inserted
					$generateHeader = (isset($module->use_warning_banner) && is_bool($module->use_warning_banner)) ? $module->use_warning_banner : true;

					// if the module returns an array, it wants to write multiple files
					// ** pinsets is an example of a module that does this
					if (is_array($fn)) {
						foreach($fn as $modconf) {
							$this->FreePBX->Performance->Stamp("oldfileHook-".$modconf."_start");
							$this->FreePBX->WriteConfig->writeConfig($modconf,$module->generateConf($modconf),$generateHeader);
							$this->FreePBX->Performance->Stamp("oldfileHook-".$modconf."_stop");
						}
					} else {
						if ($module->get_filename() != "")
							$this->FreePBX->Performance->Stamp("oldfileHook-".$module->get_filename()."_start");
							$this->FreePBX->WriteConfig->writeConfig($module->get_filename(), $module->generateConf(),$generateHeader);
							$this->FreePBX->Performance->Stamp("oldfileHook-".$module->get_filename()."_stop");
					}
				}
			}
			\modgettext::pop_textdomain();
		}
	}

	private function processNewHooks() {
		$hooks = $this->FreePBX->Hooks->getAllHooks();
		if(is_array($hooks['ConfigFiles'])) {
			foreach ($hooks['ConfigFiles'] as $hook) {
				$mod = str_ireplace("FreePBX\\modules\\","",$hook);
				\modgettext::push_textdomain(strtolower($mod));
				$hparts = explode("\\",$hook);
				if(count($hparts) > 0) {
					$c = count($hparts);
					$hook = $hparts[$c-1];
				}
				$this->FreePBX->Performance->Stamp("fileHook-".$hook."_start");
				// This is where we'd hook the output of files, if it was implemented.
				// As no-one wants it yet, I'm not going to bother.
				if (!method_exists($this->FreePBX->$hook, "genConfig")) {
					throw new \Exception("$hook asked to generate a config file, but, doesn't implement genConfig()");
				}
				$tmpconf = $this->FreePBX->$hook->genConfig();

				// Here we want to hand off $tmpconf to other modules, if they somehow say they want to do something
				// with it.

				if (!method_exists($this->FreePBX->$hook, "writeConfig")) {
					throw new \Exception("$hook asked to generate a config file, but, doesn't implement writeConfig()");
				}

				$this->FreePBX->$hook->writeConfig($tmpconf);
				$this->FreePBX->Performance->Stamp("fileHook-".$hook."_stop");
				\modgettext::pop_textdomain();
			}
		}
	}
}
