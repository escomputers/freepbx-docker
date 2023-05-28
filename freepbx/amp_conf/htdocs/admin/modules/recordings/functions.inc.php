<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//

// Source and Destination Dirctories for recording
global $recordings_astsnd_path; // PHP5 needs extra convincing of a global
global $amp_conf;
$recordings_save_path = $amp_conf['ASTSPOOLDIR']."/tmp/";
$recordings_astsnd_path = isset($asterisk_conf['astvarlibdir'])?$asterisk_conf['astvarlibdir']:'/var/lib/asterisk';
$recordings_astsnd_path .= "/sounds/";

function recordings_get_config($engine) {
	global $ext;  // is this the best way to pass this?
	global $recordings_save_path;
	global $version;

	$ast_ge_14 = version_compare($version, '1.4', 'ge');
	$ast_ge_16 = version_compare($version, '1.6', 'ge');

	$modulename = "recordings";
	$appcontext = "app-recordings";

	switch($engine) {
		case "asterisk":
			// Now generate the Feature Codes to edit recordings
			//
			$recordings = recordings_list();
			$ext->addInclude('from-internal-additional', 'app-recordings'); // Add the include from from-internal
			foreach ($recordings as $item) {

				$ext->add('play-system-recording',$item['id'],'',new ext_answer());
				$ext->add('play-system-recording',$item['id'],'',new ext_playback($item['filename']));
				$ext->add('play-system-recording',$item['id'],'',new ext_hangup());

				// Get the feature code, and do a sanity check if it is not suppose to be active and delete it
				//
				if ($item['fcode'] != 0) {
					$fcc = new featurecode($modulename, 'edit-recording-'.$item['id']);
					$fcode = $fcc->getCodeActive();
					unset($fcc);
				} else {
					$fcc = new featurecode('recordings', 'edit-recording-'.$item['id']);
					$fcc->delete();
					unset($fcc);
					continue; // loop back to foreach
				}

				if ($fcode != '') {
					// Do a sanity check, there should be no compound files
					//
					if (strpos($item['filename'], '&') === false && trim($item['filename']) != '') {
						$fcode_pass = (trim($item['fcode_pass']) != '') ? $item['fcode_pass'] : '';
						$fcode_lang = (trim($item['fcode_lang']) != '') ? ','.$item['fcode_lang'] : '';
						$ext->add($appcontext, $fcode, '', new ext_macro('user-callerid'));
						$ext->add($appcontext, $fcode, '', new ext_wait('2'));
						$ext->add($appcontext, $fcode, '', new ext_macro('systemrecording', 'docheck,'.$item['filename'].','.$fcode_pass.$fcode_lang));
						//$ext->add($appcontext, $fcode, '', new ext_macro('hangup'));
					}
				}
			}

			// moved from modules/core to modules/recordings
			// since it really belongs here and not there
			// also provides direct access to $recordings_save_path
			// which removes a hard-coded value in the macro
			$context = 'systemrecording-gui';
			$exten = 'dorecord';

			$ext->add($context, $exten, '', new ext_record('${RECFILE}.wav,,,k'));
			$ext->add($context, 'h', '', new ext_system('touch ${RECFILE}.finished'));
			$ext->add($context, 'h', 'exit', new ext_hangup());

			$context = 'macro-systemrecording';

			$ext->add($context, 's', '', new ext_gotoif('$["${ARG2}" = ""]','invalid'));
			$ext->add($context, 's', '', new ext_setvar('TMPLANG','${CHANNEL(language)}'));
			$ext->add($context, 's', '', new ext_execif('$["${ARG4}" != ""]','Set','TMPLANG='.'${ARG4}'));
			$ext->add($context, 's', '', new ext_setvar('RECFILE','${TMPLANG}/${ARG2}'));
			$ext->add($context, 's', '', new ext_setvar('LISTEN','docheck'));
			$ext->add($context, 's', '', new ext_execif('$["${ARG3}" != ""]','Authenticate','${ARG3}'));
			$ext->add($context, 's', '', new ext_goto(1, '${ARG1}'));

			$exten = 'dorecord';

			// Delete all versions of the current sound file (does not consider languages though
			// otherwise you might have some versions that are not re-recorded
			//
			$ext->add($context, $exten, '', new ext_setvar('TMPRECFILE','${RECFILE}-TMP'));
			$ext->add($context, $exten, '', new ext_background('say-temp-msg-prs-pound,,${CHANNEL(language)}'));
			$ext->add($context, $exten, '', new ext_record('${TMPRECFILE}.${CHANNEL(audioreadformat)},,,k'));
			$ext->add($context, $exten, '', new ext_setvar('LISTEN','dochecknolanguage'));
			$ext->add($context, $exten, '', new ext_goto(1, 'confmenu'));

			$exten = 'dochecknolanguage';

			$ext->add($context, $exten, '', new ext_playback('beep'));
			$ext->add($context, $exten, 'dc_start', new ext_background('${TMPRECFILE},m,,macro-systemrecording'));
			$ext->add($context, $exten, '', new ext_wait(1));
			$ext->add($context, $exten, '', new ext_goto(1, 'confmenu'));

			$exten = 'docheck';

			$ext->add($context, $exten, '', new ext_playback('beep'));
			$ext->add($context, $exten, 'dc_start', new ext_background('${RECFILE},m,${CHANNEL(language)},macro-systemrecording'));
			$ext->add($context, $exten, '', new ext_wait(1));
			$ext->add($context, $exten, '', new ext_goto(1, 'confmenu'));

			$exten = 'confmenu';
			$ext->add($context, $exten, '', new ext_background('to-listen-to-it&press-1&to-accept-recording&press-2&to-rerecord-it&press-star&language&press-3,m,${CHANNEL(language)},macro-systemrecording'));
			$ext->add($context, $exten, '', new ext_read('RECRESULT', '', 1, '', '', 4));
			$ext->add($context, $exten, '', new ext_gotoif('$["x${RECRESULT}"="x*"]', 'dorecord,1'));
			$ext->add($context, $exten, '', new ext_gotoif('$["x${RECRESULT}"="x1"]', '${LISTEN},2'));
			$ext->add($context, $exten, '', new ext_gotoif('$["x${RECRESULT}"="x2"]', 'doaccept,1'));
			$ext->add($context, $exten, '', new ext_gotoif('$["x${RECRESULT}"="x3"]', 'switchlang,1'));
			$ext->add($context, $exten, '', new ext_goto(1));

			$exten = 'doaccept';
			$ext->add($context, $exten, '', new ext_setvar('EXISTS','${STAT(e,${ASTVARLIBDIR}/sounds/${TMPRECFILE}.${CHANNEL(audioreadformat)})}'));
			$ext->add($context, $exten, '', new ext_noop('${EXISTS}'));
			$ext->add($context, $exten, '', new ext_gotoif('$["${EXISTS}" != "1"]', 'exit'));
			$ext->add($context, $exten, '', new ext_system('touch ${ASTVARLIBDIR}/sounds/${RECFILE}.finished'));
			$ext->add($context, $exten, '', new ext_gotoif('$["x${TMPRECFILE}"="x"]', 'exit'));
			$ext->add($context, $exten, '', new ext_system('mv ${ASTVARLIBDIR}/sounds/${TMPRECFILE}.${CHANNEL(audioreadformat)} ${ASTVARLIBDIR}/sounds/${RECFILE}.${CHANNEL(audioreadformat)}'));
			$ext->add($context, $exten, '', new ext_playback('wait-moment'));
			$ext->add($context, $exten, '', new ext_agi('recordings.agi'));
			$ext->add($context, $exten, '', new ext_setvar('TMPRECFILE','${RECFILE}'));
			$ext->add($context, $exten, 'exit', new ext_playback('auth-thankyou'));
			$ext->add($context, $exten, '', new ext_goto(1, 'confmenu'));

			$exten = 'switchlang';
			$ext->add($context, $exten, '', new ext_playback('language&is-set-to'));
			$ext->add($context, $exten, '', new ext_sayalpha('${TMPLANG}'));
			$ext->add($context, $exten, '', new ext_playback('after-the-tone'));
			$langs = \FreePBX::Soundlang()->getLanguages();
			$c = 1;
			foreach($langs as $l => $d) {
				$ext->add($context, $exten, '', new ext_background('press-'.$c));
				$ext->add($context, $exten, '', new ext_sayalpha($l));
				$c++;
			}
			$ext->add($context, $exten, '', new ext_playback('beep'));
			$ext->add($context, $exten, '', new ext_read('LANGRESULT', '', 1, '', '', 4));
			$c = 1;
			foreach($langs as $l => $d) {
				$ext->add($context, $exten, '', new ext_execif('$["x${LANGRESULT}"="x'.$c.'"]', 'Set', 'TMPLANG='.$l));
				$c++;
			}
			$ext->add($context, $exten, '', new ext_setvar('RECFILE','${TMPLANG}/${ARG2}'));
			$ext->add($context, $exten, '', new ext_playback('language&is-set-to'));
			$ext->add($context, $exten, '', new ext_sayalpha('${TMPLANG}'));
			$ext->add($context, $exten, '', new ext_goto(1, 'confmenu'));

			$exten = 'invalid';
			$ext->add($context, $exten, '', new ext_playback('pm-invalid-option'));
			$ext->add($context, $exten, '', new ext_hangup());

			$ext->add($context, '1', '', new ext_goto('dc_start', '${LISTEN}'));
			$ext->add($context, '2', '', new ext_goto(1, 'doaccept'));
			$ext->add($context, '3', '', new ext_goto(1, 'switchlang'));
			$ext->add($context, '*', '', new ext_goto(1, 'dorecord'));

			$ext->add($context, 't', '', new ext_playback('goodbye'));
			$ext->add($context, 't', '', new ext_hangup());

			$ext->add($context, 'i', '', new ext_playback('pm-invalid-option'));
			$ext->add($context, 'i', '', new ext_goto(1, 'confmenu'));

			$ext->add($context, 'h', '', new ext_system('touch ${ASTVARLIBDIR}/sounds/${RECFILE}.finished'));
			$ext->add($context, 'h', '', new ext_gotoif('$["x${TMPRECFILE}"="x"]', 'exit'));
			$ext->add($context, 'h', '', new ext_system('mv ${ASTVARLIBDIR}/sounds/${TMPRECFILE}.${CHANNEL(audioreadformat)} ${ASTVARLIBDIR}/sounds/${CHANNEL(language)}/${RECFILE}.${CHANNEL(audioreadformat)}'));
			$ext->add($context, 'h', 'exit', new ext_hangup());

		break;
	}
}

