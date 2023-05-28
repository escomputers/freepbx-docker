<?php
if (!function_exists('sql')) {
  function sql($sql,$type="query",$fetchmode=null) {
	  global $db;
	  $results = $db->$type($sql,$fetchmode);
	  if(DB::IsError($results)) {
		  die($results->getDebugInfo() . "SQL - <br /> $sql" );
	  }
	  return $results;
  }
}

function encrypt_passwords()
{
	global $db;
	out("Updating passwords..");
	$sql = "SELECT * FROM ampusers";
	$users = $db->getAll($sql,NULL,DB_FETCHMODE_ASSOC);
	if (DB::IsError($users)) { // Error while getting the users list to update... bad
		die($users->getMessage());
	} else {
		outn("(".count($users)." accounts) ");	
		foreach ($users as $index => $ufields) {
			$sql = "UPDATE ampusers SET password_sha1='".sha1($ufields['password'])."' WHERE username='".$ufields['username']."'";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				outn("Error while updating account: ".$ufields['username']." (".$result->getMessage.")");
			}	
		}
	}
	out("Done.");
}

outn("Checking for sha1 passwords..");
$sql = "SELECT password_sha1 FROM ampusers";
$passfield = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($passfield)) { // no error... Already done
	$sql = "SELECT password FROM ampusers";
	$passfield = $db->getRow($sql, DB_FETCHMODE_ASSOC);
	if (DB::IsError($passfield)) { //password field do not exist, done
		out("OK.");
	} else { //Field password still exist, update of passwords is needed.
		encrypt_passwords();
	}
} else {
	if ($passfield->code == DB_ERROR_NOSUCHFIELD) {
		outn("Updating database..");
		$sql = "ALTER TABLE ampusers ADD password_sha1 VARCHAR ( 40 ) NOT NULL AFTER password";
		$results = $db->query($sql);
		if (DB::IsError($results)) {
			die($sql."\n".$results->getMessage());
		} else {
			out("Done.");
			encrypt_passwords();
			outn("Removing old password column..");
			$sql = "ALTER TABLE ampusers DROP password";
			$results = $db->query($sql);
			if (DB::IsError($results)) {
				die($results->getMessage());
			} else {
				out("Done.");
			}
		}
	} else { //The error was not about the field...
		die($passfield->getMessage());
	}
}
			
// This next set of functions and code are used to migrate from the old
// global variable storage of trunk data to the new trunk table and trunk
// pattern table for localprefixes.conf
// this is taken straight out of the core install.php script, as new installs
// with install_amp break and haven't taken the time to figure out why.
//

//Sort trunks for sqlite
function __sort_trunks($a,$b)  {
        global $unique_trunks;
        preg_match("/OUT_([0-9]+)/",$unique_trunks[$a][0],$trunk_num1);
        preg_match("/OUT_([0-9]+)/",$unique_trunks[$b][0],$trunk_num2);
        return ($trunk_num1[1] >= $trunk_num2[1]? 1:-1);
}

// Get values from localprefix configuration file where values are stored
// for fixlocalprefix macro
//
function __parse_DialRulesFile($filename, &$conf, &$section) {
	if (is_null($conf)) {
		$conf = array();
	}
	if (is_null($section)) {
		$section = "general";
	}
	
	if (file_exists($filename)) {
		$fd = fopen($filename, "r");
		while ($line = fgets($fd, 1024)) {
			if (preg_match("/^\s*([a-zA-Z0-9-_]+)\s*=\s*(.*?)\s*([;#].*)?$/",$line,$matches)) {
				// name = value
				// option line
				$conf[$section][ $matches[1] ] = $matches[2];
			} else if (preg_match("/^\s*\[(.+)\]/",$line,$matches)) {
				// section name
				$section = strtolower($matches[1]);
			} else if (preg_match("/^\s*#include\s+(.*)\s*([;#].*)?/",$line,$matches)) {
				// include another file
				
				if ($matches[1][0] == "/") {
					// absolute path
					$filename = $matches[1];
				} else {
					// relative path
					$filename =  dirname($filename)."/".$matches[1];
				}
				__parse_DialRulesFile($filename, $conf, $section);
			}
		}
	}
}

function __order_DialRules($a, $b) {
  return substr($a,4) > substr($b,4);
}

