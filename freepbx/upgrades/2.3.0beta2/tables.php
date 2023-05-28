<?php

/* Convert all the core voicemail destinations that were previously controlled
 * by the global setting of busy vs. unavail to the new format that allows a
 * per voicemail box choice.
 */
convert_destinations('convert_voicemail_dest');

/* This function takes as input an old style voicemail destination:
 *
 *   ext-local,${VM_PREFIX}222,1
 *
 * and converts it to a new style voicemail destination by looking at
 * the current system default. So if busy, you would get the following:
 *
 *   ext-local,vmb200,1
 */
function convert_voicemail_dest($dest) {
	global $db;

	// Get the current default VM type to make the conversion with
	//
	$sql = "SELECT value FROM globals WHERE variable = 'VM_DDTYPE' ";
	$vm_ddtype = $db->getOne($sql);
	if(DB::IsError($vm_ddtype)) {
		// Should really be there but if not, just set to unavailable.
		//
		$vm_ddtype = 'u';
	}

	if (strstr($vm_ddtype,'b') === false) {
		$prefix = 'vmu';
	} else {
		$prefix = 'vmb';
	}
	if ( preg_match('/^\s*ext-local,\$\{VM_PREFIX\}(\d+),1/',$dest,$matches) ) {
				// matches[1] => extn
		return "ext-local,$prefix".$matches[1].",1";
	} else {
		return $dest;
	}
}

