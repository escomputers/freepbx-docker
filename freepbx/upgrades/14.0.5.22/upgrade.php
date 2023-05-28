<?php

$ampwebroot = \FreePBX::Config()->get('AMPWEBROOT');
if(file_exists($ampwebroot.'/admin/libraries/Console/Extnotify.class.php')) {
	@unlink($ampwebroot.'/admin/libraries/Console/Extnotify.class.php');
}