<?php
// vim: set ai ts=4 sw=4 ft=php:
namespace FreePBX\Builtin;

class UpdateManager {

	public function __construct() {
		$this->freepbx = \FreePBX::create();

		$this->brand = $this->freepbx->Config->get('DASHBOARD_FREEPBX_BRAND');
		$settings = $this->getCurrentUpdateSettings(false); // Don't html encode the output
		$this->machine_id = $settings['system_ident'];
	}

	private function isCloudDeployment() {
		if($this->freepbx->Modules->checkStatus('sysadmin')) {
			if (method_exists($this->freepbx->Sysadmin(),"isCloudDeployment")) {
				if($this->freepbx->Sysadmin->isCloudDeployment()){
					return true;
				}
			}
		}
		return false;
	}
	/**
	 * Handle an Ajax request from the Updates Page
	 *
	 * @param array $req $_REQUEST
	 * @return array
	 */
	public function ajax($req) {
		if (empty($req['action'])) {
			throw new \Exception("No action");
		}
		switch ($req['action']) {
		case "getmoduleupdates":
			return [ "status" => true, "result" => $this->getAvailModules() ];
		case "getsystemupdates":
			$s = new SystemUpdates();
			if (!$s->canDoSystemUpdates()) {
				return [ "systemupdates" => false, "updatesavail" => false, "updatespending" => [] ];
			} else {
				$pending = $s->getSystemUpdatesPending();
				return [ "systemupdates" => true, "updatesavail" => empty($pending), "updatespending" => $pending ];
			}
		case "updatescheduler":
			$this->updateUpdateSettings($req);
			return $this->getCurrentUpdateSettings();
		default:
			throw new \Exception(json_encode($req));
		}
	}

