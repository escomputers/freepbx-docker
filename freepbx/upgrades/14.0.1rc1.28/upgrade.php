<?php
global $amp_conf;
if(!class_exists('freepbx_conf')) {
	include_once ($amp_conf['AMPWEBROOT'].'/admin/libraries/freepbx_conf.class.php');
}
$freepbx_conf = freepbx_conf::create();

if ($freepbx_conf->conf_setting_exists('EXPOSE_ALL_FEATURE_CODES')) {
	$freepbx_conf->remove_conf_settings('EXPOSE_ALL_FEATURE_CODES');
}

