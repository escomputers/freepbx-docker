<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//
$display_data = array();

/* All extensions. */
$extens		 = core_users_list();
/* All voicemail.conf settings. */
$uservm		 = voicemail_getVoicemail();
/* VMAIL info - needed for rnav menu and other page content. */
$vmail_info["activated_info"]	 = array();
$vmail_info["bycontext"]				= array();
$vmail_info["unactivated_info"] = array();
$vmail_info["disabled_list"]		= array();
$vmail_info["contexts"]				 = array();
$vmail_info["contexts"] 	= array_keys($uservm);		/* All voicemail contexts. */

$extdisplay 			= isset($_REQUEST["ext"])?$_REQUEST["ext"]:"";
$type				= (isset($_REQUEST["type"]) && $_REQUEST["type"] != "")?$_REQUEST["type"]:"setup";
$display			= (isset($_REQUEST["display"]) && $_REQUEST["display"] != "")?$_REQUEST["display"]:"voicemail";

$rnav_list				= "";
$rnav_enabled_index 		= array();
$rnav_entries 			= array();

/* Activated mailboxes are those which have a subdirectory on disk. */
global $amp_conf;
$vmail_root = "/" . trim($amp_conf["ASTSPOOLDIR"] , "/") . "/voicemail";

if (isset($extens) && is_array($extens)) {
	$i = 0;
	foreach ($extens as $key => $exten) {
		$vmbox = null;
		/* Voicemail is enabled for this extension when it is associated with a Voicemail context. */
		foreach ($vmail_info["contexts"] as $vmcontext) {
			if (isset($uservm[$vmcontext][$exten[0]])) {
				$vmbox["context"] = $vmcontext;
				break;
			}
		}

		/* FOR RNAV MENU */
		$name = $exten[1];
		$unactivated_style = "";
		$unactivated_txt = "";
		$disabled_style = "";
		$disabled_txt = "";
		$c = "";
		$c = isset($vmbox["context"])?$vmbox["context"]:"";
		if ($vmbox !== null) {
			$vmail_info["bycontext"][$vmbox["context"]][] = $exten[0];
			$vmbox["path"] = $vmail_root . "/" . $vmbox["context"] . "/" . $exten[0];
			$rnav_enabled_index[$vmbox["context"]][] = $i;
			if (is_dir($vmbox["path"])) {
				$vmail_info["activated_info"][$exten[0]] = $vmbox["context"];
			} else {
				$vmail_info["unactivated_info"][$exten[0]] = $vmbox["context"];
				$unactivated_style = " style='background: #4FB580;'";
				$unactivated_txt = " [unactivated]";
			}
			$link = "config.php?type=" . $type . "&display=" . $display . "&ext=" . $exten[0] . "&action=bsettings#" . $exten[0];
		} else {
			/* Voicemail is disabled for this extension. */
			$vmail_info["disabled_list"][] = $exten[0];
			$disabled_txt = "disabled";
			$disabled_style = " style='background: #ffffcc; text-decoration: line-through;'";
			/* Distinguish between "extensions" and "deviceanduser" modes. */
			if (isset($amp_conf["AMPEXTENSIONS"]) && ($amp_conf["AMPEXTENSIONS"] == "extensions")) {
				$link = "config.php?type=setup&display=extensions&extdisplay=" . $exten[0] . "#" . $exten[0];
			} else {
				$link = "config.php?type=setup&display=users&extdisplay=" . $exten[0] . "#" . $exten[0];
			}
		}
		$rnav_entries[$i] = "<a" . ($extdisplay==$exten[0] ? ' class="list-group-item active"':' class="list-group-item"') . "${disabled_style}${unactivated_style} href=\"$link\" onHover='menuUpdatePos();'>{$name} &lt;" . $exten[0] . "&gt;&nbsp;&nbsp;(${c}${disabled_txt})${unactivated_txt}</a></li>\n";
		$i++;
	}
}

/* End VMAIL info processing. */