function convert_destinations($convert_dest) {

	global $db;

	if (!function_exists($convert_dest)) {
		out("ERROR: no such function $convert_dest");
		return false;
	}

	// CORE:
	//
	outn("converting voicemail destinations in core module..");
	$results = array();
	$sql = "SELECT cidnum, extension, destination FROM incoming";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if (DB::IsError($results)) { // error - table must not be there
		out("Error - no incoming table");
	} else {
		foreach ($results as $result) {
			$old_dest  = $result['destination'];
			$extension = $result['extension'];
			$cidnum    = $result['cidnum'];
	
			$new_dest = $convert_dest(trim($old_dest));
			if ($new_dest != $old_dest) {
				$sql = "UPDATE incoming SET destination = '$new_dest' WHERE cidnum = '$cidnum' AND extension = '$extension' AND destination = '$old_dest'";
				$results = $db->query($sql);
				if(DB::IsError($results)) {
					die($results->getMessage());
				}
			}
		}
		out("Done");
	}
	
	// ANNOUCEMENT:
	//
	outn("converting voicemail destinations in announcement module..");
	$results = array();
	$sql = "SELECT announcement_id, post_dest FROM announcement";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if (DB::IsError($results)) { // error - table must not be there
		out("no announcement table");
	} else {
		foreach ($results as $result) {
			$old_dest  = $result['post_dest'];
			$announcement_id    = $result['announcement_id'];
	
			$new_dest = $convert_dest(trim($old_dest));
			if ($new_dest != $old_dest) {
				$sql = "UPDATE announcement SET post_dest = '$new_dest' WHERE announcement_id = $announcement_id  AND post_dest = '$old_dest'";
				$results = $db->query($sql);
				if(DB::IsError($results)) {
					die($results->getMessage());
				}
			}
		}
		out("Done");
	}
	
	// CALLBACK:
	//
	outn("converting voicemail destinations in callback module..");
	$results = array();
	$sql = "SELECT callback_id, destination FROM callback";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if (DB::IsError($results)) { // error - table must not be there
		out("no callback table");
	} else {
		foreach ($results as $result) {
			$old_dest  = $result['destination'];
			$callback_id    = $result['callback_id'];
	
			$new_dest = $convert_dest(trim($old_dest));
			if ($new_dest != $old_dest) {
				$sql = "UPDATE callback SET destination = '$new_dest' WHERE callback_id = $callback_id  AND destination = '$old_dest'";
				$results = $db->query($sql);
				if(DB::IsError($results)) {
					die($results->getMessage());
				}
			}
		}
		out("Done");
	}
	
	// FINDMEFOLLOW:
	//
	outn("converting voicemail destinations in findmefollow module..");
	$results = array();
	$sql = "SELECT grpnum, postdest FROM findmefollow";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if (DB::IsError($results)) { // error - table must not be there
		out("no findmefollow table");
	} else {
		foreach ($results as $result) {
			$old_dest  = $result['postdest'];
			$grpnum    = $result['grpnum'];
	
			$new_dest = $convert_dest(trim($old_dest));
			if ($new_dest != $old_dest) {
				$sql = "UPDATE findmefollow SET postdest = '$new_dest' WHERE grpnum = '$grpnum'  AND postdest = '$old_dest'";
				$results = $db->query($sql);
				if(DB::IsError($results)) {
					die($results->getMessage());
				}
			}
		}
		out("Done");
	}
	
	// IVR:
	//
	outn("converting voicemail destinations in miscapp module..");
	$results = array();
	$sql = "SELECT ivr_id, selection, dest FROM ivr_dests";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if (DB::IsError($results)) { // error - table must not be there
		out("no ivr_dests table");
	} else {
		foreach ($results as $result) {
			$old_dest  = $result['dest'];
			$ivr_id    = $result['ivr_id'];
			$selection = $result['selection'];
	
			$new_dest = $convert_dest(trim($old_dest));
			if ($new_dest != $old_dest) {
				$sql = "UPDATE ivr_dests SET dest = '$new_dest' WHERE ivr_id = $ivr_id AND selection = '$selection' AND dest = '$old_dest'";
				$results = $db->query($sql);
				if(DB::IsError($results)) {
					die($results->getMessage());
				}
			}
		}
		out("Done");
	}
	
	// MISCAPP:
	//
	outn("converting voicemail destinations in miscapp module..");
	$results = array();
	$sql = "SELECT miscapps_id, dest FROM miscapps";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if (DB::IsError($results)) { // error - table must not be there
		out("no miscapp table");
	} else {
		foreach ($results as $result) {
			$old_dest    = $result['dest'];
			$miscapps_id = $result['miscapps_id'];
	
			$new_dest = $convert_dest(trim($old_dest));
			if ($new_dest != $old_dest) {
				$sql = "UPDATE miscapps SET dest = '$new_dest' WHERE miscapps_id = $miscapps_id  AND dest = '$old_dest'";
				$results = $db->query($sql);
				if(DB::IsError($results)) {
					die($results->getMessage());
				}
			}
		}
		out("Done");
	}
	
	// PARKING:
	//
	outn("converting voicemail destinations in queues module..");
	$results = array();
	$sql = "SELECT id, keyword, data FROM parkinglot WHERE keyword = 'goto'";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if (DB::IsError($results)) { // error - table must not be there
		out("no parkinglot table");
	} else {
		foreach ($results as $result) {
			$old_dest  = $result['data'];
			$id        = $result['id'];
			$keyword   = $result['keyword'];
	
			$new_dest = $convert_dest(trim($old_dest));
			if ($new_dest != $old_dest) {
				$sql = "UPDATE parkinglot SET data = '$new_dest' WHERE id = '$id'  AND keyword = '$keyword' AND data = '$old_dest'";
				$results = $db->query($sql);
				if(DB::IsError($results)) {
					die($results->getMessage());
				}
			}
		}
		out("Done");
	}
	
	// QUEUES:
	//
	outn("converting voicemail destinations in queues module..");
	$results = array();
	$sql = "SELECT args, extension, priority FROM extensions WHERE context = 'ext-queues' AND descr = 'jump'";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if (DB::IsError($results)) { // error - table must not be there
		out("no queues table");
	} else {
		foreach ($results as $result) {
			$old_dest  = $result['args'];
			$extension = $result['extension'];
			$priority  = $result['priority'];
	
			$new_dest = $convert_dest(trim($old_dest));
			if ($new_dest != $old_dest) {
				$sql = "UPDATE extensions SET args = '$new_dest' WHERE extension = '$extension' AND priority = '$priority' AND context = 'ext-queues' AND descr = 'jump' AND args = '$old_dest'";
				$results = $db->query($sql);
				if(DB::IsError($results)) {
					die($results->getMessage());
				}
			}
		}
		out("Done");
	}
	
	// RINGGROUPS:
	//
	outn("converting voicemail destinations in ringgroups module..");
	$results = array();
	$sql = "SELECT grpnum, postdest FROM ringgroups";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if (DB::IsError($results)) { // error - table must not be there
		out("no ringgroups table");
	} else {
		foreach ($results as $result) {
			$old_dest  = $result['postdest'];
			$grpnum    = $result['grpnum'];
	
			$new_dest = $convert_dest(trim($old_dest));
			if ($new_dest != $old_dest) {
				$sql = "UPDATE ringgroups SET postdest = '$new_dest' WHERE grpnum = '$grpnum'  AND postdest = '$old_dest'";
				$results = $db->query($sql);
				if(DB::IsError($results)) {
					die($results->getMessage());
				}
			}
		}
		out("Done");
	}
	
	// TIMECONDITIONS:
	//
	outn("converting voicemail destinations in timeconditions module..");
	$results = array();
	$sql = "SELECT timeconditions_id, truegoto, falsegoto FROM timeconditions";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if (DB::IsError($results)) { // error - table must not be there
		out("no timeconditions table");
	} else {
		foreach ($results as $result) {
			$old_false_dest    = $result['falsegoto'];
			$old_true_dest     = $result['truegoto'];
			$timeconditions_id = $result['timeconditions_id'];
	
			$new_false_dest = $convert_dest(trim($old_false_dest));
			$new_true_dest  = $convert_dest(trim($old_true_dest));
			if (($new_true_dest != $old_true_dest) || ($new_false_dest != $old_false_dest)) {
				$sql = "UPDATE timeconditions SET truegoto = '$new_true_dest', falsegoto = '$new_false_dest' WHERE timeconditions_id = $timeconditions_id  AND truegoto = '$old_true_dest' AND falsegoto ='$old_false_dest'";
				$results = $db->query($sql);
				if(DB::IsError($results)) {
					die($results->getMessage());
				}
			}
		}
		out("Done");
	}
	
	// DAYNIGHT:
	//
	outn("converting voicemail destinations in daynight module..");
	$results = array();
	$sql = "SELECT ext, dmode, dest FROM daynight WHERE dmode in ('day', 'night')";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if (DB::IsError($results)) { // error - table must not be there
		out("no daynight table");
	} else {
		foreach ($results as $result) {
			$old_dest  = $result['dest'];
			$ext    = $result['ext'];
			$dmode = $result['dmode'];
	
			$new_dest = $convert_dest(trim($old_dest));
			if ($new_dest != $old_dest) {
				$sql = "UPDATE daynight SET dest = '$new_dest' WHERE ext = '$ext' AND dmode = '$dmode' AND dest = '$old_dest'";
				$results = $db->query($sql);
				if(DB::IsError($results)) {
					die($results->getMessage());
				}
			}
		}
		out("Done");
	}
}


