<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.

/**
 * Backward compatibility function
 * @param  string $path The path
 * @return array       Array of music categories
 */
function music_list($path=null) {
	return FreePBX::Music()->getAllMusic($path);
}