/* Settings options */
$dlen = 800;	/* default max length on text entry */
$gen_settings = array(		"adsifdn" 			=> array("ver" => 1.2, "len" => 4, "type" => "char", "default" => ""),
				"adsisec" 			=> array("ver" => 1.2, "len" => 4, "type" => "char", "default" => ""),
				"adsiver" 			=> array("ver" => 1.2, "len" => $dlen, "type" => "num", "default" => ""),
				"attach" 			=> array("ver" => 1.2, "len" => $dlen, "type" => "flag", "default" => "yes"),
				"authpassword" 			=> array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
        			"authuser" 			=> array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"backupdeleted" 		=> array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"callback" 			=> array("ver" => 1.2, "len" => 80, "type" => "char", "default" => ""),
				"charset"  			=> array("ver" => 1.2, "len" => 32, "type" => "char", "default" => ""),
        			"cidinternalcontexts" 		=> array("ver" => 1.2, "len" => 640, "type" => "char", "default" => ""),
				"dialout"  			=> array("ver" => 1.2, "len" => 80, "type" => "char", "default" => ""),
				"emailbody" 			=> array("ver" => 1.2, "len" => $dlen, "type" => "char", "default" => ""),
				"emaildateformat" 		=> array("ver" => 1.2, "len" => 32, "type" => "char", "default" => ""),
				"emailsubject"                  => array("ver" => 1.2, "len" => $dlen, "type" => "char", "default" => ""),
				"envelope"                      => array("ver" => 1.2, "len" => $dlen, "type" => "flag", "default" => "yes"),
				"exitcontext"                   => array("ver" => 1.2, "len" => 80, "type" => "char", "default" => ""),
				"expungeonhangup"               => array("ver" => 1.2, "len" => $dlen, "type" => "char", "default" => ""),
				"externnotify"                  => array("ver" => 1.2, "len" => 160, "type" => "char", "default" => ""),
				"externpass"                    => array("ver" => 1.2, "len" => 128, "type" => "char", "default" => ""),
				"externpasscheck"               => array("ver" => 1.2, "len" => 128, "type" => "char", "default" => ""),
				"externpassnotify"              => array("ver" => 1.6, "len" => 128, "type" => "char", "default" => ""),
				"forcegreetings"                => array("ver" => 1.2, "len" => $dlen, "type" => "flag", "default" => "no"),
				"forcename"                     => array("ver" => 1.2, "len" => $dlen, "type" => "flag", "default" => "yes"),
				"format"                        => array("ver" => 1.2, "len" => 80, "type" => "char", "default" => ""),
				"fromstring"                    => array("ver" => 1.2, "len" => 100, "type" => "char", "default" => ""),
				"greetingsfolder"               => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"imapclosetimeout"              => array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"imapflags"                     => array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"imapfolder"                    => array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"imapgreetings"                 => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"imapopentimeout"               => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"imapparentfolder"              => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"imapport"                      => array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"imapreadtimeout"               => array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"imapserver"                    => array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"imapwritetimeout"              => array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"listen-control-forward-key"    => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"listen-control-pause-key"      => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"listen-control-restart-key"    => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"listen-control-reverse-key"    => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"listen-control-stop-key"       => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"mailcmd"                       => array("ver" => 1.2, "len" => $dlen, "type" => "char", "default" => ""),
				"maxgreet"                      => array("ver" => 1.2, "len" => $dlen, "type" => "num", "default" => ""),
				"maxlogins"                     => array("ver" => 1.2, "len" => $dlen, "type" => "num", "default" => ""),
				"maxmsg"                        => array("ver" => 1.2, "len" => $dlen, "type" => "num", "default" => ""),
				"minpassword"                   => array("ver" => 1.8, "len" => 4, "type" => "num", "default" => ""),
				"maxsecs"                       => array("ver" => 1.6, "len" => $dlen, "type" => "num", "default" => ""),
				"maxsilence"                    => array("ver" => 1.2, "len" => $dlen, "type" => "num", "default" => ""),
				"minsecs"                       => array("ver" => 1.6, "len" => $dlen, "type" => "num", "default" => ""),
				"moveheard"                     => array("ver" => 1.6, "len" => 0, "type" => "flag", "default" => "no"),
				"nextaftercmd"                  => array("ver" => 1.2, "len" => 0, "type" => "flag", "default" => "no"),
				"odbcstorage"                   => array("ver" => 1.2, "len" => $dlen, "type" => "char", "default" => ""),
				"odbctable"                     => array("ver" => 1.2, "len" => $dlen, "type" => "char", "default" => ""),
				"operator"                      => array("ver" => 1.2, "len" => $dlen, "type" => "flag", "default" => "yes"),
				"pagerbody"                     => array("ver" => 1.2, "len" => $dlen, "type" => "char", "default" => ""),
				"pagerfromstring"               => array("ver" => 1.2, "len" => 100, "type" => "char", "default" => ""),
				"pagersubject"                  => array("ver" => 1.2, "len" => $dlen, "type" => "char", "default" => ""),
				"pbxskip"                       => array("ver" => 1.2, "len" => 0, "type" => "flag", "default" => "no"),
				"pollfreq"                      => array("ver" => 1.6, "len" => $dlen, "type" => "num", "default" => "30"),
				"pollmailboxes"                 => array("ver" => 1.6, "len" => 0, "type" => "flag", "default" => "yes"),
				"review"                        => array("ver" => 1.2, "len" => $dlen, "type" => "flag", "default" => "no"),
				"saycid"                        => array("ver" => 1.2, "len" => $dlen, "type" => "flag", "default" => "no"),
				"sayduration"                   => array("ver" => 1.2, "len" => $dlen, "type" => "flag", "default" => "yes"),
				"saydurationm"                  => array("ver" => 1.2, "len" => $dlen, "type" => "num", "default" => ""),
				"searchcontexts"                => array("ver" => 1.2, "len" => 0, "type" => "flag", "default" => "no"),
				"sendvoicemail"                 => array("ver" => 1.2, "len" => 0, "type" => "flag", "default" => "yes"),
				"serveremail"                   => array("ver" => 1.2, "len" => 80, "type" => "char", "default" => ""),
				"silencethreshold"              => array("ver" => 1.2, "len" => $dlen, "type" => "num", "default" => ""),
				"skipms"                        => array("ver" => 1.2, "len" => $dlen, "type" => "num", "default" => ""),
				"smdienable"                    => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"smdiport"                      => array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"tempgreetwarn"                 => array("ver" => 1.4, "len" => $dlen, "type" => "flag", "default" => "yes"),
				"usedirectory"                  => array("ver" => 1.4, "len" => 0, "type" => "flag", "default" => "yes"),
				"userscontext"                  => array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"vm-mismatch"                   => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"vm-newpassword"                => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"vm-passchanged"                => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"vm-password"                   => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"vm-reenterpassword"            => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"vm-invalid-password"            => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"vm-pls-try-again"            => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"vm-prepend-timeout"            => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"directoryintro"            => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"volgain" 			=> array("ver" => 1.4, "len" => $dlen, "type" => "num", "default" => "") 	);