/* Create notifications table used by framework to put warnings, errors, etc. on the dashboard
 */
outn("creating notifications table..");
$sql = "
	CREATE TABLE IF NOT EXISTS notifications (
	  module        varchar(24) NOT NULL default '',
		id            varchar(24) NOT NULL default '',
		`level`       int(11) NOT NULL default '0',
		display_text  varchar(255) NOT NULL default '',
		extended_text blob NOT NULL,
		link          varchar(255) NOT NULL default '',
		`reset`       tinyint(4) NOT NULL default '0',
		candelete     tinyint(4) NOT NULL default '0',
		`timestamp`   int(11) NOT NULL default '0',
		PRIMARY KEY  (module,id)
	)
	";
$check = $db->query($sql);
if(DB::IsError($check)) {
	out("ERROR: Can not create notifications table");
}
out("Done");

outn("Upgrading notifications table to add candelete..");

$sql = "SELECT candelete FROM notifications";
$confs = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($confs)) { // no error... Already done
	out("Not Required");
} else {
	$sql = "ALTER TABLE notifications ADD candelete TINYINT ( 4 ) NOT NULL DEFAULT '0'";
	$results = $db->query($sql);
	if(DB::IsError($results)) {
	        die($results->getMessage());
	}
	out("Done");
}

outn("creating cronmanager table..");
$sql = " CREATE TABLE IF NOT EXISTS `cronmanager` (
  				`module` varchar(24) NOT NULL default '',
  				`id` varchar(24) NOT NULL default '',
  				`time` varchar(5) default NULL,
  				`freq` int(11) NOT NULL default '0',
  				`lasttime` int(11) NOT NULL default '0',
  				`command` varchar(255) NOT NULL default '',
  				PRIMARY KEY  (`module`,`id`)
				)
				";
$check = $db->query($sql);
if(DB::IsError($check)) {
	out("ERROR: Can not create cronmanager table");
}
out("Done");

outn("enabling online update checking..");
$sql = "SELECT * FROM cronmanager WHERE module = 'module_admin' AND id = 'UPDATES'";
$result = $db->getAll($sql, DB_FETCHMODE_ASSOC);
if (DB::IsError($result)) { // error - table must not be there
	out("can't update cronmanager table");
} else {
	if (count($result)) {
		out("already enabled");
	} else {
		$freq = 24;
		$night_time = array(19,20,21,22,23,0,1,2,3,4,5);
		$run_time = $night_time[rand(0,10)];
		$command = $amp_conf['AMPBIN']."/module_admin listonline";
	
		$sql = "INSERT INTO cronmanager 
        		(module, id, time, freq, lasttime, command)
						VALUES
						('module_admin', 'UPDATES', '$run_time', $freq, 0, '$command')
					";
		$check = $db->query($sql);
		if(DB::IsError($check)) {
			out("ERROR: can not insert update information in cronmanager");
		} else {
			out("Done");
		}
	}
}

?>