function recordings_get_file($id) {
	return FreePBX::Recordings()->getFilenameById($id);
}


function recordings_list($compound=true) {
	return FreePBX::Recordings()->getAllRecordings($compound);
}

function recordings_get($id) {
	return FreePBX::Recordings()->getRecordingsById($id);
}


// returns a associative arrays with keys 'destination' and 'description'
function recordings_destinations() {
	$recs = recordings_list();
	$dests = array();
	if (!empty($recs)) {
		foreach ($recs as $r) {
			$dests[] = array('destination' => "play-system-recording,".$r['id'].",1", 'description' => $r['displayname'], 'category' => "Play Recording", 'edit_url' => 'config.php?display=recordings&action=edit&id='.$r['id']);
		}
	}
	return $dests;
}

function recordings_check_destinations($dest=true) {

	$rs = recordings_destinations();
	$rs = is_array($rs) ? $rs : array();

	$destlist = array();
	if (is_array($dest) && empty($dest)) {
		return $destlist;
	}

	$results = array();
	if ($dest === true) {
		$results = $rs;
	} else {
		foreach ($rs as $fc) {
			if (in_array($fc['destination'], $dest)) {
				$results[] = $fc;
			}
		}
	}

	foreach ($results as $result) {
		$destlist[] = array(
			'dest' => $result['destination'],
			'description' => $result['description'],
			'edit_url' => $result['edit_url'],
		);
	}
	return $destlist;
}


function recordings_getdest($exten) {
	return array('play-system-recording,'.$exten.',1');
}

function recordings_getdestinfo($dest) {
	global $active_modules;


	if (substr(trim($dest),0,21) == 'play-system-recording') {
		$exten = explode(',',$dest);

		$thisexten = recordings_get($exten[1]);
		if (empty($thisexten)) {
			return array();
		} else {
			return array(
				'description' => sprintf(_("Play Recording: %s"), $thisexten['displayname']),
				'edit_url' => 'config.php?display=recordings&action=edit&id='.urlencode($exten[1]),
			);
		}
	} else {
		return false;
	}
}