$acct_settings = array(		"attach"			=> array("ver" => 1.2, "len" => 0,	"type" => "flag"),
				"attachfmt"			=> array("ver" => 1.4, "len" => 20, "type" => "char"),
				"backupdeleted"			=> array("ver" => 1.6, "len" => 0,	"type" => "num"),
				"callback"			=> array("ver" => 1.2, "len" => 80, "type" => "char"),
				"callmenum"			=> array("ver" => 1.2, "len" => 0,	"type" => "num"),
				"delete"			=> array("ver" => 1.2, "len" => 0,	"type" => "flag"),
				"dialout"			=> array("ver" => 1.2, "len" => 80, "type" => "char"),
				"email"												 => array("ver" => 1.2, "len" => 80, "type" => "char"),
				"envelope"			=> array("ver" => 1.2, "len" => 0,	"type" => "flag"),
				"exitcontext"			=> array("ver" => 1.2, "len" => 80, "type" => "char"),
				"forcegreetings"		=> array("ver" => 1.2, "len" => 0,	"type" => "flag"),
				"forcename"			=> array("ver" => 1.2, "len" => 0,	"type" => "flag"),
				"hidefromdir"			=> array("ver" => 1.2, "len" => 0,	"type" => "flag"),
				"imappassword"			=> array("ver" => 1.4, "len" => 80, "type" => "char"),
				"imapuser"			=> array("ver" => 1.4, "len" => 80, "type" => "char"),
				"language"			=> array("ver" => 1.4, "len" => 20, "type" => "char"),
				"maxmsg"			=> array("ver" => 1.2, "len" => 0,	"type" => "num"),
				"maxsecs"			=> array("ver" => 1.6, "len" => 0,	"type" => "num"),
				"moveheard"			=> array("ver" => 1.6, "len" => 0,	"type" => "flag"),
				"name"										=> array("ver" => 1.2, "len" => 80, "type" => "char"),
				"operator"			=> array("ver" => 1.2, "len" => 0,	"type" => "flag"),
				"pager"												 => array("ver" => 1.2, "len" => 80, "type" => "char"),
				"pwd"													 => array("ver" => 1.2, "len" => 80, "type" => "charpass"),
				"review"			=> array("ver" => 1.2, "len" => 0,	"type" => "flag"),
				"saycid"			=> array("ver" => 1.2, "len" => 0,	"type" => "flag"),
				"sayduration"			=> array("ver" => 1.2, "len" => 0,	"type" => "flag"),
				"saydurationm"			=> array("ver" => 1.2, "len" => 0,	"type" => "num"),
				"sendvoicemail"			=> array("ver" => 1.2, "len" => 0,	"type" => "flag"),
				"serveremail"			=> array("ver" => 1.2, "len" => 80, "type" => "char"),
				"tempgreetwarn"			=> array("ver" => 1.4, "len" => 0,	"type" => "flag"),
				"tz"				=> array("ver" => 1.2, "len" => 80, "type" => "char"),
				"vmcontext"			=> array("ver" => 1.2, "len" => 80, "type" => "char"),
				"volgain"			=> array("ver" => 1.4, "len" => 0,	"type" => "num")
			);