function __migrate_trunks_to_table() {

	global $db;
	global $amp_conf;

	$sql = "
	CREATE TABLE `trunks` 
	( 
		`trunkid` INTEGER,
		`name` VARCHAR( 50 ) NOT NULL DEFAULT '', 
		`tech` VARCHAR( 20 ) NOT NULL , 
		`outcid` VARCHAR( 40 ) NOT NULL DEFAULT '', 
		`keepcid` VARCHAR( 4 ) DEFAULT 'off',
		`maxchans` VARCHAR( 6 ) DEFAULT '',
		`failscript` VARCHAR( 255 ) NOT NULL DEFAULT '', 
		`dialoutprefix` VARCHAR( 255 ) NOT NULL DEFAULT '', 
		`channelid` VARCHAR( 255 ) NOT NULL DEFAULT '', 
		`usercontext` VARCHAR( 255 ) NULL, 
		`provider` VARCHAR( 40 ) NULL, 
		`disabled` VARCHAR( 4 ) DEFAULT 'off',
	
		PRIMARY KEY  (`trunkid`, `tech`, `channelid`) 
	) 
	";
	$check = $db->query($sql);
	if(DB::IsError($check)) {
		if($check->getCode() == DB_ERROR_ALREADY_EXISTS) {
			//echo ("already exists\n");
			return false; 
		} else {
			die_freepbx($check->getDebugInfo());	
		}
	}
	
	// sqlite doesn't support the syntax required for the SQL so we have to do it the hard way
	if ($amp_conf["AMPDBENGINE"] == "sqlite3") {
		$sqlstr = "SELECT variable, value FROM globals WHERE variable LIKE 'OUT\_%' ESCAPE '\'";
		$my_unique_trunks = sql($sqlstr,"getAll",DB_FETCHMODE_ASSOC);

		$sqlstr = "SELECT variable, value FROM globals WHERE variable LIKE 'OUTDISABLE\_%' ESCAPE '\'";
		$disable_states = sql($sqlstr,"getAll",DB_FETCHMODE_ASSOC);

		foreach($disable_states as $arr)  {
			$disable_states_assoc[$arr['variable']] = $arr['value'];
		}
		global $unique_trunks;
		$unique_trunks = array();

		foreach ($my_unique_trunks as $this_trunk) {

			$trunk_num = substr($this_trunk['variable'],4);
			$this_state = (isset($disable_states_assoc['OUTDISABLE_'.$trunk_num]) ? $disable_states_assoc['OUTDISABLE_'.$trunk_num] : 'off');
			$unique_trunks[] = array($this_trunk['variable'], $this_trunk['value'], $this_state);
		}
		// sort this array using a custom function __sort_trunks(), defined above
		uksort($unique_trunks,"__sort_trunks");
		// re-index the newly sorted array
		foreach($unique_trunks as $arr) {
			$unique_trunks_t[] = array($arr[0],$arr[1],$arr[2]);
		}
		$unique_trunks = $unique_trunks_t;

	} else {
		$sqlstr  = "SELECT t.variable, t.value, d.value state FROM `globals` t ";
		$sqlstr .= "JOIN (SELECT x.variable, x.value FROM globals x WHERE x.variable LIKE 'OUTDISABLE\_%') d ";
		$sqlstr .= "ON substring(t.variable,5) = substring(d.variable,12) WHERE t.variable LIKE 'OUT\_%' ";
		$sqlstr .= "UNION ALL ";
		$sqlstr .= "SELECT v.variable, v.value, concat(substring(v.value,1,0),'off') state  FROM `globals` v ";
		$sqlstr .= "WHERE v.variable LIKE 'OUT\_%' AND concat('OUTDISABLE_',substring(v.variable,5)) NOT IN ";
		$sqlstr .= " ( SELECT variable from globals WHERE variable LIKE 'OUTDISABLE\_%' ) ";
		$sqlstr .= "ORDER BY variable";
		$unique_trunks = sql($sqlstr,"getAll");
	}

	$trunkinfo = array();
	foreach ($unique_trunks as $trunk) {
		list($tech,$name) = explode('/',$trunk[1]);
		$trunkid = ltrim($trunk[0],'OUT_');

		$sqlstr = "
			SELECT `variable`, `value` FROM `globals` WHERE `variable` IN (
				'OUTCID_$trunkid', 'OUTFAIL_$trunkid', 'OUTKEEPCID_$trunkid',
				'OUTMAXCHANS_$trunkid', 'OUTPREFIX_$trunkid')
		";
		$trunk_attribs = sql($sqlstr,'getAll',DB_FETCHMODE_ASSOC);
		$trunk_attrib_hash = array();
		foreach ($trunk_attribs as $attribs) {
			$trunk_attrib_hash[$attribs['variable']] = $attribs['value'];
		}

		switch ($tech) {
			case 'SIP':
				$tech = 'sip';
				$user = sql("SELECT `data` FROM `sip` WHERE `id` = '99999$trunkid' AND `keyword` = 'account'",'getOne');
				break;
			case 'IAX':
			case 'IAX2':
				$tech = 'iax';
				$user = sql("SELECT `data` FROM `iax` WHERE `id` = '99999$trunkid' AND `keyword` = 'account'",'getOne');
				break;
			case 'ZAP':
			case 'DUNDI':
			case 'ENUM':
				$tech = strtolower($tech);
				$user = '';
				break;
			default:
				if (substr($tech,0,4) == 'AMP:') {
					$tech='custom';
					$name = substr($trunk[1],4);
				} else {
					$tech = strtolower($tech);
				}
				$user = '';
		}

		$trunkinfo[] = array(
			'trunkid' =>       $trunkid,
			'tech' =>          $tech,
			'outcid' =>        $trunk_attrib_hash['OUTCID_'.$trunkid],
			'keepcid' =>       $trunk_attrib_hash['OUTKEEPCID_'.$trunkid],
			'maxchans' =>      $trunk_attrib_hash['OUTMAXCHANS_'.$trunkid],
			'failscript' =>    $trunk_attrib_hash['OUTFAIL_'.$trunkid],
			'dialoutprefix' => $trunk_attrib_hash['OUTPREFIX_'.$trunkid],
			'channelid' =>     $name,
			'usercontext' =>   $user,
			'disabled' =>      $trunk[2], // disable state
		);	

		$sqlstr = "INSERT INTO `trunks` 
			( trunkid, tech, outcid, keepcid, maxchans, failscript, dialoutprefix, channelid, usercontext, disabled) 
			VALUES (
				'".$db->escapeSimple($trunkid)."',
				'".$db->escapeSimple($tech)."',
				'".$db->escapeSimple($trunk_attrib_hash['OUTCID_'.$trunkid])."',
				'".$db->escapeSimple($trunk_attrib_hash['OUTKEEPCID_'.$trunkid])."',
				'".$db->escapeSimple($trunk_attrib_hash['OUTMAXCHANS_'.$trunkid])."',
				'".$db->escapeSimple($trunk_attrib_hash['OUTFAIL_'.$trunkid])."',
				'".$db->escapeSimple($trunk_attrib_hash['OUTPREFIX_'.$trunkid])."',
				'".$db->escapeSimple($name)."',
				'".$db->escapeSimple($user)."',
				'".$db->escapeSimple($trunk[2])."'
		  )
		";
		sql($sqlstr);
	}

	return $trunkinfo;
}

