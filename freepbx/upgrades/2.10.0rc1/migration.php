<?php
global $amp_conf;

if (!class_exists('freepbx_conf')) {
	include_once ($amp_conf['AMPWEBROOT'].'/admin/libraries/freepbx_conf.class.php');
}

$freepbx_conf =& freepbx_conf::create();

// add second repo
if ($freepbx_conf->conf_setting_exists('MODULE_REPO') && $amp_conf['MODULE_REPO'] == 'http://mirror.freepbx.org') {
	$freepbx_conf->set_conf_values(array('MODULE_REPO' => 'http://mirror1.freepbx.org,http://mirror2.freepbx.org'), true);
}

