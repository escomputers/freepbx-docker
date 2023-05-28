<?php 

outn("Generating device & user settings in astdb..");


require_once("/var/lib/asterisk/bin/php-asmanager.php");

$sql = "SELECT * FROM users";
$userresults = $db->getAll($sql,DB_FETCHMODE_ASSOC);
if(DB::IsError($userresults)) {
	$userresults = null;
}

$sql = "SELECT * FROM devices";
$devresults = $db->getAll($sql,DB_FETCHMODE_ASSOC);
if(DB::IsError($devresults)) {
	$devresults = null;
}
	
//add details to astdb
$astman = new AGI_AsteriskManager();
if ($res = $astman->connect("127.0.0.1", $amp_conf["AMPMGRUSER"] , $amp_conf["AMPMGRPASS"])) {

	$astman->database_deltree("AMPUSER");
	foreach($userresults as $usr) {
		extract($usr);
		$astman->database_put("AMPUSER",$extension."/password",$password);
		$astman->database_put("AMPUSER",$extension."/ringtimer",$ringtimer);
		$astman->database_put("AMPUSER",$extension."/noanswer",$noasnwer);
		$astman->database_put("AMPUSER",$extension."/recording",$recording);
		$astman->database_put("AMPUSER",$extension."/outboundcid","\"".$outboundcid."\"");
		$astman->database_put("AMPUSER",$extension."/cidname","\"".$name."\"");
	}	
	$astman->database_deltree("DEVICE");
	foreach($devresults as $dev) {
		extract($dev);	
		$astman->database_put("DEVICE",$id."/dial",$dial);
		$astman->database_put("DEVICE",$id."/type",$devicetype);
		$astman->database_put("DEVICE",$id."/user",$user);
		$astman->database_put("AMPUSER",$user."/device",$id);
		
		//voicemail symlink
		exec("rm -f /var/spool/asterisk/voicemail/device/".$id);
		exec("/bin/ln -s /var/spool/asterisk/voicemail/default/".$user."/ /var/spool/asterisk/voicemail/device/".$id);
		exec("chown asterisk:asterisk /var/spool/asterisk/voicemail/device/".$id);
	}
	
} else {
	fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
}


out("OK");

?>

