<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright (C) 2014 Schmooze Com Inc.
//

// TODO: Is kept since there is no hook (Class Extensions in Framework:BMO)
function featurecodeadmin_check_extensions($exten=true) {
	return \FreePBX::Featurecodeadmin()->destinations_check_extensions($exten);
}

// TODO: There is no hook on the _redirect_standard_helper function in the view.functions.php file.
function featurecodeadmin_getdest($exten) {
	return array(\FreePBX::Featurecodeadmin()->getDest($exten));
}

?>