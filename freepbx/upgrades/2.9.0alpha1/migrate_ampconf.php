<?php


//TODO: canwe remove these as there included by install_amp?
if (! function_exists("out")) {
	function out($text) {
		echo $text."<br />";
	}
}

if (! function_exists("outn")) {
	function outn($text) {
		echo $text;
	}
}

global $db, $amp_conf;

outn(_("Checking for freepbx_settings table.."));
$sql = 'SELECT count(*) FROM freepbx_settings';
$result = $db->query($sql);
unset($sql);
if(DB::IsError($result)){
	$sql[] = "CREATE TABLE `freepbx_settings` (
	  `keyword` varchar(50) default NULL,
	  `value` varchar(255) default NULL,
	  `name` varchar(80) default NULL,
	  `level` tinyint(1) default 0,
	  `description` text default NULL,
	  `type` varchar(25) default NULL,
	  `options` text default NULL,
	  `defaultval` varchar(255) default NULL,
	  `readonly` tinyint(1) default 0,
	  `hidden` tinyint(1) default 0,
	  `category` varchar(50) default NULL,
	  `module` varchar(25) default NULL,
	  `emptyok` tinyint(1) default 1,
	  `sortorder` int(11) default 0,
	  PRIMARY KEY  (`keyword`)
	) ENGINE=MyISAM DEFAULT CHARSET=latin1";

	foreach ($sql as $q) {
		$result = $db->query($q);
		if(DB::IsError($result)){
			die_freepbx($result->getDebugInfo());
		}
	}
  out(_("created"));
} else {
  out(_("exists"));
}
outn("Add field sortorder to freepbx_settings..");
$sql = "SELECT sortorder FROM freepbx_settings";
$confs = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($confs)) { // no error... Already done
  out("Not Required");
} else {
  $sql = "ALTER TABLE freepbx_settings ADD sortorder INT ( 11 ) NOT NULL DEFAULT 0";
  $results = $db->query($sql);
  if(DB::IsError($results)) {
    die($results->getMessage());
  }
  out("Done");
}
	
// Make sure we save the settings as they are currenlty parsed
//
$current_amp_conf = $amp_conf;

// Now let's initialize all the settings, if they happen to already exist for
// some reason, this is ok as the define_conf_settings() method does not save the
// values back once defined, you must explicitly set them. Don't commit to db,
// that is down a little further down anyhow.
//
outn(_("Initialize freepbx_conf settings.."));
freepbx_settings_init(false);
out(_("ok"));

// Now we will set the current value of all settings
//
$freepbx_conf =& freepbx_conf::create();
$update_arr = array();

/* Previously 'none' was the default. If migrating from old system, and it was
 * not set, then it was in 'none' mode. We need to retain this as part of the
 * migration or we may lock out admins after the migration.
 */
if (!isset($current_amp_conf['AUTHTYPE']) || ($current_amp_conf['AUTHTYPE'] !='database' && $current_amp_conf['AUTHTYPE'] !='webserver')) {
  out(_("Setting AUTHTYPE to none consistent with old default"));
  $current_amp_conf['AUTHTYPE'] = 'none';
}

if (!isset($current_amp_conf['MOHDIR']) || $current_amp_conf['MOHDIR'] == '') {
  out(_("Setting MOHDIR to mohmp3 consistent with old default"));
  $current_amp_conf['MOHDIR'] = 'mohmp3';
} else {
  $current_amp_conf['MOHDIR'] = trim($current_amp_conf['MOHDIR'],'/');
}

/* FreePBX has a 'back door' option that allows loging into the GUI with the dababase username/password as
 * admin user. We have disabled this ability by default but it has the potential to lock people out of
 * their systems on upgrade. Check to see if they have ANY admin users defined. If not, then set
 * AMP_ACCESS_DB_CREDS to true overriding the default so they can still access their GUI.
 */
