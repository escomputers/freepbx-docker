<?php

$ampwebroot = \FreePBX::Config()->get('AMPWEBROOT');
if(file_exists($ampwebroot.'/admin/libraries/Console/RemoteUnlock.class.php')) {
	@unlink($ampwebroot.'/admin/libraries/Console/RemoteUnlock.class.php');
}
if(file_exists($ampwebroot.'/admin/libraries/Console/UpdateManager.class.php')) {
	@unlink($ampwebroot.'/admin/libraries/Console/UpdateManager.class.php');
}