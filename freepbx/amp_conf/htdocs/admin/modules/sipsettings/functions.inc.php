<?php
/* $Id:$ */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//
// Use hookGet_config so that everyone (like core) will have written their
// SIP settings and then we can remove any that we are going to override
//

/* Field Values for type field */
define('SIP_NORMAL','0');
define('SIP_CODEC','1');
define('SIP_VIDEO_CODEC','2');
define('SIP_CUSTOM','9');


function set_prev_owner($owner) {
	global $prev_owner;
	$prev_owner = $owner;
}

function sipsettings_process_errors($errors) {
	foreach($errors as $error) {
		$error_display[] = array(
			'js' => "$('#".$error['id']."').addClass('validation-error');\n",
			'div' => $error['message'],
		);
	}
	return $error_display;
}

class sipsettings_validate {
	var $errors = array();

	/* checks if value is an integer */
	function is_int($value, $item, $message, $negative=false) {
		$value = trim($value);
		if($value == "-1"){
			return $value;
		}
		else{
			if ($value != '' && $negative) {
				$tmp_value = substr($value,0,1) == '-' ? substr($value,1) : $value;
				if (!ctype_digit($tmp_value)) {
					$this->errors[] = array('id' => $item, 'value' => $value, 'message' => $message);
				}
			} elseif (!$negative) {
				if (!ctype_digit($value) || ($value < 0 )) {
					$this->errors[] = array('id' => $item, 'value' => $value, 'message' => $message);
				}
			}
			return $value;
		}
	}

	/* checks if value is valid port between 1024 - 65535 */
	function is_ip_port($value, $item, $message) {
		$value = trim($value);
		if ($value != '' && (!ctype_digit($value) || $value < 1024 || $value > 65535)) {
			$this->errors[] = array('id' => $item, 'value' => $value, 'message' => $message);
		}
		return $value;
	}

	/* checks if value is valid ip format */
	function is_ip($value, $item, $message, $ipv6_ok=false) {
		$value = trim($value);
		if ($value != '' && !preg_match('|^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$|',$value,$matches)) {
			$regex = '/^\s*((?=.*::.*)(::)?([0-9A-F]{1,4}(:(?=[0-9A-F])|(?!\2)(?!\5)(::)|\z)){0,7}|((?=.*::.*)(::)?([0-9A-F]{1,4}(:(?=[0-9A-F])|(?!\7)(?!\10)(::))){0,5}|([0-9A-F]{1,4}:){6})((25[0-5]|(2[0-4]|1[0-9]|[1-9]?)[0-9])(\.(?=.)|\z)){4}|([0-9A-F]{1,4}:){7}[0-9A-F]{1,4})\s*$/i';
			if ($ipv6_ok && ($value == '::' || preg_match($regex,$value, $matches))) {
				return $value;
			} else {
				$this->errors[] = array('id' => $item, 'value' => $value, 'message' => $message);
			}
		}
		return $value;
	}

	/* checks if value is valid ip netmask format */
	function is_netmask($value, $item, $message) {
		$value = trim($value);
		if ($value != '' && !(preg_match('|^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$|',$value,$matches) || (ctype_digit($value) && $value >= 0 && $value <= 24))) {
			$this->errors[] = array('id' => $item, 'value' => $value, 'message' => $message);
		}
		return $value;
	}

	/* checks if value is valid alpha numeric format */
	function is_alphanumeric($value, $item, $message) {
		$value = trim($value);
		if ($value != '' && !preg_match("/^\s*([a-zA-Z0-9.&\-@_!<>!\"\']+)\s*$/",$value,$matches)) {
			$this->errors[] = array('id' => $item, 'value' => $value, 'message' => $message);
		}
		return $value;
	}

	/* trigger a validation error to be appended to this class */
	function log_error($value, $item, $message) {
		$this->errors[] = array('id' => $item, 'value' => $value, 'message' => $message);
		return $value;
	}
}

