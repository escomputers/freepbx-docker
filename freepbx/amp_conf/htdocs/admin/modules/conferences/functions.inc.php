<?php /* $Id$ */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//
//TODO: This should be moved into BMO
//its here so that other modules can hook into it
class conferences_conf {
	private static $obj;

	// FreePBX magic ::create() call
	public static function create() {
		if (!isset(self::$obj))
			self::$obj = new conferences_conf();

		return self::$obj;
	}


	function __construct() {
		$this->_confbridge['general'] = array();
		$this->_confbridge['user'] = array();
		$this->_confbridge['user']['default_user'] = array();
		$this->_confbridge['bridge'] = array();
		$this->_confbridge['bridge']['default_bridge'] = array();
		$this->_confbridge['menu'] = array();

		self::$obj = $this;
	}

	// return the filename to write
	function get_filename() {
		global $amp_conf;

		$files = array(
			'meetme_additional.conf',
		 	'confbridge_additional.conf',
		);

		return $files;
	}

	function addMeetme($room, $userpin, $adminpin='') {
		$this->_meetmes[$room] = $userpin.($adminpin != '' ? ','.$adminpin : '');
	}

	function addConfUser($section, $key, $value) {
		$this->_confbridge['user'][$section][$key] = $value;
	}

	function addConfBridge($section, $key, $value) {
		$this->_confbridge['bridge'][$section][$key] = $value;
	}

	function addConfMenu($section, $key, $value) {
		$this->_confbridge['menu'][$section][$key] = $value;
	}

	// return the output that goes in the file
	function generateConf($file) {
		global $amp_conf;
		global $version;

		$output = "";

		switch ($file) {
		case 'meetme_additional.conf':
			if ($amp_conf['ASTCONFAPP'] == 'app_meetme' && !empty($this->_meetmes)) {
				foreach (array_keys($this->_meetmes) as $meetme) {
					$output .= 'conf => '.$meetme.",".$this->_meetmes[$meetme]."\n";
				}
			}
		break;
		case 'confbridge_additional.conf':
			if ($amp_conf['ASTCONFAPP'] != 'app_confbridge' || version_compare($version, '10', 'lt')) {
				break;
			}
			if (empty($this->_confbridge['general'])) {
				$output .= "[general]\n";
				$output .= ";This section reserved for future use\n";
				$output .= "\n";
			}
			global $version;
			if(version_compare($version, '13.4', 'ge') || (version_compare($version, '11.20', 'ge') && version_compare($version, '13.0', 'lt'))) {
				$escapePound = '\#';
			} else {
				$escapePound = '#';
			}

			// Default if nothing configured
			if (empty($this->_confbridge['menu']['admin_menu'])) {
				$this->_confbridge['menu']['admin_menu'] = array(
					'*'  => 'playback_and_continue(conf-adminmenu)',
					'*1' => 'toggle_mute',
					'*2' => 'admin_toggle_conference_lock',
					'*3' => 'admin_kick_last',
					'*4' => 'decrease_listening_volume',
					'*5' => 'reset_listening_volume',
					'*6' => 'increase_listening_volume',
					'*7' => 'decrease_talking_volume',
					'*8' => 'reset_talking_volume',
					'*9' => 'increase_talking_volume',
					'*#'  => 'leave_conference',
					'*0' => 'admin_toggle_mute_participants',
				);
			}
			// Default if nothing configured
			if (empty($this->_confbridge['menu']['user_menu'])) {
				$this->_confbridge['menu']['user_menu'] = array(
					'*'  => 'playback_and_continue(conf-usermenu)',
					'*1' => 'toggle_mute',
					'*4' => 'decrease_listening_volume',
					'*5' => 'reset_listening_volume',
					'*6' => 'increase_listening_volume',
					'*7' => 'decrease_talking_volume',
					'*8' => 'no_op',
					'*9' => 'increase_talking_volume',
					'*#'  => 'leave_conference',
				);
			}

			foreach (array('user','bridge','menu') as $type) {
				foreach ($this->_confbridge[$type] as $section => $settings) {
					$output .= "[" . $section . "]\n";
					$output .= "type = " . $type . "\n";
					foreach ($settings as $key => $value) {
						switch ($key) {
							case 'timeout':
								//Timeout added in 13.7.0 And 14.0.0+
								if(version_compare($version, '13.7.0', 'lt')){
									continue 2;
								}
							break;
							default:
								$output .= $key . " = " . $value . "\n";
							break;
						}
					}
					$output .= "\n";
				}
			}
		break;
		}
		return $output;
	}
}

