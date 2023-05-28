<?php
//add setting for buffer compression callback
global $amp_conf;
include_once ($amp_conf['AMPWEBROOT'].'/admin/libraries/freepbx_conf.class.php');
$freepbx_conf =& freepbx_conf::create();

// depricated settings
//
$freepbx_conf->remove_conf_settings('AUTOMIXMON');

//commit all settings
$freepbx_conf->commit_conf_settings();

//settings
global $amp_conf;

/*
$outdated = array(
	$amp_conf['AMPWEBROOT'] . '/admin/views/loggedout.php',	
);

out("Cleaning up deprecated or moved files:");

foreach ($outdated as $file) {
	outn("Checking $file..");
	if (file_exists($file) && !is_link($file)) {
		unlink($file) ? out("removed") : out("failed to remove");
	} else {
		out("Not Required");
	}
}
 */
?>