function sipsettings_hookGet_config($engine) {
	global $core_conf;
	global $ext;	// is this the best way to pass this?

	switch($engine) {
		case "asterisk":
			if (isset($core_conf) && is_a($core_conf, "core_conf")) {
				$raw_settings = sipsettings_get(true);

				/* TODO: This is example concept code

					 The only real conflicts are codecs (mainly cause
					 it will look ugly. So we should strip those but
					 leave the rest. If we overrite it, oh well

				 */
				$idx = 0;
				foreach ($core_conf->_sip_general as $entry) {
					switch (strtolower($entry['key'])) {
						case 'allow':
						case 'disallow':
							unset($core_conf->_sip_general[$idx]);
						break;
						default:
							// do nothing
					}
					$idx++;
				}
				$interim_settings = array();
				foreach ($raw_settings as $var) {
					switch ($var['type']) {
						case SIP_NORMAL:
							$interim_settings[$var['keyword']] = $var['data'];
						break;
						case SIP_CUSTOM:
							$sip_settings[] = array($var['keyword'], $var['data']);
						break;
						default:
							// Error should be above
					}
				}
				unset($raw_settings);

				// Add any defaults that should be in there
				$def = FreePBX::Sipsettings()->getChanSipDefaults();
				foreach ($def as $k => $v) {
					if (!isset($interim_settings[$k]) && $v) {
						$interim_settings[$k] = $v;
					}
				}

				/* Codecs First */
				$core_conf->addSipGeneral('disallow','all');
				foreach (FreePBX::Sipsettings()->getCodecs('audio') as $codec => $enabled) {
					if ($enabled != '') {
						$core_conf->addSipGeneral('allow',$codec);
					}
				}
				unset($codecs);

				if ($interim_settings['videosupport'] == 'yes') {
					foreach (FreePBX::Sipsettings()->getCodecs('video') as $codec => $enabled) {
						if ($enabled != '') {
							$core_conf->addSipGeneral('allow',$codec);
						}
					}
				}
				unset($video_codecs);

				/* next figure out what we need to write out (deal with things like nat combos, etc. */

				$nat_mode = $interim_settings['nat_mode'];
				$jbenable = $interim_settings['jbenable'];

	      $foundexternip = false;

	      // Ensure default TLS Settings for chansip are available
      	if(empty($interim_settings['tlsbindport'])) {
      		// Note - this is TCP, not UDP.
      		$interim_settings['tlsbindport'] = 5061;
      	}

      	if(!empty($interim_settings['tlsbindaddr'])) {
      		$interim_settings['tlsbindaddr'] = ($interim_settings['tlsbindaddr'] === '::' ? '[::]' : $interim_settings['tlsbindaddr']).":".$interim_settings['tlsbindport'];
      	} else {
      		// [::] means 'listen on all interfaces, both ipv4 and ipv6' when in sipsettings.
      		$interim_settings['tlsbindaddr'] = "[::]:".$interim_settings['tlsbindport'];
      	}

	// There is no sip setting 'tlsbindport', so make sure we remove it before writing the file.
	unset($interim_settings['tlsbindport']);

	foreach ($interim_settings as $key => $value) {
		switch ($key) {
		case 'csipcertid':
			if(!empty($value) && $interim_settings['tlsenable'] == 'yes' && FreePBX::Modules()->moduleHasMethod("certman","getDefaultCertDetails")) {
				$cert = FreePBX::Certman()->getCertificateDetails($value);
				if(!empty($cert['files']['crt']) && !empty($cert['files']['key'])) {
					$sip_settings[] = array('tlsprivatekey', $cert['files']['key']);
					$sip_settings[] = array('tlscertfile', $cert['files']['pem']);
				}
			}
			break;
		case 'nat_mode':
			break;
		case 'externhost_val':
			if ($nat_mode == 'externhost' && $value != '') {
				$sip_settings[] = array('externhost', $value);
			}
			break;

		case 'externrefresh':
			if ($nat_mode == 'externhost' && $value != '') {
				$sip_settings[] = array($key, $value);
			}
			break;

		case 'externip_val':
			if ($nat_mode == 'externip' && $value != '') {
				$foundexternip = true;
				$sip_settings[] = array('externip', $value);
			}
			break;

		case 'jbforce':
		case 'jbimpl':
		case 'jbmaxsize':
		case 'jbresyncthreshold':
		case 'jblog':
			if ($jbenable == 'yes' && $value != '') {
				$sip_settings[] = array($key, $value);
			}
		break;

		case 'language':
			if ($value != '') {
				$sip_settings[] = array('language', $value);
				$ext->addGlobal('SIPLANG',$value);
			}
		break;
		//FREEPBX-9737 AND FREEPBX-6518 In Asterisk 11+ nat=yes is deprecated.
		case 'nat':
			global $amp_conf;
			$astge11 = version_compare($amp_conf['ASTVERSION'],'11.5','ge');
			if($astge11){
				switch ($value) {
					case 'yes':
						$value = "force_rport,comedia";
					break;
					case 'never':
						$value = "no";
					break;
					case 'route':
						$value = "force_rport";
					break;
				}
			}
			$sip_settings[] = array($key, $value);
		break;

		case 't38pt_udptl':
			if ($value != 'no') {
				if($value == 'yes') {
					$sip_settings[] = array('t38pt_udptl', 'yes,redundancy,maxdatagram=400');
				} elseif($value == 'fec') {
					$sip_settings[] = array('t38pt_udptl', 'yes,fec');
				} elseif($value == 'redundancy') {
					$sip_settings[] = array('t38pt_udptl', 'yes,redundancy');
				} elseif($value == 'none') {
					$sip_settings[] = array('t38pt_udptl', 'yes,none');
				}

			}
			break;
		case "webrtcstunaddr":
		case "webrtcturnaddr":
		case "webrtcturnpassword":
		case "webrtcturnusername":
		case "bindport":
		case "bindaddr":
		break;
		default:
			// Ignore localnet settings from chansip sipsettings, they're now in general
			if (substr($key,0,9) == "localnet_" || substr($key,0,8) == "netmask_") {
				break;
			}

			$sip_settings[] = array($key, $value);
			break;
		}
	}

	$udpbindaddrr = (!empty($interim_settings['bindaddr']) ? ($interim_settings['bindaddr'] === '::' ? '[::]' : $interim_settings['bindaddr']) : '0.0.0.0').":".(!empty($interim_settings['bindport']) ? $interim_settings['bindport'] : '5060');
	$sip_settings[] = array('udpbindaddr', $udpbindaddrr);

	if(FreePBX::Modules()->moduleHasMethod("certman","getCABundle")) {
		$cafile = FreePBX::Certman()->getCABundle();
		if(!empty($cafile)) {
			$sip_settings[] = array('tlscafile', $cafile);
		}
	}

	// Is there a global external IP settings? If there wasn't one specified
	// as part of the chan_sip settings, check to see if there's one here.
	if (!$foundexternip && $nat_mode == "externip") {
		$externip = FreePBX::create()->Sipsettings->getConfig('externip');
		if ($externip) {
			$sip_settings[] = array("externip", $externip);
		}
	}

	// Now do the localnets
	$localnets = FreePBX::create()->Sipsettings->getConfig('localnets');
	if(!empty($localnets) && is_array($localnets)) {
		foreach ($localnets as $arr) {
			$net = trim($arr['net']);
			$mask = trim($arr['mask']);
			$sip_settings[] = array("localnet", $net."/".$mask);
		}
	}
					global $version;
					$core_conf->addSipGeneral('context','from-sip-external');
					$core_conf->addSipGeneral('callerid','Unknown');
					$core_conf->addSipGeneral('notifyringing','yes');
					$core_conf->addSipGeneral('notifyhold','yes');
					$core_conf->addSipGeneral('tos_sip','cs3');		// Recommended setting from doc/ip-tos.txt
					$core_conf->addSipGeneral('tos_audio','ef');	 // Recommended setting from doc/ip-tos.txt
					$core_conf->addSipGeneral('tos_video','af41'); // Recommended setting from doc/ip-tos.txt
					$core_conf->addSipGeneral('alwaysauthreject','yes');
					$core_conf->addSipGeneral('limitonpeers','yes');
					unset($interim_settings);
					if (is_array($sip_settings)) foreach ($sip_settings as $entry) {
						if ($entry[1] != '') {
							$core_conf->addSipGeneral($entry[0],$entry[1]);
						}
					}
			}
		break;
	}

	return true;
}