$tooltips = array("tz" 	    => array("name" 				=> _("Timezone definition name"),
				     "def"				=> _("Time announcement for message playback"),
				     "del"				=> _("Remove the timezone definition")),
	          "general" => array("adsifdn"				=> _("The ADSI feature descriptor number to download to"),
				     "adsisec"				=> _("The ADSI security lock code"),
				     "adsiver"				=> _("The ADSI Voicemail application version number."),
				     "attach" 				=> _("Option to attach Voicemails to email."),
				     "authpassword"	 		=> _("IMAP server master password."),
				     "authuser" 			=> _("IMAP server master username."),
				     "backupdeleted"			=> _("No. of deleted messages saved per mailbox (can be a number or yes/no, yes meaning MAXMSG, no meaning 0)."),
				     "callback"				=> _("Context to call back from; if not listed, calling the sender back will not be permitted."),
				     "charset"				=> _("The character set for Voicemail messages"),
				     "cidinternalcontexts"		=> _("Comma separated list of internal contexts to use caller ID."),
				     "dialout"				=> _("Context to dial out from [option 4 from the advanced menu] if not listed, dialing out will not be permitted."),
				     "emailbody"			=> _("Email body."),
				     "emaildateformat"			=> _("Load date format config for Voicemail mail."),
				     "emailsubject"			=> _("Email subject"),
				     "maxsilence"			=> _("How many miliseconds of silence before we end the recording"),
				     "envelope"				=> _("Turn on/off envelope playback before message playback. [ON by default] This does NOT affect option 3,3 from the advanced options menu."),
				     "exitcontext"			=> _("Context to check for handling * or 0 calls to operator. \"Operator Context\""),
				     "expungeonhangup"			=> _("Expunge on exit."),
				     "externnotify"			=> _("External Voicemail notify application."),
				     "externpass"			=> _("External password changing command (overrides externpassnotify)."),
				     "externpasscheck"			=> _("External password verification script."),
				     "externpassnotify"			=> _("Command specified runs after a user changes his password."),
				     "forcegreetings"			=> _("Force new user to record greetings (the same as forcename, except for recording greetings).  The default is \"no\"."),
				     "forcename"			=> _("Force a new user to record their name.  A new user is determined by the password being the same as the mailbox number.  The default is \"yes\"."),
				     "format"				=> _("Formats for writing Voicemail.  Note that when using IMAP storage for Voicemail, only the first format specified will be used."),
				     "fromstring"			=> _("From: string for email"),
				     "imapclosetimeout"			=> _("For IMAP storage"),
				     "imapflags"			=> _("IMAP server flags."),
				     "imapfolder"			=> _("IMAP Voicemail folder."),
				     "imapgreetings"			=> _("If using IMAP storage, specify whether Voicemail greetings should be stored via IMAP. If no, then greetings are stored as if IMAP storage were not enabled"),
				     "greetingsfolder"			=> _("(yes/no) If imapgreetings=yes, then specify which folder to store your greetings in. If you do not specify a folder, then INBOX will be used."),
				     "imapopentimeout"			=> _("For IMAP storage - TCP open timeout in seconds"),
				     "imapparentfolder"			=> _("Set the parent folder (default is to have no parent folder set)."),
				     "imapport"				=> _("IMAP server port."),
				     "imapreadtimeout"			=> _("For IMAP storage - TCP read timeout in seconds"),
				     "imapserver"			=> _("IMAP server address."),
				     "imapwritetimeout"			=> _("For IMAP storage - TCP write timeout in seconds"),
				     "listen-control-forward-key"	=> _("Customize the key that fast-forwards message playback"),
				     "listen-control-pause-key"		=> _("Customize the key that pauses/unpauses message playback"),
				     "listen-control-restart-key"	=> _("Customize the key that restarts message playback"),
				     "listen-control-reverse-key"	=> _("Customize the key that rewinds message playback"),
				     "listen-control-stop-key"		=> _("Customize the key that stops message playback"),
				     "mailcmd"				=> _("Mail command."),
				     "maxgreet"				=> _("Max message greeting length."),
				     "maxlogins"			=> _("Max failed login attempts."),
				     "maxmessage" 			=> _("Max message time length."),
				     "maxsecs"				=> _("Max message time length."),
				     "maxmsg"				=> _("Maximum number of messages per folder.  If not specified, a default value (100) is used.  Maximum value for this option is 9999."),
						 "minpassword" => _("Enforce minimum password length"),
				     "minsecs"				=> _("Min message time length - maxsilence should be less than minsecs or you may get empty messages."),
				     "moveheard"			=> _("Move heard messages to the 'Old' folder automatically.  Defaults to no."),
				     "nextaftercmd"			=> _("Skip to the next message after save/delete."),
				     "odbcstorage"			=> _("The value of odbcstorage is the database connection configured in res_odbc.conf."),
				     "odbctable"			=> _("The default table for ODBC Voicemail storage is voicemessages."),
				     "operator"				=> _("Operator break. Allow sender to hit 0 before/after/during leaving a Voicemail to reach an operator [ON by default]"),
				     "pagerbody"			=> _("Body of message sent to pager."),
				     "pagerfromstring"			=> _("From: string sent to pager."),
				     "pagersubject"			=> _("Subject sent to pager."),
				     "pbxskip"				=> _("Skip the \"[PBX]:\" string from the message title"),
				     "pollfreq"				=> _("If the \"pollmailboxes\" option is enabled, this option sets the polling frequency.  The default is once every 30 seconds."),
				     "pollmailboxes"			=> _("If mailboxes are changed anywhere outside of app_voicemail, then this option must be enabled for MWI to work.  This enables polling mailboxes for changes.  Normally, it will expect that changes are only made when someone called in to one of the Voicemail applications. Examples of situations that would require this option are web interfaces to Voicemail or an email client in the case of using IMAP storage."),
				     "review"				=> _("Allow sender to review/rerecord their message before saving it [OFF by default]"),
				     "saycid"				=> _("Read back caller's telephone number prior to playing the incoming message, and just after announcing the date and time the message was left. If not described, or set to no, it will be in the envelope."),
				     "sayduration"			=> _("Turn on/off saying duration information before the message playback. [ON by default]"),
				     "saydurationm"			=> _("Specify in minutes the minimum duration to say. Default is 2 minutes."),
				     "searchcontexts"			=> _("Yes to search all contexts, no to search current context (if one is not specified)."),
				     "sendvoicemail"			=> _("Send Voicemail message. If not listed, sending messages from inside Voicemail will not be permitted."),
				     "serveremail"			=> _("Who the e-mail notification should appear to come from"),
				     "silencethreshold"			=> _("Silence threshold (what we consider silence: the lower, the more sensitive)"),
				     "skipms"				=> _("How many milliseconds to skip forward/back when rew/ff in message playback"),
				     "smdienable"			=> _("Enable Simple Message Desk Interface (SMDI) integration"),
				     "smdiport"				=> _("Valid port as specified in smdi.conf for using smdi for external notification."),
				     "tempgreetwarn"			=> _("Temporary greeting reminder."),
				     "usedirectory"			=> _("Permit finding entries for forward/compose from the directory"),
				     "userscontext"			=> _("User context is where entries from users.conf are registered.  The default value is 'default'"),
				     "vm-mismatch"			=> _("Customize which sound file is used instead of the default prompt that says: \"The passwords you entered and re-entered did not match.  Please try again.\""),
				     "vm-newpassword"			=> _("Customize which sound file is used instead of the default prompt that says: \"Please enter your new password followed by the pound key.\""),
				     "vm-passchanged"			=> _("Customize which sound file is used instead of the default prompt that says: \"Your password has been changed.\""),
				     "vm-password"			=> _("Customize which sound file is used instead of the default prompt that says: \"password\""),
				     "vm-reenterpassword"		=> _("Customize which sound file is used instead of the default prompt that says: \"Please re-enter your password followed by the pound key\""),
				     "volgain"				=> _("Emails bearing the Voicemail may arrive in a volume too quiet to be heard.  This parameter allows you to specify how much gain to add to the message when sending a Voicemail. NOTE: sox must be installed for this option to work.")
				     ),
		  "account" => array("pwd" 				=> _("This is the password used to access the Voicemail system.<br /><br />This password can only contain numbers.<br /><br />A user can change the password you enter here after logging into the Voicemail system (*98) with a phone."),
				     "attach" 				=> _("Option to attach Voicemails to email."),
				     "attachfmt"			=> _("Which format of audio file to attach to the email."),
				     "backupdeleted" 			=> _("No. of deleted messages saved per mailbox (can be a number or yes/no, yes meaning MAXMSG, no meaning 0)."),
				     "callback" 			=> _("Context to call back from; if not listed, calling the sender back will not be permitted."),
				     "delete" 				=> _("After notification, the Voicemail is deleted from the server. [per-mailbox only] This is intended for use with users who wish to receive their Voicemail ONLY by email. Note:  deletevoicemail is provided as an equivalent option for Realtime configuration."),
				     "dialout" 				=> _("Context to dial out from [option 4 from the advanced menu] if not listed, dialing out will not be permitted."),
				     "email"				=> _("The email address that Voicemails are sent to. Multiple e-mail addresses have to be comma separated without spaces (e.g.: first@domain.tld,second@domain.tld,third@domain.tld) "),
				     "envelope" 			=> _("Turn on/off envelope playback before message playback. [ON by default] This does NOT affect option 3,3 from the advanced options menu."),
				     "exitcontext" 			=> _("Context to check for handling * or 0 calls to operator. \"Operator Context\""),
				     "forcegreetings" 			=> _("Force new user to record greetings (the same as forcename, except for recording greetings).  The default is \"no\"."),
				     "fullname"				=> _("Name of Voicemail account"),
				     "hidefromdir"			=> _("Hide this mailbox from the directory produced by app_directory. The default is \"no\"."),
				     "imappassword" 			=> _("IMAP password."),
				     "imapuser" 			=> _("IMAP user."),
				     "language" 			=> _("Asterisk language code"),
				     "maxmsg" 				=> _("Maximum number of messages per folder.  If not specified, a default value (100) is used.  Maximum value for this option is 9999."),
				     "maxmessage" 			=> _("Max message time length."),
				     "maxsecs" 				=> _("Max message time length."),
				     "name"				=> _("Name of account/user"),
				     "pager"				=> _("Pager/mobile email address that short Voicemail notifications are sent to. Separated by |"),
				     "review"				=> _("Allow sender to review/rerecord their message before saving it [OFF by default]"),
				     "saycid" 				=> _("Read back caller's telephone number prior to playing the incoming message, and just after announcing the date and time the message was left. If not described, or set to no, it will be in the envelope."),
				     "sayduration" 			=> _("Turn on/off saying duration information before the message playback. [ON by default]"),
				     "saydurationm" 			=> _("Specify in minutes the minimum duration to say. Default is 2 minutes."),
				     "sendvoicemail" 			=> _("Send Voicemail message. If not listed, sending messages from inside Voicemail will not be permitted."),
				     "serveremail"			=> _("Who the e-mail notification should appear to come from"),
				     "tempgreetwarn" 			=> _("Remind the user that their temporary greeting is set"),
				     "tz" 				=> _("Timezone from zonemessages context.  Irrelevant if envelope=no."),
				     "vmcontext"			=> _("This is the Voicemail Context which is normally set to default. Do not change unless you understand the implications."),
				     "volgain" 				=> _("Emails bearing the Voicemail may arrive in a volume too quiet to be heard.  This parameter allows you to specify how much gain to add to the message when sending a Voicemail. NOTE: sox must be installed for this option to work."),
				     "callmenum" 			=> _("Call me number. Can be used from within ARI.")
				     )
		 );

