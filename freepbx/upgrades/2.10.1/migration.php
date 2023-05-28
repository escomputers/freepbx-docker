<?php
//add setting for buffer compression callback
global $amp_conf;
include_once ($amp_conf['AMPWEBROOT'].'/admin/libraries/freepbx_conf.class.php');
$freepbx_conf =& freepbx_conf::create();

outn(_("removing deprecated Advanced settings if needed.."));
//depricated views
//
$freepbx_conf->remove_conf_settings('BRAND_FREEPBX_ALT_RIGHT');
$freepbx_conf->remove_conf_settings('BRAND_IMAGE_FREEPBX_LINK_RIGHT');
$freepbx_conf->remove_conf_settings('BRAND_HIDE_LOGO_RIGHT');
$freepbx_conf->remove_conf_settings('BRAND_HIDE_HEADER_VERSION');
$freepbx_conf->remove_conf_settings('BRAND_HIDE_HEADER_MENUS');
$freepbx_conf->remove_conf_settings('AMPADMINLOGO');
$freepbx_conf->remove_conf_settings('BRAND_IMAGE_HIDE_NAV_BACKGROUND');
$freepbx_conf->remove_conf_settings('BRAND_IMAGE_SHADOW_SIDE_BACKGROUND');
$freepbx_conf->remove_conf_settings('BRAND_IMAGE_FREEPBX_RIGHT');
$freepbx_conf->remove_conf_settings('BRAND_IMAGE_RELOAD_LOADING');

//commit all settings
$freepbx_conf->commit_conf_settings();
out(_("ok"));

/* Check and add if necessary the writetimeout setting to the manager configuration
 */

// Read in manager.conf and strip out any #includes to avoid warnings
//
$orig_manager = file($amp_conf['ASTETCDIR'] . '/manager.conf');
if (is_array($orig_manager) && !empty($orig_manager)) {
	$manager = array();
	foreach ($orig_manager as $l) {
		$tl = trim($l);
		if ($tl[0] != '#') {
			$manager[] = $l;
		}
	}

	// check if we already have writetimeout by parsing as an ini file
	//
	$manager_ini = parse_ini_string(implode("", $manager), true);
	unset($manager);
	if (!isset($manager_ini[$amp_conf['AMPMGRUSER']]['writetimeout'])) {
		out(_("writetimeout not present, adding"));

		// add the setting right after the section heading
		//
		foreach ($orig_manager as $l) {
			$new_manager[] = $l;
			if (trim($l) == '[' . $amp_conf['AMPMGRUSER'] . ']') {
				$new_manager[] = 'writetimeout = ' . $amp_conf['ASTMGRWRITETIMEOUT'] . "\n";
			}
		}
		if (file_put_contents($amp_conf['ASTETCDIR'] . '/manager.conf', $new_manager)) {
			out(_("writetimeout added to manager.conf"));
		} else {
			out(_("an error occurred trying to write out manager.conf changes"));
		}
		unset($new_manager);
	} else {
		out(_("writetimeout already exists"));
	}
	unset($manager_ini);
} else {
	out(_("Failed to read manager file to add writetimeout"));
}
unset($orig_manager);