// returns a associative arrays with keys 'destination' and 'description'
function conferences_destinations() {
	$extens = array();
	//get the list of meetmes
	$results = conferences_list();

	// return an associative array with destination and description
	if (isset($results)) {
		foreach($results as $result){
			$extens[] = array(
				'destination' => 'ext-meetme,'.$result['0'].',1',
				'description' => $result['0']." ".$result['1'],
				'edit_url' => 'config.php?display=conferences&view=form&extdisplay='.urlencode($result[0])
			);
		}
		return $extens;
	} else {
		return null;
	}
}

function conferences_getdest($exten) {
	return array('ext-meetme,'.$exten.',1');
}

function conferences_getdestinfo($dest) {
	if (substr(trim($dest),0,11) == 'ext-meetme,') {
		$exten = explode(',',$dest);
		$exten = $exten[1];
		$thisexten = conferences_get($exten, false);
		if (empty($thisexten)) {
			return array();
		} else {
			return array('description' => sprintf(_("Conference Room %s : %s"),$exten,$thisexten['description']),
			             'edit_url' => 'config.php?display=conferences&view=form&extdisplay='.urlencode($exten),
					);
		}
	} else {
		return false;
	}
}

function conferences_recordings_usage($recording_id) {
	global $active_modules;

	$results = sql("SELECT `exten`, `description` FROM `meetme` WHERE `joinmsg_id` = '$recording_id'","getAll",DB_FETCHMODE_ASSOC);
	if (empty($results)) {
		return array();
	} else {
		foreach ($results as $result) {
			$usage_arr[] = array(
				'url_query' => 'config.php?display=conferences&extdisplay='.urlencode($result['exten']),
				'description' => sprintf(_("Conference: %s"),$result['description']),
			);
		}
		return $usage_arr;
	}
}

