<?php  /* $Id */
require_once("/var/lib/asterisk/bin/php-asmanager.php");

outn("Creating devices table..");

$sql = "CREATE TABLE IF NOT EXISTS devices (id VARCHAR( 20 ) NOT NULL , tech VARCHAR( 10 ) NOT NULL , dial VARCHAR( 50 ) NOT NULL , devicetype VARCHAR( 5 ) NOT NULL , user VARCHAR( 50 ) , description VARCHAR( 50 ))";
$results = $db->query($sql);
if (DB::IsError($results)) {
	die($results->getMessage());
}

out("OK");

outn("Creating users table..");

$sql = "CREATE TABLE IF NOT EXISTS users (extension VARCHAR( 20 ) NOT NULL , password VARCHAR( 20 ) , name VARCHAR( 50 ) , voicemail VARCHAR( 50 ) , ringtimer INT(3) , noanswer VARCHAR( 100 ) , recording VARCHAR( 50 ) ,  outboundcid VARCHAR( 50 ))";
$results = $db->query($sql);
if (DB::IsError($results)) {
	die($results->getMessage());
}

out("OK");

outn("Upgrading sip table..");

$sql = "ALTER TABLE sip CHANGE id id VARCHAR( 20 ) DEFAULT  '-1' NOT NULL";
$results = $db->query($sql);
if (DB::IsError($results)) {
	die($results->getMessage());
}

out("OK");

outn("Upgrading zap table..");

$sql = "ALTER TABLE zap CHANGE id id VARCHAR( 20 ) DEFAULT  '-1' NOT NULL";
$results = $db->query($sql);
if (DB::IsError($results)) {
	die($results->getMessage());
}

out("OK");

outn("Upgrading iax table..");

$sql = "ALTER TABLE iax CHANGE id id VARCHAR( 20 ) DEFAULT  '-1' NOT NULL";
$results = $db->query($sql);
if (DB::IsError($results)) {
	die($results->getMessage());
}

out("OK");

out("Upgrading existing extensions to devices and users..");

$sql = "SELECT * FROM users";
$existingusers = $db->getAll($sql,DB_FETCHMODE_ASSOC);
if (DB::IsError($existingusers)) {
	die($existingusers->getMessage());
}

$sql = "SELECT * FROM devices";
$existingdevices = $db->getAll($sql,DB_FETCHMODE_ASSOC);
if (DB::IsError($existingdevices)) {
	die($existingdevices->getMessage());
}

$sql = "SELECT * FROM extensions WHERE context = 'ext-local' AND priority = '1'";
$results = $db->getAll($sql,DB_FETCHMODE_ASSOC);
if (DB::IsError($results)) {
	die($results->getMessage());
}

$astman = new AGI_AsteriskManager();
if ($res = $astman->connect("127.0.0.1", $amp_conf["AMPMGRUSER"] , $amp_conf["AMPMGRPASS"])) {
	foreach ($results as $result) {
		$convert=true;
		$extension = $result['extension'];
		foreach($existingusers as $eu){
			if ($eu['extension'] == $extension) {
				out("Extension ".$extension." found in users table.  Not upgrading.");
				$convert=false;
			}
		}
		foreach($existingdevices as $ed){
			if ($ed['id'] == $extension) {
				out("Extension ".$result['extension']." found in devices table.  Not upgrading.");
				$convert=false;
			}
		}
		if($convert) {
			out("Creating a user for existing extension ".$extension);
			
			$sql = "SELECT value FROM globals WHERE variable = 'ECID{$extension}'";
			$outboundcid = $db->getOne($sql);
			
			$sql = "SELECT value FROM globals WHERE variable = 'E{$extension}'";
			$tech = $db->getOne($sql);
			$tech = strtolower($tech);
			
			if ($tech == "sip"){
				$dial = "SIP/".$extension;
				$sql = "SELECT keyword,data FROM sip WHERE id = '{$extension}'";
				$sets = $db->getAll($sql,DB_FETCHMODE_ASSOC);
				if (is_array($sets)) {
					foreach($sets as $set) {
						if ($set['keyword'] == "callerid")
							$name = $set['data'];
						if ($set['keyword'] == "record_out")
							$record_out = $set['data'];
						if ($set['keyword'] == "record_in")
							$record_in = $set['data'];
					}
				}
				$sql = "UPDATE sip SET data = '".$extension."@device' WHERE id = '".$extension."' AND keyword = 'mailbox' LIMIT 1";
				$resu = $db->query($sql);
			} else if ($tech == "iax2"){
				$dial = "IAX2/".$extension;
				$sql = "SELECT keyword,data FROM iax WHERE id = '{$extension}'";
				$sets = $db->getAll($sql,DB_FETCHMODE_ASSOC);
				if (is_array($sets)) {
					foreach($sets as $set) {
						if ($set['keyword'] == "callerid")
							$name = $set['data'];
						if ($set['keyword'] == "record_out")
							$record_out = $set['data'];
						if ($set['keyword'] == "record_in")
							$record_in = $set['data'];
					}
				}
				$sql = "UPDATE iax SET data = '".$extension."@device' WHERE id = '".$extension."' AND keyword = 'mailbox' LIMIT 1";
				$resu = $db->query($sql);
			} else if ($tech == "zap"){
				$sql = "SELECT value FROM globals WHERE variable = 'ZAPCHAN_{$extension}'";
				$zapchan = $db->getOne($sql);
				$dial = "ZAP/".$zapchan;
				
				$sql = "SELECT keyword,data FROM zap WHERE id = '{$extension}'";
				$sets = $db->getAll($sql,DB_FETCHMODE_ASSOC);
				if (is_array($sets)) {
					foreach($sets as $set) {
						if ($set['keyword'] == "callerid")
							$name = $set['data'];
						if ($set['keyword'] == "record_out")
							$record_out = $set['data'];
						if ($set['keyword'] == "record_in")
							$record_in = $set['data'];
					}
				}
				$sql = "UPDATE zap SET data = '".$extension."@device' WHERE id = '".$extension."' AND keyword = 'mailbox' LIMIT 1";
				$resu = $db->query($sql);
			}
			
			$name=substr($name,0,strcspn($name,'<'));
			$name=rtrim($name,"\" ");
			$name=ltrim($name,"\" ");
			$recording="out=".$record_out."|in=".$record_in;
			$sql="INSERT INTO users (extension,password,name,voicemail,ringtimer,noanswer,recording,outboundcid) values (\"$extension\",\"\",\"$name\",\"\",\"15\",\"\",\"$recording\",\"$outboundcid\")";
			$insertresults = $db->query($sql);
			if(DB::IsError($insertresult)) {
				die($insertresults->getMessage());
			}
			
			out("Creating a device for existing extension ".$extension);
			
			$sql="INSERT INTO devices (id,tech,dial,devicetype,user,description) values (\"$extension\",\"$tech\",\"$dial\",\"fixed\",\"$extension\",\"$name\")";
			$insertresults = $db->query($sql);
			if(DB::IsError($insertresult)) {
				die($insertresults->getMessage());
			}
			
			out("Mapping user ".$extension." to device ".$extension);
			$astman->database_put("AMPUSER",$extension."/device","\"".$extension."\"");
			$astman->database_put("DEVICE",$extension."/user","\"".$extension."\"");
		}
		
	}
} else {
	fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
}

out("..OK");

?>