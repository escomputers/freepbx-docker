<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//  Portions Copyright (C) 2011 Igor Okunev
//  Portions Copyright (C) 2011 Mikael Carlsson
//	Copyright 2013 Schmooze Com Inc.
//
function cdr_get_config($engine) {
	global $core_conf, $amp_conf, $version;
	if (isset($core_conf) && is_a($core_conf, "core_conf")) {
		$section = 'asteriskcdrdb';
		$core_conf->addResOdbc($section, array('enabled' => 'yes'));
		$core_conf->addResOdbc($section, array('dsn' => 'MySQL-asteriskcdrdb'));
		$core_conf->addResOdbc($section, array('pre-connect' => 'yes'));
		if((version_compare($version, "14.0", "lt") && version_compare($version, "13.14.0", "ge")) || (version_compare($version, "14.0", "ge") && version_compare($version, "14.3.0", "ge"))) {
			$core_conf->addResOdbc($section, array('max_connections' => '5'));
		} else {
			$core_conf->addResOdbc($section, array('pooling' => 'no'));
			$core_conf->addResOdbc($section, array('limit' => '1'));
		}
		$core_conf->addResOdbc($section, array('username' => !empty($amp_conf['CDRDBUSER']) ? $amp_conf['CDRDBUSER'] : $amp_conf['AMPDBUSER']));
		$core_conf->addResOdbc($section, array('password' => !empty($amp_conf['CDRDBPASS']) ? $amp_conf['CDRDBPASS'] : $amp_conf['AMPDBPASS']));
		$core_conf->addResOdbc($section, array('database' => !empty($amp_conf['CDRDBNAME']) ? $amp_conf['CDRDBNAME'] : 'asteriskcdrdb'));
	}

	if (isset($amp_conf['CDRUSEGMT']) && file_exists($amp_conf['ASTETCDIR'] . '/cdr_adaptive_odbc.conf')) {
		//Parse the existing file
		$cdrConf = @parse_ini_file($amp_conf['ASTETCDIR'] . '/cdr_adaptive_odbc.conf', true);
		//Modify the data
		$content = "";
		if (empty($cdrConf)) {
			return;
		}

		foreach ($cdrConf as $section => $data) {
			$content .= "[$section]\n";
			if($amp_conf['CDRUSEGMT'] == 1){
				$data['usegmtime'] = 'yes';
			} else {
				unset($data['usegmtime']);
			}
			foreach ($data as $key => $value) {
				if ($key == 'alias start') {
					$content .= $key . " =" . $value . "\n";
				} else {
					$content .= $key . "=" . $value . "\n";
				}
			}
			$content .= "\n";
		}
		//Rewrite the file
		\FreePBX::WriteConfig()->writeConfig('cdr_adaptive_odbc.conf', $content, false);
	}
}


// NOTE: This function should probably be in a FreePBX library
// php function empty() treats 0 as empty, that is why I need the function below
// to be able to search for any number starting with 0
function is_blank($value) {
	return empty($value) && !is_numeric($value);
}


/* Asterisk RegExp parser */
function cdr_asteriskregexp2sqllike( $source_data, $user_num ) {
        $number = $user_num;
        if ( strlen($number) < 1 ) {
                $number = $_POST[$source_data];
        }
        if ( '__' == substr($number,0,2) ) {
                $number = substr($number,1);
        } elseif ( '_' == substr($number,0,1) ) {
                $number_chars = preg_split('//', substr($number,1), -1, PREG_SPLIT_NO_EMPTY);
                $number = '';
                foreach ($number_chars as $chr) {
                        if ( $chr == 'X' ) {
                                $number .= '[0-9]';
                        } elseif ( $chr == 'Z' ) {
                                $number .= '[1-9]';
                        } elseif ( $chr == 'N' ) {
                                $number .= '[2-9]';
                        } elseif ( $chr == '.' ) {
                                $number .= '.+';
                        } elseif ( $chr == '!' ) {
                                $_POST[ $source_data .'_neg' ] = 'true';
                        } else {
                                $number .= $chr;
                        }
                }
                $_POST[ $source_data .'_mod' ] = 'asterisk-regexp';
        }
        return $number;
}