// __migrate_trunks_to_table will return false if the trunks table already exists and
// no migration is needed
//
outn(_("Checking if trunk table migration required.."));
$trunks = __migrate_trunks_to_table();
if ($trunks !== false) {
	outn(_("migrating.."));
	foreach ($trunks as $trunk) {
		$tech = $trunk['tech'];
		$trunkid = $trunk['trunkid'];
		switch ($tech) {
			case 'sip':
			case 'iax':
				$sql = "UPDATE `$tech` SET `id` = 'tr-peer-$trunkid' WHERE `id` = '9999$trunkid'";
				sql($sql);
				$sql = "UPDATE `$tech` SET `id` = 'tr-user-$trunkid' WHERE `id` = '99999$trunkid'";
				sql($sql);
				$sql = "UPDATE `$tech` SET `id` = 'tr-reg-$trunkid' WHERE `id` = '9999999$trunkid' AND `keyword` = 'register'";
				sql($sql);
				break;
			default:
				break;
		}
	}
	outn(_("removing globals.."));
	// Don't do this above, in case something goes wrong
	//
	// At this point we have created our trunks table and update the sip and iax files
	// time to get rid of the old globals which will not be auto-generated
	//
	foreach ($trunks as $trunk) {
		$trunkid = $trunk['trunkid'];

		$sqlstr = "
			DELETE FROM `globals` WHERE `variable` IN (
				'OUTCID_$trunkid', 'OUTFAIL_$trunkid', 'OUTKEEPCID_$trunkid',
				'OUTMAXCHANS_$trunkid', 'OUTPREFIX_$trunkid', 'OUT_$trunkid',
				'OUTDISABLE_$trunkid'
			)
		";
		sql($sqlstr);
	}
	out(_("done"));
} else {
	out(_("not needed"));
}

$sql = "
CREATE TABLE `trunks_dialpatterns` 
( 
	`trunkid` INTEGER,
	`rule` VARCHAR( 255 ) NOT NULL, 
	`seq` INTEGER,
	PRIMARY KEY  (`trunkid`, `rule`, `seq`) 
) 
";
outn(_("Checking if trunks_dialpatterns table exists.."));
$check = $db->query($sql);
if(DB::IsError($check) && $check->getCode() != DB_ERROR_ALREADY_EXISTS) {
	die_freepbx("Can not create trunks_dialpatterns table");
} else if(DB::IsError($check) && $check->getCode() == DB_ERROR_ALREADY_EXISTS) {
	out(_("already exists"));
} else {
	out(_("created"));
	outn(_("loading table from localprefixes.conf.."));
	$localPrefixFile = $amp_conf['ASTETCDIR']."/localprefixes.conf";
	$conf = array();
	__parse_DialRulesFile($localPrefixFile, $conf, $section);

	$rules_arr = array();
	foreach ($conf as $tname => $rules) {
		$tid = ltrim($tname,'trunk-');
		uksort($rules,'__order_DialRules'); //make sure they are in order
		$seq = 1;
		foreach ($rules as $rule) {
			$rules_arr[] = array($tid,$rule,$seq);
			$seq++;
		}
	}
	$compiled = $db->prepare("INSERT INTO `trunks_dialpatterns` (trunkid, rule, seq) VALUES (?,?,?)");
	$result = $db->executeMultiple($compiled,$rules_arr);
	if(DB::IsError($result)) {
		die_freepbx($result->getDebugInfo().'error populating trunks_dialpatterns table');	
	}
	out(_("loaded"));
}

// END of trunks migration code