/* 	Generates dialplan for conferences
	We call this with retrieve_conf
*/
function conferences_get_config($engine) {
	global $ext, $conferences_conf, $version, $amp_conf, $astman;

	$ast_ge_162 = version_compare($version, '1.6.2', 'ge');
	$ast_ge_10 = version_compare($version, '10', 'ge');
	$ast_ge_1370 = version_compare($version, '13.7.0', 'ge');

	switch($engine) {
		case "asterisk":
			$ext->addInclude('from-internal-additional','ext-meetme');
			$contextname = 'ext-meetme';
			if ($conflist = FreePBX::Conferences()->getAllConferences()) {
				// Start the conference
				if ($amp_conf['ASTCONFAPP'] == 'app_confbridge' && $ast_ge_10) {
					$ext->add($contextname, 'STARTMEETME', '', new ext_execif('$["${MEETME_MUSIC}" != ""]','Set','CONFBRIDGE(user,music_on_hold_class)=${MEETME_MUSIC}'));
				}
				//Always reset the musicclass for the channel because inbound routes might have previously set one, or whereever we came from before
				//http://issues.freepbx.org/browse/FREEPBX-8782
				$ext->add($contextname, 'STARTMEETME', '', new ext_execif('$["${MEETME_MUSIC}" != ""]','Set','CHANNEL(musicclass)=${MEETME_MUSIC}'));

				$ext->add($contextname, 'STARTMEETME', '', new ext_setvar('GROUP(meetme)','${MEETME_ROOMNUM}'));
				$ext->add($contextname, 'STARTMEETME', '', new ext_gotoif('$[${MAX_PARTICIPANTS} > 0 && ${GROUP_COUNT(${MEETME_ROOMNUM}@meetme)}>${MAX_PARTICIPANTS}]','MEETMEFULL,1'));
				// No harm done if quietmode, these will just then be ignored
				//
				if ($amp_conf['ASTCONFAPP'] == 'app_confbridge' && !$ast_ge_10) {
					$ext->add($contextname, 'STARTMEETME', '', new ext_set('CONFBRIDGE_JOIN_SOUND','beep'));
					$ext->add($contextname, 'STARTMEETME', '', new ext_set('CONFBRIDGE_LEAVE_SOUND','beeperr'));
				}
				if ($amp_conf['ASTCONFAPP'] == 'app_confbridge' && $ast_ge_10) {
					$ext->add($contextname, 'STARTMEETME', '', new ext_meetme('${MEETME_ROOMNUM}',',','${MENU_PROFILE}'));
				} else {
					$ext->add($contextname, 'STARTMEETME', '', new ext_meetme('${MEETME_ROOMNUM}','${MEETME_OPTS}','${PIN}'));
				}

				$ext->add($contextname, 'STARTMEETME', '', new ext_macro('hangupcall'));

				//meetme full
				$ext->add($contextname, 'MEETMEFULL', '', new ext_playback('im-sorry&conf-full&goodbye'));
				$ext->add($contextname, 'MEETMEFULL', '', new ext_macro('hangupcall'));

				// hangup for whole context
				$ext->add($contextname, 'h', '', new ext_macro('hangupcall'));

				foreach($conflist as $room) {
					$roomnum = $room['exten'];
					$roomoptions = $room['options'];
					$roomusers = $room['users'];
					$roomuserpin = $room['userpin'];
					$roomadminpin = $room['adminpin'];

					// Add optional hint
					if ($amp_conf['USEDEVSTATE']) {

						$hint_pre = $amp_conf['ASTCONFAPP'] == 'app_meetme' ? 'MeetMe' : 'confbridge';
						$ext->addHint($contextname, $roomnum, $hint_pre . ":" . $roomnum);
						$hints[] = $hint_pre . ":" . $roomnum;
					}
					// entry point
					$ext->add($contextname, $roomnum, '', new ext_macro('user-callerid'));
					// added FREEPBX-14652 : set languge when dialed the particular conf no...
					$ext->add($contextname, $roomnum, '', new ext_execif('$["${DB(CONFERENCE/'.$roomnum.'/language)}" != ""]','Set','CHANNEL(language)=${DB(CONFERENCE/'.$roomnum.'/language)}'));
					$ext->add($contextname, $roomnum, '', new ext_setvar('MEETME_ROOMNUM',$roomnum));
					$ext->add($contextname, $roomnum, '', new ext_setvar('MAX_PARTICIPANTS', '0'));
					$ext->add($contextname, $roomnum, '', new ext_setvar('MEETME_MUSIC', '${MOHCLASS}'));
					$ext->add($contextname, $roomnum, '', new ext_execif('$["${DB(CONFERENCE/'.$roomnum.'/users)}" != ""]','Set','MAX_PARTICIPANTS=${DB(CONFERENCE/'.$roomnum.'/users)}'));
					$ext->add($contextname, $roomnum, '', new ext_execif('$["${DB(CONFERENCE/'.$roomnum.'/music)}" != "inherit" & "${DB(CONFERENCE/'.$roomnum.'/music)}" != ""]','Set','MEETME_MUSIC=${DB(CONFERENCE/'.$roomnum.'/music)}'));
					$ext->add($contextname, $roomnum, '', new ext_gotoif('$["${DIALSTATUS}" = "ANSWER"]','ANSWERED'));
					$ext->add($contextname, $roomnum, '', new ext_answer(''));
					$ext->add($contextname, $roomnum, '', new ext_wait(1));

					//Check if a pin exists
					$ext->add($contextname, $roomnum, 'ANSWERED', new ext_gotoif('$["${DB(CONFERENCE/'.$roomnum.'/userpin)}" = "" & "${DB(CONFERENCE/'.$roomnum.'/adminpin)}" = ""]','USER','CHECKPIN'));

					// Deal with PINs -- if exist
					//First check to see if the PIN variable has already been set (through a call file per say)
					$ext->add($contextname, $roomnum, 'CHECKPIN', new ext_gotoif('$["${PIN}" = ""]',"READPIN"));

					//Are user pin and admin pin both blank? Ok then they area user
					$ext->add($contextname, $roomnum, '', new ext_gotoif('$["${DB(CONFERENCE/'.$roomnum.'/userpin)}" = "" & "${DB(CONFERENCE/'.$roomnum.'/adminpin)}" = ""]','USER'));

					//Check if user
					$ext->add($contextname, $roomnum, '', new ext_gotoif('$["${DB(CONFERENCE/'.$roomnum.'/userpin)}" != "" & "${PIN}" = "${DB(CONFERENCE/'.$roomnum.'/userpin)}"]','USER'));

					//Check if admin
					$ext->add($contextname, $roomnum, '', new ext_gotoif('$["${DB(CONFERENCE/'.$roomnum.'/adminpin)}" != "" & "${PIN}" = "${DB(CONFERENCE/'.$roomnum.'/adminpin)}"]','ADMIN'));

					//No pins set so ask the user now
					$ext->add($contextname, $roomnum, 'READPIN', new ext_setvar('PINCOUNT','0'));

					// for i18n playback in multiple languages
					$ext->add($contextname, $roomnum, 'RETRYPIN', new ext_gosubif('$[${DIALPLAN_EXISTS('.$contextname.'-lang-playback,${CHANNEL(language)})}]', $contextname.'-lang-playback,${CHANNEL(language)},retrypin', $contextname.'-lang-playback,en,retrypin'));

					// userpin -- must do always, otherwise if there is just an adminpin
					// there would be no way to get to the conference !
					$ext->add($contextname, $roomnum, '', new ext_gotoif('$["${DB(CONFERENCE/'.$roomnum.'/userpin)}" != "" & "${PIN}" = "${DB(CONFERENCE/'.$roomnum.'/userpin)}"]','USER'));
					// admin pin -- exists
					$ext->add($contextname, $roomnum, '', new ext_gotoif('$["${DB(CONFERENCE/'.$roomnum.'/adminpin)}" != "" & "${PIN}" = "${DB(CONFERENCE/'.$roomnum.'/adminpin)}"]','ADMIN'));
					//fall back if no pin exists on this room they are a user
					$ext->add($contextname, $roomnum, '', new ext_gotoif('$["${DB(CONFERENCE/'.$roomnum.'/userpin)}" = ""]','USER'));
					// pin invalid
					$ext->add($contextname, $roomnum, '', new ext_setvar('PINCOUNT','$[${PINCOUNT}+1]'));
					$ext->add($contextname, $roomnum, '', new ext_gotoif('$[${PINCOUNT}>3]', "h,1"));
					$ext->add($contextname, $roomnum, '', new ext_playback('conf-invalidpin'));
					$ext->add($contextname, $roomnum, '', new ext_goto('RETRYPIN'));

					$subconfcontext = 'sub-conference-options';

					// admin mode -- only valid if there is an admin pin
					$ext->add($contextname, $roomnum, 'ADMIN', new ext_gosub('1', 's', $subconfcontext, $roomnum.',ADMIN'));
					$ext->add($contextname, $roomnum, '', new ext_gosub('1','s','sub-record-check',"conf,$roomnum," . (strstr($room['options'],'r') !== false ? 'always' : 'never')));
					$ext->add($contextname, $roomnum, '', new ext_execif('$["${DB(CONFERENCE/'.$roomnum.'/joinmsg)}" != ""]','Playback','${DB(CONFERENCE/'.$roomnum.'/joinmsg)}'));
					$ext->add($contextname, $roomnum, '', new ext_goto('STARTMEETME,1'));
					//end pin checking

					// user mode
					$ext->add($contextname, $roomnum, 'USER', new ext_gosub('1', 's', $subconfcontext, $roomnum.',USER'));
					$ext->add($contextname, $roomnum, '', new ext_gosub('1','s','sub-record-check',"conf,$roomnum," . (strstr($room['options'],'r') !== false ? 'always' : 'never')));
					$ext->add($contextname, $roomnum, '', new ext_execif('$["${DB(CONFERENCE/'.$roomnum.'/joinmsg)}" != ""]','Playback','${DB(CONFERENCE/'.$roomnum.'/joinmsg)}'));
					$ext->add($contextname, $roomnum, '', new ext_goto('STARTMEETME,1'));

					// add meetme config
					if ($amp_conf['ASTCONFAPP'] == 'app_meetme') {
						$conferences_conf->addMeetme($room['exten'],$room['userpin'],$room['adminpin']);
					}
				}

				//en English
				$lang = 'en';
				$ext->add($contextname."-lang-playback", $lang, 'retrypin', new ext_read('PIN','enter-conf-pin-number'));
				$ext->add($contextname."-lang-playback", $lang, '', new ext_return());

				//Language Corrections
				foreach(array('it', 'en_NZ', 'en_AU', 'cs', 'fa', 'fr', 'he', 'ja', 'nl', 'no', 'pl', 'ru', 'sv', 'tr') as $lang) {
					$ext->add($contextname."-lang-playback", $lang, 'retrypin', new ext_read('PIN','conf-getpin'));
					$ext->add($contextname."-lang-playback", $lang, '', new ext_return());
				}

				$fcc = new featurecode('conferences', 'conf_status');
				$conf_code = $fcc->getCodeActive();
				unset($fcc);

				if ($conf_code != '') {
					$ext->add($contextname, $conf_code, '', new ext_macro('hangupcall'));
					if ($amp_conf['USEDEVSTATE']) {
						$ext->addHint($contextname, $conf_code, implode('&', $hints));
					}
				}

				$subconfcontext = 'sub-conference-options';
				$ext->add($subconfcontext, 's', '', new ext_noop('Setting options for Conference ${ARG1}'));
				//
				$ext->add($subconfcontext, 's', '', new ext_execif('$["${DB(CONFERENCE/${ARG1}/language)}" == ""]','Set','CONFBRIDGE(bridge,language)=${CHANNEL(language)}','Set','CONFBRIDGE(bridge,language)=${DB(CONFERENCE/${ARG1}/language)}'));
				$ext->add($subconfcontext, 's', '', new ext_goto('${ARG2}'));
				if ($amp_conf['ASTCONFAPP'] == 'app_confbridge' && $ast_ge_10) {
					//w
					$ext->add($subconfcontext, 's', 'USER', new ext_execif('${REGEX("w" ${DB(CONFERENCE/${ARG1}/options)})}','Set','CONFBRIDGE(user,wait_marked)=yes'));
					$ext->add($subconfcontext, 's', '', new ext_execif('${REGEX("x" ${DB(CONFERENCE/${ARG1}/options)})}','Set','CONFBRIDGE(user,end_marked)=yes'));

					//s
					$ext->add($subconfcontext, 's', '', new ext_execif('${REGEX("s" ${DB(CONFERENCE/${ARG1}/options)})}','Set','MENU_PROFILE=user_menu'));

					//m
					$ext->add($subconfcontext, 's', '', new ext_execif('${REGEX("m" ${DB(CONFERENCE/${ARG1}/options)})}','Set','CONFBRIDGE(user,startmuted)=yes'));

					$ext->add($subconfcontext, 's', '', new ext_goto('RETURN'));
					$ext->add($subconfcontext, 's', 'ADMIN', new ext_setvar('CONFBRIDGE(user,admin)','yes'));
					$ext->add($subconfcontext, 's', '', new ext_setvar('CONFBRIDGE(user,marked)','yes'));

					//s
					$ext->add($subconfcontext, 's', '', new ext_execif('${REGEX("s" ${DB(CONFERENCE/${ARG1}/options)})}','Set','MENU_PROFILE=admin_menu'));

					$ext->add($subconfcontext, 's', '', new ext_goto('RETURN'));
				} else {
					$ext->add($subconfcontext, 's', 'USER', new ext_noop('meetme user'));
					$ext->add($subconfcontext, 's', '', new ext_setvar('MEETME_OPTS','${DB(CONFERENCE/${ARG1}/options)}'));
					$ext->add($subconfcontext, 's', '', new ext_goto('RETURN'));
					$ext->add($subconfcontext, 's', 'ADMIN', new ext_noop('meetme admin'));
					$ext->add($subconfcontext, 's', '', new ext_setvar('MEETME_OPTS','aA${DB(CONFERENCE/${ARG1}/options)}'));
					$ext->add($subconfcontext, 's', '', new ext_setvar('MEETME_OPTS','${REPLACE(MEETME_OPTS,"m","")}'));
					$ext->add($subconfcontext, 's', '', new ext_goto('RETURN'));
				}
				if ($amp_conf['ASTCONFAPP'] == 'app_confbridge' && $ast_ge_10) {
					$ext->add($subconfcontext, 's', 'RETURN', new ext_noop('Setting Additional Options:'));
					//q
					$ext->add($subconfcontext, 's', '', new ext_execif('${REGEX("q" ${DB(CONFERENCE/${ARG1}/options)})}','Set','CONFBRIDGE(user,quiet)=yes'));
					//c
					$ext->add($subconfcontext, 's', '', new ext_execif('${REGEX("c" ${DB(CONFERENCE/${ARG1}/options)})}','Set','CONFBRIDGE(user,announce_user_count)=yes'));
					//I
					$ext->add($subconfcontext, 's', '', new ext_execif('${REGEX("I" ${DB(CONFERENCE/${ARG1}/options)})}','Set','CONFBRIDGE(user,announce_join_leave)=yes'));
					//o
					$ext->add($subconfcontext, 's', '', new ext_execif('${REGEX("o" ${DB(CONFERENCE/${ARG1}/options)})}','Set','CONFBRIDGE(user,dsp_drop_silence)=yes'));
					//T
					$ext->add($subconfcontext, 's', '', new ext_execif('${REGEX("T" ${DB(CONFERENCE/${ARG1}/options)})}','Set','CONFBRIDGE(user,talk_detection_events)=yes'));
					//M
					$ext->add($subconfcontext, 's', '', new ext_execif('${REGEX("M" ${DB(CONFERENCE/${ARG1}/options)})}','Set','CONFBRIDGE(user,music_on_hold_when_empty)=yes'));

					if ($ast_ge_1370){
						$ext->add($subconfcontext, 's', '', new ext_execif('$["${DB(CONFERENCE/${ARG1}/timeout)}" != ""]','Set','CONFBRIDGE(user,timeout)=${DB(CONFERENCE/${ARG1}/timeout)}'));
					}

					$ext->add($subconfcontext, 's', '', new ext_return());
				} else {
					$ext->add($subconfcontext, 's', 'RETURN', new ext_return());
				}
			}

		break;
	}
}