if ($current_amp_conf['AUTHTYPE'] !='none') {
  outn(_("Checking number of admin users.."));
  $sql = "SELECT count(*) FROM ampusers WHERE sections = '*'";
  $admin_users = $db->getOne($sql);
  if (DB::IsError($admin_users)) {
    out(_("error reading ampusers table"));
  } elseif (!$admin_users) {
    out(_("0 admins"));
  }
  if (DB::IsError($admin_users) || !$admin_users) {
    out(_("setting AMP_ACCESS_DB_CREDS to true"));
    out(_("[WARNING] this is a security risk, you should create an admin user and disable this vulnerability."));
    $current_amp_conf['AMP_ACCESS_DB_CREDS'] = true;
  } else {
    out(sprintf(_("%s admins"),$admin_users));
  }
}

out(_("Migrate current values into freepbx_conf.."));
foreach ($current_amp_conf as $key => $val) {
	outn(sprintf(_("checking %s .."),$key));
	if (!$freepbx_conf->conf_setting_exists($key)) {
		out(_("not in freepbx_conf, skipping"));
		continue;
	}
	// Make sure that all "false" values are going to be interpreted as false, the trues will be converted.
	switch (strtolower($val)) {
		case 'false':
		case 'no':
		case 'off':
		$val = false;
		break;
	}
	$update_arr[$key] = $val;
	out(_("preparing for update"));
}
unset($current_amp_conf);
if (count($update_arr)) {
	outn(_("Updating prepared settings.."));
	$ret = $freepbx_conf->set_conf_values($update_arr, true, true);
	out(sprintf(_("changed %s settings"),$ret));
} else {
	out(_("There were no settings to update"));
}

// To get through migration in the intial install we allowed SQL and LOG_SQL even though they have been obsoleted. Here we will
// convert if necessary and reset the value.
//
$log_level = strtoupper($amp_conf['AMPSYSLOGLEVEL']);
if ($log_level == 'SQL' || $log_level == 'LOG_SQL') {
	outn(sprintf(_("Discontinued logging type %s changing to %s.."),$log_level,'FILE'));
	$freepbx_conf->set_conf_values(array('AMPSYSLOGLEVEL' => 'FILE'));
	out(_("ok"));
}
// AMPSYSLOGLEVEL
unset($set);
$set['value'] = 'FILE';
$set['options'] = 'FILE, LOG_EMERG, LOG_ALERT, LOG_CRIT, LOG_ERR, LOG_WARNING, LOG_NOTICE, LOG_INFO, LOG_DEBUG';
$freepbx_conf->define_conf_setting('AMPSYSLOGLEVEL',$set,true);


// build freepbx.conf if it doesnt already exists
outn(_("checking for freepbx.conf.."));

$freepbx_conf = '/etc/freepbx.conf';
if ($freepbx_conf && file_exists($freepbx_conf)) {
	out(sprintf(_("%s already exists"),$freepbx_conf));
} else {

	$filename = $freepbx_conf;
	
	$txt = '';
	$txt .= '<?php' . "\n";
	$txt .= '$amp_conf[\'AMPDBUSER\']	= "' . $amp_conf['AMPDBUSER'] . '";' . "\n";
	$txt .= '$amp_conf[\'AMPDBPASS\']	= "' . $amp_conf['AMPDBPASS'] . '";' . "\n";
	$txt .= '$amp_conf[\'AMPDBHOST\']	= "' . $amp_conf['AMPDBHOST'] . '";' . "\n";
	$txt .= '$amp_conf[\'AMPDBNAME\']	= "' . $amp_conf['AMPDBNAME'] . '";' . "\n";
	$txt .= '$amp_conf[\'AMPDBENGINE\']	= "' . $amp_conf['AMPDBENGINE'] . '";' . "\n";
	$txt .= '$amp_conf[\'datasource\']	= "' . $amp_conf['datasource'] . '";' . "\n";
	$txt .= 'require_once(\'' . $amp_conf['AMPWEBROOT'] . '/admin/bootstrap.php\');' . "\n";

	$fh = fopen($filename,'w');
	if ($fh === false || (fwrite($fh,$txt) === false)) {
		out(sprintf(_("FATAL error writing  %s"),$filename));
		die_freepbx(_("You must have a proper freepbx.conf file to proceed"));
	}
	fclose($fh);
	out(sprintf(_("created %s"),$filename));
}
?>
