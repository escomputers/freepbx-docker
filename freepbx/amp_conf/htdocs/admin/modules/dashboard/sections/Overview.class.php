<?php
// vim: set ai ts=4 sw=4 ft=php:
//
// License for all code of this FreePBX module can be found in the license file inside the module directory
// Copyright 2006-2014 Schmooze Com Inc.

namespace FreePBX\modules\Dashboard\Sections;

class Overview {
	public $rawname = 'Overview';

	public function getSections($order) {
		return array(
			array(
				"title" => _("System Overview"),
				"group" => _("Overview"),
				"width" => "550px",
				"order" => isset($order['overview']) ? $order['overview'] : '1',
				"section" => "overview"
			)
		);
	}

	public function getContent($section) {
		if (!class_exists('TimeUtils')) {
			include dirname(__DIR__).'/classes/TimeUtils.class.php';
		}
		$brand = \FreePBX::Config()->get("DASHBOARD_FREEPBX_BRAND");

		if (\FreePBX::Config()->get("FREEPBX_SYSTEM_IDENT")) {
			$rem_help = \FreePBX::Config()->get("FREEPBX_SYSTEM_IDENT_REM_DASHBOARD_HELP");
			if (!empty($rem_help) && ($rem_help == 'yes')) {
				$idline = sprintf(_("<strong>'%s'</strong>"), \FreePBX::Config()->get("FREEPBX_SYSTEM_IDENT"));
			} else {
				$idline = sprintf(_("<strong>'%s'</strong><br><i>(You can change this name in Advanced Settings)</i>"), \FreePBX::Config()->get("FREEPBX_SYSTEM_IDENT"));
			}
		} else {
			$idline = "";
		}

		try {
			$getsi = \FreePBX::create()->Dashboard->getSysInfo();
		} catch (\Exception $e) {

		}

		$getsi['timestamp'] = isset($getsi['timestamp']) ? $getsi['timestamp'] : time();
		$since = time() - $getsi['timestamp'];
		$notifications = $this->getNotifications((isset($_COOKIE['dashboardShowAll']) && $_COOKIE['dashboardShowAll'] == "true"));
		$nots = $notifications['nots'];
		$alerts = $this->getAlerts($nots);

		return load_view(dirname(__DIR__).'/views/sections/overview.php',array("showAllMessage" => $notifications['showAllMessage'], "nots" => $nots, "alerts" => $alerts, "brand" => $brand, "idline" => $idline, "version" => get_framework_version(), "since" => $since, "services" => $this->getSummary()));
	}

	private function getNotifications($showall = false) {
		if (!class_exists('TimeUtils')) {
			include dirname(__DIR__).'/classes/TimeUtils.class.php';
		}
		$final['nots'] = array();
		$items = \FreePBX::create()->Notifications->list_all($showall);
		$allItems = \FreePBX::create()->Notifications->list_all(true);

		$final['showAllMessage'] = (count($items) != count($allItems));
		// This is where we map the Notifications priorities to Bootstrap priorities.
		// define("NOTIFICATION_TYPE_CRITICAL", 100) -> 'danger' (red)
		// define("NOTIFICATION_TYPE_SECURITY", 200) -> 'danger' (red)
		// define("NOTIFICATION_TYPE_UPDATE",   300) -> 'warning' (orange)
		// define("NOTIFICATION_TYPE_ERROR",    400) -> 'danger' (red)
		// define("NOTIFICATION_TYPE_WARNING" , 500) -> 'warning' -> (orange)
		// define("NOTIFICATION_TYPE_NOTICE",   600) -> 'success' -> (green)

		$alerts = array(100 => "danger", 200 => "danger", 250 => 'warning', 300 => "warning", 400 => "danger", 500 => "warning", 600 => "success");
		foreach ($items as $notification) {
			$final['nots'][] = array(
				"id" => $notification['id'],
				"rawlevel" => $notification['level'],
				"level" => !isset($alerts[$notification['level']]) ? 'danger' : $alerts[$notification['level']],
				"candelete" => $notification['candelete'],
				"title" => $notification['display_text'],
				"time" => \TimeUtils::getReadable(time() - $notification['timestamp']),
				"text" => nl2br($notification['extended_text']),
				"module" => $notification['module'],
				"link" => $notification['link'],
				"reset" => $notification['reset']
			);
		}
		return $final;
	}

	public function getAlerts($nots = false) {
		// Check notifications and decide what we want to do with them.
		// Start with everything happy
		$alerttitle = _("System Alerts");
		$state = "success";
		$text = "<div class='text-center'>"._("No critical issues found")."</div>";
		$foundalerts = array();
		// Go through our notifications now..
		foreach ($nots as $n) {
			// Firstly, check for a security issue. If that happens, we don't care about
			// anything else.
			if ($n['rawlevel'] == 200) {
				// Security vulnerability. This is bad.
				$state = "danger";
				$alerttitle = "<center><h4><i class='fa fa-exclamation-triangle'></i> "._("Security Issue")." <i class='fa fa-exclamation-triangle'></i></h4></center>";
				$text = "<p>".$n['title']."</p><p>" . _("This is a critical issue and should be resolved urgently") . "</p>";
				return array("alerttitle" => $alerttitle, "state" => $state, "text" => $text);
			}

			// Now lets find some alerts!
			if (!isset($foundalerts[$n['level']])) {
				$foundalerts[$n['level']] = 1;
			} else {
				$foundalerts[$n['level']]++;
			}
		}

		// Here is where we decide what the 10-word-box shall say.
		// If there's a Critical Issue, report that and a summary.
		if (isset($foundalerts['danger'])) {
			// There's a critical issue. That's what we're doing.
			$state = "danger";
			$text = _("Please check for errors in the notification section");
			$alerttitle = _("Critical Errors found");
		} elseif (isset($foundalerts['warning'])) {
			$state = "warning";
			$text = _("Please check for errors in the notification section");
			$alerttitle = _("Warnings Found");
		}
		return array("alerttitle" => $alerttitle, "state" => $state, "text" => $text);
	}

