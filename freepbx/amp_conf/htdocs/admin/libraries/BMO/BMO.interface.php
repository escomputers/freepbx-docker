<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
// Define and document the BMO Interface
namespace FreePBX;
// Helpers here.
// DB_Helper provides $this->getConfig and $this->setConfig
include "DB_Helper.class.php";
// Self_Helper extends DB_Helper and adds magic __get and __call
// calls, avoiding $this->FreePBX->x, So you can do $this->x
// instead
include "Self_Helper.class.php";
// Request_Helper handles $_REQUEST processing
include "Request_Helper.class.php";
// Do not randomly add helpers because you think it MAY be used
// in SOME module. A Helper should only be added if you're
// sure it's going to be used in EVERY module.  Otherwise,
// add them as a normal module.

// Note that we have to build the helpers manually. This DOES
// work with Eclipse, PHPStorm, etc.  Doing it programatically
// at runtime doesn't. I may change this, because this is awful.
// Also, before you tell me that this is a terrible way of doing
// multiple inheritances, please tell me a better way. 8-(  --Rob
// (Note: Traits in 5.4 fixes this)
class FreePBX_Helpers extends Request_Helper {
}

interface BMO  {
	// ///////////////////////////////// //
	// Installing/Upgrading/Uninstalling //
	// ///////////////////////////////// //
	//
	// Process to run when you're installed.
	public function install();
	// Note that __construct will be called with ($BMO, true) before install is to be run.
	// If an exception is thrown in here, the module will NOT be marked as installed, and
	// won't be accessable.
	//
	// Process to run when you're being uninstalled.
	public function uninstall();
	// If an exception is thrown in here, the module WILL BE marked as uninstalled, but a
	// warning will be displayed to the end user with the text of the Exception.
	//
	// Optional  /// UNIMPLEMENTED //
	// public function upgrade()
	// Is called when an Upgrade is run on the module. If this doesn't exist, install()
	// will be called.

	// ////////////////// //
	// Backup and Restore //
	// ////////////////// //
	//These methods have been removed. They will be implemented differently than
	//the original concept. Modification of the abstract now would cause the universe
	// to implode and noone wants that.

	// ////////////// //
	// FreePBX Search //
	// ////////////// //
	// public function search($request, &$results);
	// This function needs to append (or possibly alter?) $result, which is an array
	// that is handed back to the search box.

	// ////////// //
	// FreePBX UI //
	// ////////// //
	// This is called from config.php?display=thismodulename and the entire $_REQUEST is
	// passed to it. For compatibility, you can print/echo things here, and they will
	// appear in the right place, however, it should be returning an array, or an Object.
	// public function showPage($request);

	// ////////// //
	// AJAX Calls //
	// ////////// //
	//
	// These are called from ajax.php.
	// public function ajaxCall($_REQUEST);

	// ////////////////////////// //
	// Hooking into other modules //
	// ////////////////////////// //
	//
	// public function getPageHooks();
	// public function getConfigHooks();
	//
	// public function pageHook($page);
	// public function configHook($module, $config);

	// ////////////////////// //
	// Dialplan Modifications //
	// ////////////////////// //

	// //////////////////////////// //
	// Asterisk Configuration Files //
	// //////////////////////////// //
	// When the 'reload' button is clicked, genConfig will be called, the output will
	// be given to any modules that requested it, and what they return will then be
	// given to writeConfig.
	// public function genConfig();
	//
	// writeConfig should use $this->FreePBX->WriteConfig($config) which will do all
	// the actual writing of files for it.
	// See BMO/WriteConfig.class.php
	// public function writeConfig($config);
}
