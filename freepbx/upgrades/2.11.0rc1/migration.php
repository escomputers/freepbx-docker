<?php
//add setting for buffer compression callback
global $amp_conf;
include_once ($amp_conf['AMPWEBROOT'].'/admin/libraries/freepbx_conf.class.php');
$freepbx_conf =& freepbx_conf::create();


// remove hidden settings to get rid of confusion that it can't be changed
//
if ($freepbx_conf->conf_setting_exists('AS_DISPLAY_HIDDEN_SETTINGS')) {
	$freepbx_conf->set_conf_values(array('AS_DISPLAY_HIDDEN_SETTINGS' => false), true);
}

// Get the rtp ports Asterisk is configured for, make sure we start on even port
//
$rtp_ports = parse_ini_file($amp_conf['ASTETCDIR']."/rtp.conf");
if (!empty($rtp_ports['rtpstart']) && !empty($rtp_ports['rtpend'])) {
	$parms = array($rtp_ports['rtpstart'], $rtp_ports['rtpend']);
	$result = $db->query("INSERT INTO `admin` (`variable`, `value`) VALUES ('RTPSTART', ?), ('RTPEND', ?)", $parms);
	if(DB::IsError($result)) {
		out(_("ERROR: could not insert previous values for rpt.conf, they may already exist"));
	} else {
		out(_("Inserted interim RTPSTART and RTPEND settings"));
	}
}

// Saved the interim values, now remove the file as it's supplied by core. If this was a link it will be relinked.
//
if (file_exists($amp_conf['ASTETCDIR']."/rtp.conf")) {
	out(_("removing rtp.conf it will be symlinked from core"));
	unlink($amp_conf['ASTETCDIR']."/rtp.conf");
}

// Remove res_odbc.conf it it's not a symlink and if possible save it as
// res_odbc_custom.conf
//
$res_odbc = $amp_conf['ASTETCDIR'] . '/res_odbc.conf';
$res_odbc_custom = $amp_conf['ASTETCDIR'] . '/res_odbc_custom.conf';

out(_("checking if res_odbc.conf needs migration"));
if (!is_link($res_odbc)) {
	out(_("trying to move res_odbc.conf to res_odbc_custom.conf"));
	if (!file_exists($res_odbc_custom)) {
		if (rename($res_odbc, $res_odbc_custom)) {
			out(_("ok moved"));
		} else {
			out(_("error could not move it"));
		}
	}
	out(_("removing res_odbc.conf so it can be symlinked from core"));
	unlink($res_odbc);
} else {
	out(_("already symlinked skipping"));
}
/*
// depricated settings
//
 */
$freepbx_conf->remove_conf_settings('PARKINGPATCH');
$freepbx_conf->remove_conf_settings('USECATEGORIES');

//commit all settings
$freepbx_conf->commit_conf_settings();
?>