	public function getSummary() {
		$svcs = array(
			"asterisk" => _("Asterisk"),
			"mysql" => _("MySQL"),
			"apache" => _("Web Server"),
			"mailq" => _("Mail Queue"),
		);

		$sysinfo = \FreePBX::create()->Dashboard->getSysInfo();

		$final = array();
		$i = 0;
		foreach (array_keys($svcs) as $svc) {
			if (!method_exists($this, "check$svc")) {
				$final[$i]['type'] = 'unknown';
				$final[$i]['tooltip'] = "Function check$svc doesn't exist!";
			} else {
				$func = "check$svc";
				$final[$i] = $this->$func($sysinfo);
			}
			$final[$i]['title'] = $svcs[$svc];
			$i++;
		}

		$t = \FreePBX::Hooks()->processHooks($sysinfo);
		$f = $final;
		foreach($t as $d) {
			foreach($d as $d1) {
				$order = isset($d1['order']) ? $d1['order'] : count($f);
				$module = \module_functions::create();
				$fw_module = $module->getinfo('firewall', MODULE_STATUS_ENABLED);
				if(!empty($fw_module["firewall"])){
					$fw_status = \FreePBX::Firewall()->isEnabled();
				}
				if($d1['title'] == "System Firewall" && \FreePBX::Config()->get('VIEW_FW_STATUS') == false && !$fw_status){
					unset($d1);
					continue;
				}
				if($order == 0) {
					array_unshift($f, $d1);
					continue;
				}
				$t1 = array_slice($f, 0, $order, true);
				$t2 = array_slice($f, $order, count($f) - 1, true);
				$f = array_merge($t1, array($d1), $t2);
			}
		}
		return $f;
	}

	private function genAlertGlyphicon($res, $tt = null) {
		return \FreePBX::Dashboard()->genStatusIcon($res, $tt);
	}

	private function checkasterisk($sysinfo) {
		if (!isset($sysinfo['ast.uptime.system-seconds'])) {
			return $this->genAlertGlyphicon('critical', 'Unable to find Asterisk results');
		}
		$ast = $sysinfo['ast.uptime.system-seconds'];

		// Check to see if Asterisk is up and running.
		if ($ast == -1) {
			return $this->genAlertGlyphicon('error', 'Asterisk not running');
		}

		// Can we connect to asterisk?
		if ($ast == -2) {
			return $this->genAlertGlyphicon('critical', 'Asterisk Manager Interface (astman) failure');
		}

		$uptime = $sysinfo['ast.uptime.system'];
		// Up for less than 10 minutes? Is it crashing?
		if ($ast < 600) {
			return $this->genAlertGlyphicon('warning', "Asterisk running for less than 10 minutes ($uptime)");
		}

		return $this->genAlertGlyphicon('ok', "Asterisk uptime $uptime");
	}

	private function checkmysql() {
		return $this->genAlertGlyphicon('ok', "No Database checks written yet.");
	}

	private function checkmailq() {
		$mailq = fpbx_which("mailq");
		if ($mailq) {
			$lastline = exec("$mailq 2>&1", $out, $ret);
		}
		// Postfix returns 'Mail queue is empty'; exim returns nothing; sendmail returns total
		if (empty($out) || // exim
			strpos($out[0], "queue is empty") !== false || // postfix status on first/only output line
			strpos(end($out), "Total requests: 0") !== false // sendmail status on last line
		) {
			return $this->genAlertGlyphicon('ok', "No outbound mail in queue");
		}

		if (preg_match('/(?:in (\d+) Request)|(?:Total requests: (\d+))/', $lastline, $regex)) { // exim/postfix|sendmail
			// We have mail.
			$messages = (int) $regex[1] ?: (int) $regex[2]; // take whichever one matched
			if ($messages > 5) {
				$err = "critical";
			} else {
				$err = "warning";
			}

			if ($messages == 1) {
				$msg = _("1 message is queued on this machine, and has not been delivered");
			} else {
				$msg = sprintf(_("%s messages are queued on this machine, and have not been delivered"), $messages);
			}
			return $this->genAlertGlyphicon($err, $msg);
		}
		// This signifies a bug and must not be translated.
		return $this->genAlertGlyphicon('critical', "Unknown output from mailq: ".json_encode([$out, $ret]));
	}


	private function checkapache() {
		// This is here to allow us to fire up a small replacement httpd server if
		// something traumatic happens to apache. For the moment, however, we just
		// say yes.
		return $this->genAlertGlyphicon('ok', "Apache running");
	}

	private function delNotification() {
		// Triggered from above.
		$id = $_REQUEST['id'];
		$mod = $_REQUEST['mod'];
		return FreePBX::create()->Notifications->safe_delete($mod, $id);

	}
}
