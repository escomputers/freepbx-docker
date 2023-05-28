<?php
global $amp_conf;

if (!class_exists('freepbx_conf')) {
	include_once ($amp_conf['AMPWEBROOT'].'/admin/libraries/freepbx_conf.class.php');
}

$freepbx_conf =& freepbx_conf::create();

//move freepbx debug log
if ($freepbx_conf->conf_setting_exists('FPBXDBUGFILE')) {
	$freepbx_conf->set_conf_values(array('FPBXDBUGFILE' => $amp_conf['ASTLOGDIR'] . '/freepbx_dbug'), true);
}

$rm_command = function_exists('fpbx_which') ? fpbx_which('rm') : 'rm';

$cdr_dir =  $amp_conf['AMPWEBROOT'] . '/admin/cdr';
outn("Trying to remove dir $cdr_dir..");
if (is_dir($cdr_dir) && !is_link($cdr_dir)) {
	exec($rm_command . ' -rf ' . $cdr_dir, $out, $ret);
	if ($ret) {
		out("could not remove");
	} else {
		out("ok");
	}
} else {
	out("Not Required");
}

// Need to migrate the CDR table adding the recordingfile and did field. We get the creds from cdr_mysql.conf
// since $amp_conf is not really authoritative.
//
$db_creds = parse_ini_file($amp_conf['ASTETCDIR'] . '/cdr_mysql.conf');
if ($db_creds === false) {
	out(_("Error parsing cdr_mysql.conf, no migration done"));
} else {
	$db_creds['hostname'] = !empty($db_creds['hostname']) ? $db_creds['hostname'] : 'localhost';
	$datasource = 'mysql://'
		. $db_creds['user']
		. ':'
		. $db_creds['password']
		. '@'
		. $db_creds['hostname']
		. '/'
		. $db_creds['dbname'];
	$db_cdr = DB::connect($datasource); // attempt connection
	if (DB::IsError($db_cdr)) { 
		out(_("ERROR connecting to CDR DB to migrate, not done!"));
		freepbx_log(FPBX_LOG_ERROR,"failed to open CDR DB to add recordingfile and did fields");
	} else {
		$sql = "SELECT recordingfile FROM cdr";
		$confs = $db_cdr->getRow($sql, DB_FETCHMODE_ASSOC);
		outn(_("checking if recordingfile file field needed in cdr.."));
		if (DB::IsError($confs)) { // no error... Already done
			$sql = "ALTER TABLE cdr ADD recordingfile VARCHAR ( 255 ) NOT NULL default ''";
			$results = $db_cdr->query($sql);
			if(DB::IsError($results)) {
				out(_("failed"));
				freepbx_log(FPBX_LOG_ERROR,"failed to add recordingfile field to cdr table during migration");
			}
			out(_("added"));
		} else {
			out(_("already there"));
		}

		$sql = "SELECT did FROM cdr";
		$confs = $db_cdr->getRow($sql, DB_FETCHMODE_ASSOC);
		outn(_("checking if did file field needed in cdr.."));
		if (DB::IsError($confs)) { // no error... Already done
			$sql = "ALTER TABLE cdr ADD did VARCHAR ( 50 ) NOT NULL default ''";
			$results = $db_cdr->query($sql);
			if(DB::IsError($results)) {
				out(_("failed"));
				freepbx_log(FPBX_LOG_ERROR,"failed to add did field to cdr table during migration");
		}
			out(_("added"));
		} else {
			out(_("already there"));
		}
	}
}
?>