/* End settings options */

/* Data needed to display correct page. */
$type		= (isset($_REQUEST["type"]) && $_REQUEST["type"] != "")?$_REQUEST["type"]:"setup";
$display	= (isset($_REQUEST["display"]) && $_REQUEST["display"] != "")?$_REQUEST["display"]:"voicemail";
if (isset($_REQUEST["updated"])) {
	if ($_REQUEST["updated"] == "true") {
		$update_flag = true;
	} else {
		$update_flag = false;
	}
} else {
	$update_flag = null;
}
$action		= isset($_REQUEST["action"])?$_REQUEST["action"]:"";
if (isset($_REQUEST["ext"])) {
		$extension = $_REQUEST["ext"];
		if (isset($vmail_info["activated_info"][$extension])) {
			$context = $vmail_info["activated_info"][$extension];
		} else if (isset($vmail_info["unactivated_info"][$extension])) {
			$context = $vmail_info["unactivated_info"][$extension];
		} else {
			// Force Voicemail to "system" mode by clearing context and extension values
			$context	 = "";
			$extension = "";
		}
} else {
	// System mode
	$context	 = "";
	$extension = "";
}
switch ($action) {
	case 'Go':
		/* This is for viewing a particular context's usage. */
		$action = "usage";
		/* Clear extension */
		$extension = "";
	break;
	case 'Submit':
		/* "Submit" is for performing some kind of update to settings (for page type of general, account OR timezone settings) OR to the files on disk. */
		/* page_type can be settings, account, tz or usage. */
		$action = (isset($_REQUEST["page_type"]) && !empty($_REQUEST["page_type"]))?$_REQUEST["page_type"]:"";;
		$need_update = true;
	break;
	default:
		$need_update = false;
	break;
}