function sipsettings_get($raw=false) {
	return FreePBX::Sipsettings()->getChanSipSettings($raw);
}

// Add a sipsettings
function sipsettings_edit($sip_settings) {
	global $db;
	global $amp_conf;
	global $prev_owner;
	$save_settings = array();
	$save_to_admin = array(); // Used only by ALLOW_SIP_ANON for now
	$chansip_val =  FreePBX::create()->Sipsettings()->getChanSipSettings();
	$dbbindport = $chansip_val['tlsbindport'];
	$req_owner = $_REQUEST['tlsportowner'];
	$vd = new	sipsettings_validate();

	// TODO: this is where I will build validation before saving
	//
	$integer_msg = _("%s must be a non-negative integer");
	foreach ($sip_settings as $key => $val) {
		switch ($key) {
			case 'bindaddr':
				$msg = _("Bind Address (bindaddr) must be an IP address.");
				$ipv6_ok = version_compare($amp_conf['ASTVERSION'],'1.8','ge');
				$save_settings[] = array($key,$db->escapeSimple($vd->is_ip($val,$key,$msg,$ipv6_ok)),'2',SIP_NORMAL);
			break;

			case 'bindport':
				$msg = _("Bind Port (bindport) must be between 1024 and 65535");
				$save_settings[] = array($key,$db->escapeSimple($vd->is_ip_port($val, $key, $msg)),'1',SIP_NORMAL);
			break;

			case 'rtpholdtimeout':
				// validation: must be > $sip_settings['rtptimeout'] (and of course a proper number)
				//$vd->log_error();
				if ($val < $sip_settings['rtptimeout']) {
					$msg = _("rtpholdtimeout must be higher than rtptimeout");
					$vd->log_error($val, $key, $msg);
				}
				$msg = sprintf($integer_msg,$key);
				$save_settings[] = array($key,$db->escapeSimple($vd->is_int($val, $key, $msg)),'10',SIP_NORMAL);
			break;

			case 'rtptimeout':
			case 'rtpkeepalive':
			case 'checkmwi':
			case 'registertimeout':
			case 'minexpiry':
			case 'maxexpiry':
			case 'defaultexpiry':
				$msg = sprintf($integer_msg,$key);
				$save_settings[] = array($key,$db->escapeSimple($vd->is_int($val,$key,$msg)),'10',SIP_NORMAL);
			break;

			case 'maxcallbitrate':
			case 'registerattempts':
				$msg = sprintf($integer_msg,$key);
				$save_settings[] = array($key,$db->escapeSimple($vd->is_int($val,$key,$msg)),'10',SIP_NORMAL);
			break;


			case 'context':
				$msg = sprintf(_("%s must be alphanumeric"),$key);
				$save_settings[] = array($key,$db->escapeSimple($vd->is_alphanumeric($val,$key,$msg)),'0',SIP_NORMAL);
			break;

			case 'externrefresh':
				$msg = sprintf($integer_msg,$key);
				$save_settings[] = array($key,$db->escapeSimple($vd->is_int($val,$key,$msg)),'41',SIP_NORMAL);
			break;

			case 'nat':
				$save_settings[] = array($key,$val,'39',SIP_NORMAL);
			break;

			case 'externip_val':
				if ($sip_settings['nat_mode'] == 'externip') {
					if (trim($val) == "" && !FreePBX::create()->Sipsettings->getConfig('externip')) {
						$msg = _("External IP can not be blank when NAT Mode is set to Static and no default IP address provided on the main page");
						$vd->log_error($val, $key, $msg);
					} else {
						$save_settings[] = array($key,$val,'40',SIP_NORMAL);
					}
				}
			break;

			case 'externhost_val':
				if (trim($val) == '' && $sip_settings['nat_mode'] == 'externhost') {
					$msg = _("Dynamic Host can not be blank");
					$vd->log_error($val, $key, $msg);
				 }
				$save_settings[] = array($key,$val,'40',SIP_NORMAL);
			break;

			case 'jbenable':
				$save_settings[] = array($key,$val,'4',SIP_NORMAL);
			break;

			case 'jbforce':
			case 'jbimpl':
			case 'jblog':
				$save_settings[] = array($key,$val,'5',SIP_NORMAL);
			break;

			case 'jbmaxsize':
			case 'jbresyncthreshold':
				$msg = sprintf($integer_msg,$key);
				$save_settings[] = array($key,$db->escapeSimple($vd->is_int($val,$key,$msg)),'5',SIP_NORMAL);
			break;

			case 'nat_mode':
			case 'g726nonstandard':

			case 't38pt_udptl':
			case 'videosupport':
			case 'canreinvite':
			case 'notifyringing':
			case 'notifyhold':
			case 'allowguest':
			case 'srvlookup':
			case 'tlsbindaddr':
			case 'tlsdontverifyserver':
			case 'tlsclientmethod':
			case 'tlsenable':
				$save_settings[] = array($key,$val,'10',SIP_NORMAL);
			break;

			case 'ALLOW_SIP_ANON':
				$save_to_admin[] = array($key,$val);
			break;
		default:
			if (substr($key,0,9) == "localnet_") {
				// ip validate this and store
				$seq = substr($key,9);
				$msg = _("Localnet setting must be an IP address");
				$save_settings[] = array($key,$db->escapeSimple($vd->is_ip($val,$key,$msg)),(42+$seq),SIP_NORMAL);
			} else if (substr($key,0,8) == "netmask_") {
				// ip validate this and store
				$seq = substr($key,8);
				$msg = _("Localnet netmask must be formatted properly (e.g. 255.255.255.0 or 24)");
				$save_settings[] = array($key,$db->escapeSimple($vd->is_netmask($val,$key,$msg)),$seq,SIP_NORMAL);
			} else if (substr($key,0,15) == "sip_custom_key_") {
				$seq = substr($key,15);
				$save_settings[] = array($db->escapeSimple($val),$db->escapeSimple($sip_settings["sip_custom_val_$seq"]),($seq),SIP_CUSTOM);
			} else if (substr($key,0,15) == "sip_custom_val_") {
				// skip it, we will seek it out when we see the sip_custom_key
			} else {
				if (($key == 'tlsbindport') && ($req_owner == "sip") && (!(isset($prev_owner)) || ($prev_owner == "none"))) {
					$save_settings[] = array($key,$dbbindport,'0',SIP_NORMAL);
				} else {
					$save_settings[] = array($key,$val,'0',SIP_NORMAL);
				}
			}
		}
	}

	/* if there were any validation errors, we will return them and not proceed with saving */
	if (count($vd->errors)) {
		return $vd->errors;
	} else {
		 $fvcodecs = array();
		 $seq = 1;
		if(!empty($_REQUEST['vcodec'])) {
			foreach($_REQUEST['vcodec'] as $codec => $v) {
					$fvcodecs[$codec] = $seq++;
			}
		}
		if ($_REQUEST['category'] == "general" && $_REQUEST['Submit'] == "Submit"){
			FreePBX::Sipsettings()->setCodecs('video',$fvcodecs);
		}


		// TODO: normally don't like doing delete/insert but otherwise we would have do update for each
		//			 individual setting and then an insert if there was nothing to update. So this is cleaner
		//			 this time around.
		//
		sql("DELETE FROM `sipsettings` WHERE 1");
		$compiled = $db->prepare('INSERT INTO `sipsettings` (`keyword`, `data`, `seq`, `type`) VALUES (?,?,?,?)');
		$result = $db->executeMultiple($compiled,$save_settings);
		if(DB::IsError($result)) {
			die_freepbx($result->getDebugInfo()."<br><br>".'error adding to sipsettings table');
		}
		if (!empty($save_to_admin)) {
			$compiled = $db->prepare("REPLACE INTO `admin` (`variable`, `value`) VALUES (?,?)");
			$result = $db->executeMultiple($compiled,$save_to_admin);
			if(DB::IsError($result)) {
				die_freepbx($result->getDebugInfo()."<br><br>".'error adding to sipsettings table');
			}
		}
		return true;
	}
}