function conferences_check_extensions($exten=true) {
	$extenlist = array();
	if (is_array($exten) && empty($exten)) {
		return $extenlist;
	}
	$sql = "SELECT exten, description FROM meetme ";
	if (is_array($exten)) {
		$sql .= "WHERE exten in ('".implode("','",$exten)."')";
	}
	$sql .= " ORDER BY exten";
	$results = sql($sql,"getAll",DB_FETCHMODE_ASSOC);

	foreach ($results as $result) {
		$thisexten = $result['exten'];
		$extenlist[$thisexten]['description'] = _("Conference: ").$result['description'];
		$extenlist[$thisexten]['status'] = 'INUSE';
		$extenlist[$thisexten]['edit_url'] = 'config.php?display=conferences&extdisplay='.urlencode($thisexten);
	}
	return $extenlist;
}

//get the existing meetme extensions
function conferences_list() {
	return FreePBX::Conferences()->listConferences();
}

function conferences_get($account, $processAstDb = true){
	return FreePBX::Conferences()->getConference($account, $processAstDb);
}

function conferences_del($account){
	return FreePBX::Conferences()->deleteConference($account);
}

function conferences_add($account,$name,$userpin,$adminpin,$options,$joinmsg_id=null,$music='',$users=0, $timeout=21600){
	return FreePBX::Conferences()->addConference($account,$name,$userpin,$adminpin,$options,$joinmsg_id,$music,$users,$timeout);
}
?>