/* If no action specified, default to a view of the entire system's usage. */
if (empty($action)) {
	$context		 = "";
	$extension	 = "";
	$need_update = false;
	$action			= "usage";
}

/* Need to generate rnav div menu */
/* system-wide rnav menu (lists all accounts) */
$rnav_list = implode("\n", $rnav_entries);

$title		= voicemail_get_title($action, $context, $extension);
$sys_view_flag = empty($extension)?true:false;

show_view(dirname(__FILE__).'/views/header.php',array(
	'type' => $type,
	'display' => $display,
	'extension' => $extension,
	'action' => $action,
	'sys_view_flag' => $sys_view_flag,
	'title' =>$title,
		)
);
//TODO: Do we really need to say "UPDATE COMPLETED??"
if ($need_update && $action != 'usage') {
	$args = array();
	$request = freepbxGetSanitizedRequest(FILTER_UNSAFE_RAW);
	if (voicemail_update_settings($action, $context, $extension, $request)) {
		$url = "config.php?type=$type&display=$display&action=$action&ext=$extension&updated=true";
				needreload();
				redirect($url);
	} else {
		$url = "config.php?type=$type&display=$display&action=$action&ext=$extension&updated=false";
		redirect($url);
	}
}

switch ($action) {
	case "tz":
		/* get tz settings */
		$settings = voicemail_get_settings($uservm, $action, $extension);
		$settings = (is_array($settings) && !empty($settings)) ? $settings : array();
		show_view(dirname(__FILE__).'/views/tz.php',array('settings' => $settings, 'tooltips' => $tooltips));
		break;
	case "dialplan":
		// TODO: may wan to look at making this table driven but for now ...
		$settings = voicemail_get_settings($uservm, $action, $extension);
		// Direct Dial Mode
		$direct_dial_opts = array(
			'u' => _("Unavailable"),
			'b' => _("Busy"),
			's' => _("No Message")
		);
		// Voicemail Gain
		$voicemail_gain_opts = array(
			'' => _("None"),
			'3' => _("3 db"),
			'6' => _("6 db"),
			'9' => _("9 db"),
			'12' => _("12 db"),
			'15' => _("15 db")
		);
		// VMX_TIMEOUT
		$vmx_timeout_opts['0'] = _("0 Sec");
		for ($i=1;$i<16;$i++) {
			$vmx_timeout_opts[$i] = sprintf(_("%s Sec"),$i);
		}
		// VMX_REPEAT
		for ($i=1;$i<5;$i++) {
			$vmx_repeat_opts[$i] = sprintf(_("%s Attempts"),$i);
		}
		// VMX_LOOPS
		//
		$vmx_loops_opts[1] = sprintf(_("%s Retry"),1);
		for ($i=2;$i<5;$i++) {
			$vmx_loops_opts[$i] = sprintf(_("%s Retries"),$i);
		}
		show_view(dirname(__FILE__).'/views/dialplan.php',array('settings' => $settings, 'direct_dial_opts' => $direct_dial_opts, 'voicemail_gain_opts' => $voicemail_gain_opts, 'vmx_timeout_opts' => $vmx_timeout_opts, 'vmx_repeat_opts' => $vmx_repeat_opts, 'vmx_loops_opts' => $vmx_loops_opts));
		break;

	case "settings":
		if(!empty($extension)) {
			$level = "account";
			$id_prefix = "acct";
		} else {
			$level = "general";
			$id_prefix = "gen";
		}
		$level = !empty($extension) ? "account" : "general";
		$d = \FreePBX::Voicemail()->constructSettings($level);
		/* get settings */
		$settings = voicemail_get_settings($uservm, $action, $extension);
		/* Get Asterisk version. */
		$ast_info = engine_getinfo();
		$version = $ast_info["version"];

		show_view(dirname(__FILE__).'/views/ssettings.php',array('d' => $d, 'action' => $action, 'extension' => $extension, 'version' => $version, 'settings' => $settings, 'tooltips' => $tooltips, 'display_settings' => $acct_settings, 'display_tips' => $tooltips["account"], 'id_prefix' => $id_prefix));
	break;
	case "bsettings":
		$output = '';
		/* get settings */
		$settings = voicemail_get_settings($uservm, $action, $extension);
		/* Get Asterisk version. */
		$ast_info = engine_getinfo();
		$version = $ast_info["version"];

		$text_size = 40;
		if (!empty($extension)) {
			show_view(dirname(__FILE__).'/views/settings.php',array('action' => $action, 'extension' => $extension, 'version' => $version, 'settings' => $settings, 'tooltips' => $tooltips, 'display_settings' => $acct_settings, 'display_tips' => $tooltips["account"], 'id_prefix' => 'acct'));
			$id_prefix = "acct";
			$display_settings = $acct_settings;
		} else {
			show_view(dirname(__FILE__).'/views/settings.php',array('action' => $action, 'extension' => $extension, 'version' => $version, 'settings' => $settings, 'tooltips' => $tooltips, 'display_settings' => $gen_settings, 'display_tips' => $tooltips["general"], 'id_prefix' => 'gen'));
			$id_prefix = "gen";
			$display_settings = $gen_settings;
		}

		$display_name_row = "";
		if ($action == "bsettings") {
			/* Display account name */
			$display_name = isset($settings["name"])?$settings["name"]:_("No name defined; this is configured from the Extensions or Users page.");
			$display_name_row = "<tr><td><a href='#' class='info'>" . _("Name") . "<span>" . $tooltips["account"]["name"] . "</span></a></td><td>&nbsp;&nbsp;&nbsp;&nbsp;" . $display_name . "</td></tr>";
			# Override display settings, so only the basic account settings appear.
			unset($display_settings);
			$basic_settings["pwd"] 		= isset($settings["pwd"])?$settings["pwd"]:"";
			$basic_settings["email"] 	= isset($settings["email"])?$settings["email"]:"";
			$basic_settings["pager"] 	= isset($settings["pager"])?$settings["pager"]:"";
			$basic_settings["attach"] 	= isset($settings["attach"])?$settings["attach"]:"";
			$basic_settings["saycid"] 	= isset($settings["saycid"])?$settings["saycid"]:"";
			$basic_settings["envelope"] 	= isset($settings["envelope"])?$settings["envelope"]:"";
			$basic_settings["delete"] 	= isset($settings["delete"])?$settings["delete"]:"";
			$basic_settings["callmenum"] 	= isset($settings["callmenum"])?$settings["callmenum"]:"";
			unset($settings);
			$settings			= $basic_settings;
			$display_settings["pwd"] 	= $acct_settings["pwd"];
			$display_settings["email"] 	= $acct_settings["email"];
			$display_settings["pager"] 	= $acct_settings["pager"];
			$display_settings["attach"] 	= $acct_settings["attach"];
			$display_settings["saycid"] 	= $acct_settings["saycid"];
			$display_settings["envelope"] 	= $acct_settings["envelope"];
			$display_settings["delete"] 	= $acct_settings["delete"];
			$display_settings["callmenum"] 	= $acct_settings["callmenum"];
			$opt_headings = $display_settings;
			$opt_headings["pwd"]		= _("Voicemail Password");
			$opt_headings["email"]		= _("Email Address");
			$opt_headings["pager"]		= _("Pager Email Address");
			$opt_headings["attach"]		= _("Email Attachment");
			$opt_headings["saycid"]		= _("Play CID");
			$opt_headings["envelope"]	= _("Play Envelope");
			$opt_headings["delete"]		= _("Delete Voicemail");
			$opt_headings["callmenum"]	= _("Call-Me Number");
		}
		$output .= $display_name_row;

		foreach ($display_settings as $key => $descrip) {
			$tooltip = isset($tooltips['general'][$key])?$tooltips['general'][$key]:(isset($tooltips['account'][$key])?$tooltips['account'][$key]:"");
			$len = ($descrip["len"] > 0)?$descrip["len"]:$dlen;
			$id = $id_prefix . "__" . $key;
			if (isset($settings[$key]) || ($version >= $descrip["ver"])) {
				$val = isset($settings[$key]) ? $settings[$key] : (!empty($descrip["default"]) ? $descrip["default"] : '');
				unset($settings[$key]);
				$opt_name = ($action == "bsettings")?$opt_headings[$key]:$key;
				/* check box or not */
				if ($descrip["type"] == "flag") {
					switch ($val) {
						case "yes":
							$yes_selected = "checked=checked";
							$no_selected	= "";
							$undef_selected = "";
							break;
						case "no":
							$yes_selected = "";
							$no_selected = "checked=checked";
							$undef_selected = "";
							break;
						default:
							$yes_selected = "";
							$no_selected = "";
							$undef_selected = "checked=checked";
							break;
					}
					$output.= '
					<!--'.$opt_name.'-->
					<div class="element-container">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="'.$id.'">'. $opt_name .'</label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="'.$id.'"></i>
										</div>
										<div class="col-md-9 radioset">
											<input type="radio" class="form-control" id="'.$id.'_yes" name="'.$id.'" value="yes" '.$yes_selected.'>
											<label for="'.$id.'_yes">'. _("yes") .'</label>
											<input type="radio" class="form-control" id="'.$id.'_no" name="'.$id.'" value="no" '.$no_selected.'>
											<label for="'.$id.'_no">'. _("No") .'</label>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="'.$id.'-help" class="help-block fpbx-help-block">'.$tooltip.'</span>
							</div>
						</div>
					</div>
					<!--END '.$opt_name.'-->
					';
				} else {
					switch($descrip['type']) {
						case "charpwd":
							$text_type = "password";
						break;
						case "num":
							$text_type = "number";
						break;
						case "chararea":
							$text_type = "textarea";
						break;
						default:
							$text_type = "text";
						break;
					}
					$output.= '
					<!--'.$opt_name.'-->
					<div class="element-container">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="'.$id.'">'. $opt_name .'</label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="'.$id.'"></i>
										</div>
										<div class="col-md-9">';
					if($text_type == "textarea") {
						$val = str_replace(array('\r','\n','\t'),array("\r","\n","\t"),$val);
						$output.= '<textarea class="form-control" id="'.$id.'" name="'.$id.'" rows="10">'.htmlentities($val,ENT_COMPAT | ENT_HTML401, "UTF-8").'</textarea>';
					} else {
						$output.= '<input type="'.$text_type.'" maxlength="'.$len.'" class="form-control" id="'.$id.'" name="'.$id.'" value="'.htmlentities($val,ENT_COMPAT | ENT_HTML401, "UTF-8").'">';
					}
					$output.= '</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="'.$id.'-help" class="help-block fpbx-help-block">'.$tooltip.'</span>
							</div>
						</div>
					</div>
					<!--END '.$opt_name.'-->
					';
				}
			}
			unset($id);
		}
		/* Any additional setting? */
		unset($settings["enabled"]);	# ignore this value; we will not enable/disable from Voicemail
		unset($settings[0]);
		if (is_array($settings) && !empty($settings)) {
			foreach ($settings as $key => $val) {
				$id = $id_prefix . "__" . $key;
				# no tooltip available
					$output.= '
					<!--'.$key.'-->
					<div class="element-container">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="'.$id.'">'. $opt_name .'</label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="'.$id.'"></i>
										</div>
										<div class="col-md-9">
											<input type="'.$text_type.'" maxlength="'.$len.'" class="form-control" id="'.$id.'" name="'.$id.'" value="'.htmlentities($val,ENT_COMPAT | ENT_HTML401, "UTF-8").'">
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="'.$id.'-help" class="help-block fpbx-help-block">'.$tooltip.'</span>
							</div>
						</div>
					</div>
					<!--END '.$key.'-->
					';
			}
		}
		$update_notice = ($update_flag === false)?"&nbsp;&nbsp;<b><u>UPDATE FAILED</u></b>":"";
		$update_flag === true ? $update_notice = "&nbsp;&nbsp;<b><u>UPDATE COMPLETED</u></b>":"";
		$output .= "<input type='hidden' name='action' id='action' value='"._('Submit')."' />";
		if($update_notice != ""){
			$output .= '<div class="well">'. $update_notice . '</div>';
		}
		echo $output;
		break;
	case "usage":
		/* Usage information and options available for system-wide,
			 and individual account views.
		*/
		$scope = voicemail_get_scope($extension);
		if ($need_update) {
			$request = freepbxGetSanitizedRequest(FILTER_UNSAFE_RAW);
			voicemail_update_usage($vmail_info, $context, $extension, $request);
			if (!empty($extension)) {
				$url = "config.php?display=$display&ext=$extension&action=$action&updated=true";
			} else {
				$url = "config.php?display=$display&action=$action&updated=true";
			}
			redirect($url);
		}

		voicemail_get_usage($vmail_info,
			$scope,
			$acts_total,
			$acts_act,
			$acts_unact,
			$disabled_count,
			$msg_total,
			$msg_in,
			$msg_other,
			$name,
			$unavail,
			$busy,
			$temp,
			$abandoned,
			$storage,
			$context,
			$extension
		);

		$vals = array(
			'scope' => $scope,
			'acts_total' => $acts_total,
			'acts_act' => $acts_act,
			'acts_unact' => $acts_unact,
			'disabled_count' => $disabled_count,
			'msg_total' => $msg_total,
			'msg_in' => $msg_in,
			'msg_other' => $msg_other,
			'name' => $name,
			'unavail' => $unavail,
			'busy' => $busy,
			'temp' => $temp,
			'abandoned' => $abandoned,
			'storage' => $storage,
			'context' => $context,
			'extension' => $extension
		);

		if ($scope == "system") {
			show_view(dirname(__FILE__).'/views/usage_system.php',$vals);
		} else {
			$version = !empty($version) ? $version : '';
			$settings = !empty($settings) ? $settings : '';
			show_view(dirname(__FILE__).'/views/settings.php',array('action' => $action, 'extension' => $extension, 'version' => $version, 'settings' => $settings, 'tooltips' => $tooltips, 'display_settings' => $acct_settings, 'display_tips' => $tooltips["account"], 'id_prefix' => 'acct'));
			/* Get timestamps, if applicable */
			$vals['ts'] = voicemail_get_greeting_timestamps($name, $unavail, $busy, $temp, $context, $extension);
			$vals['name_ts'] = ($vals['ts']["name"] > 0) ? $vals['ts']["name"] : '0';
			$vals['unavail_ts'] = ($vals['ts']["unavail"] > 0) ? $vals['ts']["unavail"] : '0';
			$vals['busy_ts'] = ($vals['ts']["busy"] > 0) ? $vals['ts']["busy"] : '0';
			$vals['temp_ts'] = ($vals['ts']["temp"] > 0) ? $vals['ts']["temp"] : '0';
			show_view(dirname(__FILE__).'/views/usage.php',$vals);
		}
		break;
	default:
		break;
}
show_view(__DIR__.'/views/footer.php', array('rnav_list' => $rnav_list));