	/**
	 * Get current settings
	 *
	 * If the setting is not defined, return the default.
	 *
	 * @param bool $encode If false, return strings raw. Otherwise html escape special chars.
	 * @return array
	 */
	public function getCurrentUpdateSettings($encode = true) {
		$retarr = [
			"notification_emails" => "",
			"system_ident" => "",
			"auto_system_updates" => "emailonly", // This is ignored if not on a supported OS
			"auto_module_updates" => "enabled",
			"auto_module_security_updates" => "enabled",
			"unsigned_module_emails" => "enabled",
			"update_every" => "saturday",
			"update_period" => "8to12",
		];

		foreach ($retarr as $k => $null) {
			$val = $this->freepbx->getConfig($k, "updates");
			if($k === 'system_ident') {
				$val_old = \FreePBX::Config()->get('FREEPBX_SYSTEM_IDENT');
				if($val_old !== $val) {
					$this->freepbx->setConfig($k, $val_old, "updates");
				}
				$val = $val_old;
			}
			if ($val) {
				if ($encode) {
					$retarr[$k] = htmlspecialchars($val, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8', false);
				} else {
					$retarr[$k] = $val;
				}
			}
		}

		if($this->freepbx->Config->conf_setting_exists('AUTOSECURITYUPDATES')) {
			$setting = $this->freepbx->Config->get('AUTOSECURITYUPDATES');
			$retarr['auto_module_security_updates'] = $setting ? 'enabled' : 'emailonly';
			$this->freepbx->Config->remove_conf_setting('AUTOSECURITYUPDATES');
		}

		if($this->freepbx->Config->conf_setting_exists('SEND_UNSIGNED_EMAILS_NOTIFICATIONS')) {
			$setting = $this->freepbx->Config->get('SEND_UNSIGNED_EMAILS_NOTIFICATIONS');
			$retarr['unsigned_module_emails'] = $setting ? 'enabled' : 'disabled';
			$this->freepbx->Config->remove_conf_setting('SEND_UNSIGNED_EMAILS_NOTIFICATIONS');
		}

		if($this->freepbx->Config->conf_setting_exists('CRONMAN_UPDATES_CHECK')) {
			$setting = $this->freepbx->Config->get('CRONMAN_UPDATES_CHECK');
			$retarr['auto_module_updates'] = $setting ? 'enabled' : 'disabled';
			$this->freepbx->Config->remove_conf_setting('CRONMAN_UPDATES_CHECK');
		}

		// If ident is empty, take the one from settings
		if (!$retarr['system_ident']) {
			$retarr['system_ident'] = htmlspecialchars($this->freepbx->Config->get('FREEPBX_SYSTEM_IDENT'), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8', false);
			$this->freepbx->setConfig("system_ident", $retarr['system_ident'], "updates");
		}

		// If we don't have an email address, it may be in admin from
		// previous updates. Grab it (and delete it) from there.
		if (!$retarr['notification_emails']) {
			$oldemail = (string) $this->getOldEmailAddress(); // Make sure it's an empty string, not bool false.
			if ($oldemail) {
				$retarr['notification_emails'] = $oldemail;
				$this->freepbx->setConfig("notification_emails", $oldemail, "updates");
			}
		}

		return $retarr;
	}

	public function setNotificationEmail($email) {
		$this->freepbx->setConfig("notification_emails", $email, "updates");
	}

	/**
	 * Update our updater settings.
	 *
	 * Yes. That's confusing. Sorry, non-english speakers.
	 *
	 * @return array all settings
	 */
	public function updateUpdateSettings($req) {

		$this->freepbx->Notifications->delete('freepbx', 'UPDATE_CHANGES');

		$current = $this->getCurrentUpdateSettings(false);

		$s = new SystemUpdates();
		if (!$s->canDoSystemUpdates()) {
			$req['auto_system_updates'] = 'disabled';
		}

		// Check what we're currently allowed to change, and if it's different
		// from what was just submitted, change it!
		foreach ($current as $k => $c) {
			if (isset($req[$k]) && $req[$k] !== $c) {
				if($k === 'system_ident') {
					\FreePBX::Config()->update('FREEPBX_SYSTEM_IDENT',$req[$k]);
				}
				$this->freepbx->setConfig($k, $req[$k], "updates");
			}
		}

		$this->updateCrontab();

		return $this->getCurrentUpdateSettings();
	}

	/**
	 * Return the email address from the old admin table
	 */
	public function getOldEmailAddress($delete = true) {
		$sql = "SELECT `value` FROM `admin` WHERE `variable`='email'";

		$row = $this->freepbx->Database->query($sql)->fetchColumn();
		if ($delete) {
			$this->freepbx->Database->query("DELETE FROM `admin` WHERE `variable`='email'");
		}
		return $row;
	}


	/**
	 * Update our crontab to be whatever it should be
	 *
	 * @return string
	 */
	public function updateCrontab() {

		$hourmaps = [
			"0to4" => [ 0, 3 ],
			"4to8" => [ 4, 7 ],
			"8to12" => [ 8, 11 ],
			"12to16" => [ 12, 15 ],
			"16to20" => [ 16, 19 ],
			"20to0" => [ 20, 23 ]
		];

		$daymaps = [
		    "day" => "*",
		    "sunday" => "0",
		    "monday" => "1",
		    "tuesday" => "2",
		    "wednesday" => "3",
		    "thursday" => "4",
		    "friday" => "5",
			"saturday" => "6"
		];

		$settings = $this->getCurrentUpdateSettings(false);

		// Get the day
		if (!isset($daymaps[$settings['update_every']])) {
			throw new \Exception("Unknown day '${settings['update_every']}'");
		}
		$day = $daymaps[$settings['update_every']];

		// Pick a random hour
		if (!isset($hourmaps[$settings['update_period']])) {
			throw new \Exception("Unknown period '$period'");
		}
		$map = $hourmaps[$settings['update_period']];
		$hour = mt_rand($map[0], $map[1]);

		// Pick a random minute
		$min = mt_rand(0, 59);

		// Start by deleting any fwconsole commands in cron
		$cron = $this->freepbx->Cron;

		$fwconsole = $this->freepbx->Config->get('AMPSBIN')."/fwconsole" ;

		$crons = $cron->getAll();
		foreach($crons as $line) {
			// LEGACY: Make sure our old scheduler is gone - WIP
			if(preg_match('/freepbx-cron-scheduler\.php/',$line)) {
				$cron->remove($line);
			}

			if(preg_match('/fwconsole ma/',$line)) {
				$cron->remove($line);
			}

			if(preg_match('/fwconsole sys/',$line)) {
				$cron->remove($line);
			}
		}

		$cmd = "[ -e $fwconsole ] && $fwconsole ma listonline --sendemail -q > /dev/null 2>&1";
		// Add the new job to check for updates.
		if(!empty($cmd)) {
			$cron->add([ "command" => $cmd, "minute" => $min, "hour" => $hour, "dow" => $day ]);
		}

		// If we are auto-installing, then run the update job an hour later.
		if ($hour == 23) {
			$hour = 0;
			$day++;
		} else {
			$hour++;
		}
		// Are our updates enabled?
		if ($settings['auto_system_updates'] === "emailonly") {
			$cmd = "[ -e $fwconsole ] && $fwconsole sys listonline --sendemail -q > /dev/null 2>&1";
			$cron->add([ "command" => $cmd, "minute" => $min, "hour" => $hour, "dow" => $day ]);
		} elseif($settings['auto_system_updates'] === "enabled") {
			$cmd = "[ -e $fwconsole ] && $fwconsole sys upgradeall --sendemail -q > /dev/null 2>&1";
			$cron->add([ "command" => $cmd, "minute" => $min, "hour" => $hour, "dow" => $day ]);
		}

		// If we are auto-installing, then run the update job an hour later.
		if ($hour == 23) {
			$hour = 0;
			$day++;
		} else {
			$hour++;
		}

		if ($settings['auto_module_updates'] === "enabled") {
			if($this->freepbx->Config->get('AUTOMODULEUPDATESANDRELOAD')){
				$cmd = "[ -e $fwconsole ] && $fwconsole ma upgradeall --sendemail && $fwconsole reload -q > /dev/null 2>&1";
			}else {
				$cmd = "[ -e $fwconsole ] && $fwconsole ma upgradeall --sendemail -q > /dev/null 2>&1";
			}
			$cron->add([ "command" => $cmd, "minute" => $min, "hour" => $hour, "dow" => $day ]);
		}

	}

	/**
	 * Send an email
	 *
	 * This checks to make sure it's not a duplicate of the last email that was sent,
	 * but WILL send an email if it's been more than a week since the last email was
	 * sent.  This ensures that people get a regular ping from their server telling
	 * them that everything's OK.
	 *
	 * @param string $tag A way to differentiate between different emails
	 * @param string $to
	 * @param string $from
	 * @param string $subject
	 * @param string $body
	 * @param int $priority
	 */
	public function sendEmail($tag, $subject, $message, $priority = 4, $force = false) {
		if($this->isCloudDeployment()){
			return;
		}
		$settings = $this->getCurrentUpdateSettings(false); // Don't html encode the output
		$to = $settings['notification_emails'];

		// This is our default
		$from = get_current_user() . '@' . gethostname();

		// Sysadmin lets you override the 'from' address.
		if(function_exists('sysadmin_get_storage_email')){
			$emails = sysadmin_get_storage_email();
			//Check that what we got back above is a email address
			if(!empty($emails['fromemail']) && filter_var($emails['fromemail'],FILTER_VALIDATE_EMAIL)){
				$from = $emails['fromemail'];
			}
		}

		// Generate a hash of this email body and who it's being sent to
		$currenthash = hash("sha256", $message.$to);

		$previoushash = $this->freepbx->getConfig($tag, "emailhash");
		$lastsent = (int) $this->freepbx->getConfig($tag, "emailtimestamp");

		$currTime = time();
		/* Random hour, minute in 4-hour interval; this week's timestamp could be as little as 7D-3H59M after last week's timestamp
		   Allow 1 minute additional margin; 6 days 20 hours = 590400 seconds
		 */
		if (!$force && $currenthash === $previoushash && ($lastsent > $currTime - 590400)) {
			// Not sending, it's a dupe and it's too soon. Pretend we did.
			return true;
		}

		$em = new Email();
		$em->setTo(array($to));
		$em->setFrom($from);
		$em->setSubject($subject);
		$em->setBodyPlainText($message);
		$em->setPriority($priority);
		$result = $em->send();
		if ($result) {
			// Successfully sent!
			$this->freepbx->setConfig($tag, $currenthash, "emailhash");
			$this->freepbx->setConfig($tag, $currTime, "emailtimestamp");
		}
		return $result;
	}

	/** Clean up HTML in emails */
	public function cleanHtml($string) {
		return "    ".str_replace("<br>", "\n    ", $string);
	}

	/** Return summary of module updates */
	public function getAvailModules() {
		$cachedonline = $this->freepbx->Modules->getCachedOnlineData();

		// TODO: use $cachedonline['timestamp'] to figure this out
		$html = "<h3>"._("Last module update check: ")."1234 seconds ago</h3>";

		// Get our list of upgradeable modules (if any)
		$upgradeable = $this->freepbx->Modules->getUpgradeableModules($cachedonline['modules']);

		if (!$upgradeable) {
			$html .= _("No pending module updates.");
			return $html;
		}
		// We have modules. Create our table
		$html .= "<table class='table'><tr><th>"._("Module Name")."</th><th>"._("Current Version")."</th><th>"._("New Version")."</th></tr>\n";
		foreach ($upgradeable as $row) {
			$html .= "<tr><td>".$row['descr_name']."</td><td>".$row['local_version']."</td><td>".$row['online_version']."</td></tr>\n";
		}
		$html .= "</table>";

		return $html;
	}

	public function securityEmail() {
		if($this->isCloudDeployment()){
			return;
		}
		$security = $this->freepbx->Notifications->list_security();
		if (!$security) {
			return;
		}

		$email_subject = sprintf(_("%s Security Alert (%s)"), $this->brand, $this->machine_id);
		$email_body = $this->getUnsignedEmailBody();

		$email_body .= _("SECURITY NOTICE:")."\n\n";
		foreach ($security as $item) {
			$email_body .= $item['display_text'].":\n";
			$email_body .= $this->cleanHtml($item['extended_text'])."\n";
		}
		$email_body .= "\n\n";

		// Send an email if we need to.
		$sent = $this->sendEmail("security", $email_subject, $email_body, 4);

		if (!$sent) {
			$this->freepbx->Notifications->add_error('freepbx', 'SECEMAIL', _('Failed to send security notification email'), sprintf(_('An attempt to send an email to "%s" with security notifications failed'),$this->email_to),'./config.php?display=modules');
		}
	}

	public function updateEmail($willupdate = false) {
		if($this->isCloudDeployment()){
			return;
		}
		$updates = $this->freepbx->Notifications->list_update();
		if (!$updates) {
			return;
		}

		$email_subject = sprintf(_("%s Updates Notification (%s)"), $this->brand, $this->machine_id);
		$email_body = _("Module updates are available.")."\n\n";
		$email_body .= $this->getUnsignedEmailBody()."\n";
		if ($willupdate) {
			$email_body .= "\n"._("Automatic updates are enabled! These modules will be automatically updated in one hours time.")."\n\n";
		}
		foreach ($updates as $item) {
			$email_body .= $item['display_text']."\n";
			$email_body .= $item['extended_text']."\n\n";
		}

		// Send an email if we need to.
		$sent = $this->sendEmail("updates", $email_subject, $email_body, 4);

		if ($sent) {
			$this->freepbx->Notifications->delete('freepbx', 'UPDATEEMAIL');
		} else {
			$this->freepbx->Notifications->add_error('freepbx', 'UPDATEEMAIL', _('Failed to send update notification email'), sprintf(_('An attempt to send an email to "%s" with update notifications failed'),$this->email_to));
		}
	}

	public function unsignedEmail() {
		if($this->isCloudDeployment()){
			return;
		}
		if($this->getCurrentUpdateSettings(false)['unsigned_module_emails'] === 'disabled'){
			$this->freepbx->Notifications->delete("freepbx", "SIGEMAIL");
			return true;
		}

		$email_body = $this->getUnsignedEmailBody();
		if (!$email_body) {
			$this->freepbx->Notifications->delete("freepbx", "SIGEMAIL");
			return true;
		}

		$email_subject = sprintf(_("%s Unsigned Module Alert (%s)"), $this->brand, $this->machine_id);

		// Send an email if we need to.
		$sent = $this->sendEmail("unsigned", $email_subject, $email_body, 4);

		if (!$sent) {
			$this->freepbx->Notifications->add_error('freepbx', 'SIGEMAIL', _('Failed to send unsigned modules notification email'), sprintf(_('An attempt to send an email to "%s" with an alert about unsigned modules notifications failed'),$this->email_to));
		}
	}

	private function getUnsignedEmailBody() {
		if ($this->getCurrentUpdateSettings(false)['unsigned_module_emails'] === 'disabled') {
			$warning = _("Unsigned module notifications are turned off!")."\n\n";
			$warning .= _("You will not get alerts about new modules that are installed on your system without a valid signature. It is unusual to turn off this protection. You can turn it back on in 'Updates' by enabling the 'Send security emails for unsigned modules' option.")."\n";
			$warning .= _("If you want to sign your own modules to protect them from unauthorized tampering, please see the link below for more information:")."\n";
			$warning .= "    http://wiki.freepbx.org/display/FOP/Signing+your+own+modules\n";
			return $warning;
		}
		// Now see if there ARE any unsigned modules to complain about
		$unsigned = $this->freepbx->Notifications->list_signature_unsigned();
		if (!$unsigned) {
			return "";
		}
		$warning = _("UNSIGNED MODULES DETECTED:")."\n\n";
		$warning .= _("Warning: It is unusual to have Unsigned modules on your system!")."\n";
		$warning .= sprintf(_("There are several ways to protect modules against tampering in %s. Please see the wiki page on module signing for more information:"), $this->brand)."\n";
		$warning .= "    http://wiki.freepbx.org/display/FOP/Signing+your+own+modules\n\n";
		foreach ($unsigned as $item) {
			$warning .= $item['display_text'].":\n";
			$warning .= $this->cleanHtml($item['extended_text'])."\n";
		}
		return $warning;
	}
}

