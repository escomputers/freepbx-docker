<?php
global $amp_conf;
global $db;

$removes = array();
$removes[] = $amp_conf['AMPWEBROOT'] . '/admin/assets/.htaccess';
$removes[] = $amp_conf['AMPWEBROOT'] . '/admin/modules/.htaccess';
foreach($removes as $remove) {
	if(file_exists($remove)) {
		unlink($remove);
	}
}