function cdr_get_cel($uid, $cel_table = 'asteriskcdrdb.cel') {
	global $dbcdr;

	// common query components
	//
	$sql_base = "SELECT *, unix_timestamp(eventtime) as `event_timestamp` FROM $cel_table WHERE ";
	$sql_order = " ORDER BY eventtime, id";


	// get first set of CEL records
	//
	$sql_start = $sql_base . "uniqueid = '$uid' OR linkedid = '$uid'" . $sql_order;
	$pass = $dbcdr->getAll($sql_start,DB_FETCHMODE_ASSOC);
	if(DB::IsError($pass)) {
		die_freepbx($pass->getDebugInfo() . "SQL - <br /> $sql_start" );
	}

	$last_criteria = array();
	$next =array();
	$done = false;

	// continue querying all records based on the uniqueid and linkedid fields associated
	// with the first set we queried until we have found all of them. This usually results
	// in one or two more queries prior to the last one being identical indicating we have
	// found all the records
	//
	while (!$done) {
		unset($next);
    $next = array();
		foreach ($pass as $set) {
			$next[] = $set['uniqueid'];
			$next[] = $set['linkedid'];
		}
		$next = array_unique($next);
		sort($next);

		// if our criteria is now the same then we have found everything
		//
		if ($next == $last_criteria) {
			$done = true;
			continue;
		}
		unset($pass);

		$set = "('" . implode("','", $next) . "')";

		$sql_next = $sql_base . "uniqueid IN $set OR linkedid IN $set" . $sql_order;
		$last_criteria = $next;
		$next = array();
		$pass = $dbcdr->getAll($sql_next,DB_FETCHMODE_ASSOC);
		if(DB::IsError($pass)) {
			die_freepbx($pass->getDebugInfo() . "SQL - <br /> $sql_next" );
		}
	}
	return $pass;
}

function cdr_export_csv($csvdata) {
	// Searching for more than 10,000 records take more than 30 seconds.
	// php default timeout is 30 seconds, hard code it to 3000 seconds for now (which is WAY overkill).
	// TODO: make this value a setting in Advanced Settings
	set_time_limit(3000);
	$fname		= "cdr__" .  (string) time() . $_SERVER["SERVER_NAME"] . ".csv";
	$csv_header ="calldate,clid,src,dst,dcontext,channel,dstchannel,lastapp,lastdata,duration,billsec,disposition,amaflags,accountcode,uniqueid,userfield,did,cnum,cnam,outbound_cnum,outbound_cnam,dst_cnam,recordingfile,linkedid,peeraccount,sequence";

	$mimetype = "application/octet-stream";

	// Start sending headers
	header("Pragma: public"); // required
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: private",false); // required for certain browsers
	header("Content-Transfer-Encoding: binary");
	header("Content-Type: " . $mimetype);
	header("Content-Disposition: attachment; filename=\"" . $fname . "\";" );
	// Send data

	$out = fopen('php://output', 'w');
	fputcsv($out, explode(",",$csv_header));
	foreach ($csvdata as $csv) {
		$csv_line = array();
		foreach(explode(",",$csv_header) as $k => $item) {
			if(preg_match("/\p{Hebrew}/u", utf8_decode($csv[$item]))){
				/**
				 * Hebrew is read from right to the left.
				 * Need to change the order Num Name instead to Name and Num
				 * Otherwise, even if the csv format is correct, the result is messed up on Excel.
				 */
				preg_match('/<\d+>/', $csv[$item], $_num);
				preg_match('/".+"/', $csv[$item], $_name);
				$name = str_replace('"','',utf8_decode($_name[0]));
				$csv[$item] = $_num[0].' "'.$name.'"';
			}
			$csv_line[$k] 	= $csv[$item];
		}
		fputcsv($out, $csv_line);
	}
	fclose($out);
	die();
}
