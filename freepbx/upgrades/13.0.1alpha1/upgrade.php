<?php
global $amp_conf;
if(!class_exists('freepbx_conf')) {
	include_once ($amp_conf['AMPWEBROOT'].'/admin/libraries/freepbx_conf.class.php');
}
$freepbx_conf =& freepbx_conf::create();

$remove = array();
if ($freepbx_conf->conf_setting_exists('JQUERY_VER')) {
	$remove[] = 'JQUERY_VER';
}

if ($freepbx_conf->conf_setting_exists('JQUERYUI_VER')) {
	$remove[] = 'JQUERYUI_VER';
}

if ($freepbx_conf->conf_setting_exists('BOOTSTRAP_VER')) {
	$remove[] = 'BOOTSTRAP_VER';
}

if ($freepbx_conf->conf_setting_exists('ALWAYS_SHOW_DEVICE_DETAILS')) {
	$remove[] = 'ALWAYS_SHOW_DEVICE_DETAILS';
}

if(!empty($remove)) {
	$freepbx_conf->remove_conf_settings($remove);
}
