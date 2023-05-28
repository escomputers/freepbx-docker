<?php
//add setting for buffer compression callback
global $amp_conf;
include_once ($amp_conf['AMPWEBROOT'].'/admin/libraries/freepbx_conf.class.php');
$freepbx_conf =& freepbx_conf::create();

// TODO: remember to put these into beta 2 migration also, most of these may not have originally been in the alpha/beta1 migration stage
//

//depricated views
//
$freepbx_conf->remove_conf_settings('VIEW_FREEPBX');
$freepbx_conf->remove_conf_settings('VIEW_FREEPBX_ADMIN');
$freepbx_conf->remove_conf_settings('VIEW_FREEPBX_RELOAD');
$freepbx_conf->remove_conf_settings('VIEW_FREEPBX_RELOADBAR');
$freepbx_conf->remove_conf_settings('VIEW_UNAUTHORIZED');
$freepbx_conf->remove_conf_settings('VIEW_LOGGEDOUT');
$freepbx_conf->remove_conf_settings('VIEW_LOGGEDOUT');
$freepbx_conf->remove_conf_settings('BRAND_FREEPBX_ALT_RIGHT');
$freepbx_conf->remove_conf_settings('BRAND_IMAGE_FREEPBX_LINK_RIGHT');
$freepbx_conf->remove_conf_settings('BRAND_IMAGE_HIDE_NAV_BACKGROUND');
$freepbx_conf->remove_conf_settings('BRAND_HIDE_LOGO_RIGHT');
$freepbx_conf->remove_conf_settings('BRAND_HIDE_HEADER_MENUS');
$freepbx_conf->remove_conf_settings('BRAND_HIDE_HEADER_VERSION');
$freepbx_conf->remove_conf_settings('VIEW_REPORTS');
$freepbx_conf->remove_conf_settings('VIEW_PANEL');
$freepbx_conf->remove_conf_settings('BRAND_IMAGE_FREEPBX_LEFT');

//commit all settings
$freepbx_conf->commit_conf_settings();

//settings
global $amp_conf;

$outdated = array(
	$amp_conf['AMPWEBROOT'] . '_asterisk',
	$amp_conf['AMPWEBROOT'] . '/admin/views/freepbx.php',
	$amp_conf['AMPWEBROOT'] . '/admin/views/freepbx_admin.php',
	$amp_conf['AMPWEBROOT'] . '/admin/views/freepbx_reload.php',
	$amp_conf['AMPWEBROOT'] . '/admin/views/freepbx_reloadbar.php',
	$amp_conf['AMPWEBROOT'] . '/admin/views/freepbx_footer.php',
	$amp_conf['AMPWEBROOT'] . '/admin/views/unauthorized.php',
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
?>
