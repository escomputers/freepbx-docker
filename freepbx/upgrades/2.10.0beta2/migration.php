<?php
global $amp_conf;

include_once ($amp_conf['AMPWEBROOT'].'/admin/libraries/freepbx_conf.class.php');
include_once ($amp_conf['AMPWEBROOT'].'/admin/libraries/notifications.class.php');
include_once ($amp_conf['AMPWEBROOT'].'/admin/libraries/moduleHook.class.php');
include_once ($amp_conf['AMPWEBROOT'].'/admin/libraries/modulelist.class.php');
include_once ($amp_conf['AMPWEBROOT'].'/admin/libraries/module.functions.php');

$freepbx_conf =& freepbx_conf::create();
$notify = notifications::create($db);

// Remove fop noitications if present since no longer supported
//
$notify->delete('freepbx','reload_fop');

// Remove FOP settings no longer used, and buffering_callback
//
$remove_settings = array('FOPDISABLE', 'FOPRUN', 'buffering_callback');
$freepbx_conf->remove_conf_settings($remove_settings);
unset($remove_settings);

// FOPPASSWORD was set hidden at some point which breaks things, so fix it here
// if it is present
//
if ($freepbx_conf->conf_setting_exists('FOPPASSWORD')) {
	unset($set);
	$set['hidden'] = 0;
	$set['value'] = $freepbx_conf->get_conf_setting('FOPPASSWORD');
	$freepbx_conf->define_conf_setting('FOPPASSWORD',$set); // comitted below
}

//move freepbx debug log
if ($freepbx_conf->conf_setting_exists('FPBXDBUGFILE')) {
	$freepbx_conf->set_conf_values(array('FPBXDBUGFILE' => $amp_conf['ASTLOGDIR'] . '/freepbx_dbug'), true);
}

$outdated = array(
	$amp_conf['AMPWEBROOT'] . '/admin/reports.php',
	$amp_conf['AMPWEBROOT'] . '/admin/assets/js/pbxlib.js.php',
	$amp_conf['AMPWEBROOT'] . '/admin/assets/css/jquery-ui-1.8.16.css',
	$amp_conf['AMPWEBROOT'] . '/admin/common/db_connect.php',
	$amp_conf['AMPWEBROOT'] . '/admin/common/json.inc.php',
	$amp_conf['AMPWEBROOT'] . '/admin/common/libfreepbx.javascripts.js',
	$amp_conf['AMPWEBROOT'] . '/admin/common/mainstyle-alternative.css',
	$amp_conf['AMPWEBROOT'] . '/admin/common/mainstyle.css',
	$amp_conf['AMPWEBROOT'] . '/admin/common/script.js.php',
	$amp_conf['AMPWEBROOT'] . '/admin/images/category1.png',
	$amp_conf['AMPWEBROOT'] . '/admin/images/freepbx_large.png',
	$amp_conf['AMPWEBROOT'] . '/admin/images/header-back.png',
	$amp_conf['AMPWEBROOT'] . '/admin/images/loading.gif',
	$amp_conf['AMPWEBROOT'] . '/admin/images/logo.png',
	$amp_conf['AMPWEBROOT'] . '/admin/images/modules1.png',
	$amp_conf['AMPWEBROOT'] . '/admin/images/shadow-side-background.png',
	$amp_conf['AMPWEBROOT'] . '/admin/images/tab-first-current.png',
	$amp_conf['AMPWEBROOT'] . '/admin/images/tab.png',
	$amp_conf['AMPWEBROOT'] . '/admin/images/watermark.png',
	$amp_conf['AMPWEBROOT'] . '/admin/views/freepbx_admin.php',
	$amp_conf['AMPWEBROOT'] . '/admin/views/loggedout.php',
);

out("Cleaning up deprecated or moved files:");

foreach ($outdated as $file) {
	outn("Checking $file..");
	if (file_exists($file) || is_link($file)) {
		unlink($file) ? out("removed") : out("failed to remove");
	} else {
		out("Not Required");
	}
}

$rm_command = function_exists('fpbx_which') ? fpbx_which('rm') : 'rm';

$common_dir =  $amp_conf['AMPWEBROOT'] . '/admin/common';
exec($rm_command . ' -rf ' . $common_dir . '/mstyle_autogen_*.css');

outn("Trying to remove dir $common_dir..");
if (is_dir($common_dir) && !is_link($common_dir)) {
	rmdir($common_dir) ? out("removed") : out("failed to remove, there may be left over files that need to be deleted");
} else {
	out("Not Required");
}

?>
