<?php
global $amp_conf;
if(!class_exists('freepbx_conf')) {
	include_once ($amp_conf['AMPWEBROOT'].'/admin/libraries/freepbx_conf.class.php');
}
$freepbx_conf =& freepbx_conf::create();
//rare race condition
if (!$freepbx_conf->conf_setting_exists('AMPPLAYBACK')) {
	$setting = array(
		'category' => 'Directory Layout',
		'value' => '/var/lib/asterisk/playback',
		'defaultval' => '/var/lib/asterisk/playback',
		'options' => '',
		'name' => 'Browser Playback Cache Directory',
		'description' => 'This is the default directory for HTML5 releated playback files',
		'readonly' => 1,
		'type' => CONF_TYPE_DIR,
		'level' => 4,
	);
	$freepbx_conf->define_conf_setting('AMPPLAYBACK', $setting, true);
}
if ($freepbx_conf->conf_setting_exists('ASTVARLIBPLAYBACK')) {
	$value = $freepbx_conf->get_conf_setting('ASTVARLIBPLAYBACK');
	$freepbx_conf->remove_conf_setting('ASTVARLIBPLAYBACK');
	$freepbx_conf->set_conf_values(array('AMPPLAYBACK' => $value), true, true);
}
