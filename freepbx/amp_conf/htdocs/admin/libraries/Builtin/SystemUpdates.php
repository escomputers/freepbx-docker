<?php
// vim: set ai ts=4 sw=4 ft=php:
namespace FreePBX\Builtin;
use Symfony\Component\Lock\Factory;
use Symfony\Component\Lock\Store\SemaphoreStore;

class SystemUpdates {
	private $lock;
	// See framework/hooks/yum-* commands where these files are defined
	private $lockfile = "/dev/shm/yumwrapper/yum.lock";
	private $logfile = "/dev/shm/yumwrapper/output.log";
	//  i18n
	private $strarr = false; // This is overwritten in __construct
	private $cli = false;

	public function __construct($cli = false) {
		$this->cli = $cli;
		// Can't use functions in class definitions
		$this->strarr = [ "complete" => _("(Complete)"), "unknown" => _("(Unknown)"), "inprogress" => _("(In Progress)"), "yumerror" => _("(YUM Error)"), "error" => _("General Error") ];
	}

	public function __destruct() {

	}

	/**
	 * Ajax handler.
	 */
	public function ajax($req) {
		if (!isset($req['action'])) {
			throw new \Exception("No action");
		}
		switch ($req['action']) {
		case 'getsysupdatepage':
			return $this->getSystemUpdatesPage();
		case 'startcheckupdates':
			return $this->startCheckUpdates();
		case 'startyumupdate':
			return $this->startYumUpdate();
		case 'startsysupdate':
			return $this->startSystemUpdate();
		case 'getsysupdatestatus':
			return $this->getYumUpdateStatus();
		}
		throw new \Exception("Unknown action");
	}

	/**
	 * This checks to make sure we have the Sysadmin module, and that the
	 * sysadmin module is activated. If neither of these things are true,
	 * this machine can't do system updates.
	 *
	 * @return bool
	 */
	public function canDoSystemUpdates() {
		if(!\FreePBX::Modules()->checkStatus('sysadmin')) {
			return false;
		}
		\FreePBX::Modules()->loadFunctionsInc('sysadmin');
		if (!function_exists("sysadmin_get_license")) {
			return false;
		}
		$lic = sysadmin_get_license();
		//machineid is used since PHP 7+. for the earlier versions hostid is used
		return (isset($lic['machineid']));
	}

	/**
	 * Is a yum command in progress?
	 *
	 * We check to see if the yum.lock file exists. If we have
	 * issues with the screen command crashing, we may need to revisit
	 * this, and do more work than just checking if the file exists.
	 *
	 * @return bool
	 */
	public function isYumRunning() {
		return file_exists($this->lockfile);
	}


	/**
	 * Start yum-check-updates if it's not already running
	 */
	public function startCheckUpdates() {
		if ($this->isYumRunning()) {
			return true;
		}
		if(!$this->getLock()->acquire()) {
			return true;
		}
		try {
			if (!is_dir("/var/spool/asterisk/incron")) {
				// Something's broken with this machine.
				throw new \Exception("Incron not configured, unable to manage system updates");
			}
			// incron hook
			if (file_exists("/var/spool/asterisk/incron/framework.yum-check-updates")) {
				unlink("/var/spool/asterisk/incron/framework.yum-check-updates");
			}
			touch("/var/spool/asterisk/incron/framework.yum-check-updates");
			$nt = \notifications::create();
			if($this->checkIfTestingRepoEnabled()) {
				$nt->add_warning('framework', 'test_repos_enabled', _("Test repos are enabled"), _("'sangoma-devel' rpm is installed.\nyum repo 'sng7-testing' is enabled."), "", false, true);
			}

			$nt->delete("freepbx", "RPM_BROKEN");
			if($this->checkBrokenRpm()) {
				$nt->add_warning("freepbx", "RPM_BROKEN",_("You have some fault RPM versions running"),_("Found some faulty RPM versions please run 'yum update -y' manually to update to the latest version."));
			}
			// Wait up to 5 seconds for it to start
			$endafter = time()+5;
			while (time() < $endafter) {
				if ($this->isYumRunning()) {
					return true;
				}
				return true;
				usleep(100000); // 1/10th of a second.
			}
		} finally {
			$this->getLock()->release();
		}
		// If we made it here, the updates never started
		throw new \Exception("Updates did not start. Incron error?");
	}

	/**
	 * Start yum-update if it's not already running
	 */
	public function startYumUpdate() {
		if ($this->isYumRunning()) {
			return true;
		}
		if(!$this->getLock()->acquire()) {
			return true;
		}
		try {
			if (!is_dir("/var/spool/asterisk/incron")) {
				// Something's broken with this machine.
				throw new \Exception("Incron not configured, unable to manage system updates");
			}
			// incron hook
			if (file_exists("/var/spool/asterisk/incron/framework.yum-update-system")) {
				unlink("/var/spool/asterisk/incron/framework.yum-update-system");
			}
			touch("/var/spool/asterisk/incron/framework.yum-update-system");
			// Wait up to 5 seconds for it to start
			$endafter = time()+5;
			while (time() < $endafter) {
				if ($this->isYumRunning()) {
					return true;
				}
				usleep(100000); // 1/10th of a second.
			}
		} finally {
			$this->getLock()->release();
			$nt = \notifications::create();
			$nt->delete("freepbx", "RPM_BROKEN");
			if($this->checkBrokenRpm()) {
				$nt->add_warning("freepbx", "RPM_BROKEN",_("You have some fault RPM versions running"),_("Found some faulty RPM versions please run 'yum update -y' manually to update to the latest version."));
			}
		}

		// If we made it here, the updates never started
		throw new \Exception("Updates did not start. Incron error?");
	}

	/**
	 * Parse the output of a yum command
	 *
	 * Returns an array of useful stuff.
	 *
	 * @return array [ ... ]
	 */
	public function parseYumOutput($filename = false) {
		if (!$filename) {
			throw new \Exception("No file to parse");
		}

		$retarr = [
			"title" => false,
			"begintimestamp" => 0,
			"timestamp" => 0,
			"commands" => [ ],
		];
		// Grab the contents of the output file
		if (file_exists($filename)) {
			$logfile = file($filename, FILE_IGNORE_NEW_LINES);
		} else {
			$logfile = [];
		}

		// Now find the important parts
		foreach ($logfile as $lineno => $line) {

			// Title?
			if (strpos($line, "TITLE ") === 0) {
				if (!preg_match('/^TITLE (\d+) (.+)$/', $line, $out)) {
					continue;
				}
				$retarr['begintimestamp'] = $out[1];
				$retarr['title'] = $out[2];
				continue;
			}

			// Finish?
			if (strpos($line, "FINISH ") === 0) {
				$retarr['finishtimestamp'] = substr($line, 7);
				continue;
			}

			// Was this a timestamp?
			if (strpos($line, "[") === 0) {
				$retarr['timestamp'] = $this->parseTimestamp($line);
				continue;
			}

			// Start of a command?
			if (strpos($line, "START") === 0) {
				if (!preg_match("/^START (\d+) (.+)/", $line, $out)) {
					// How?
					continue;
				}
				$retarr['commands'][$out[2]] = [ 'started' => $out[1] ];
				continue;
			}

			// Completion of a command?
			if (strpos($line, "STOP") === 0) {
				// STOP 1482988046 yum-check-updates 100 CnNhbmdvbWEtcGJ4Lm5vYXJjaCAgICAgICAgICAgICAgICAgICAgIDE2MTItMS5zbmc3ICAgICAgICAgICAgICAgICAgICAgIHNuZy1wa2dzCg==
				if (!preg_match("/^STOP (\d+) ([^\s]+) (\d+)(.*)$/", $line, $out)) {
					// ... Even more how?
					continue;
				}
				if (!is_array($retarr['commands'][$out[2]])) {
					throw new \Exception("Found a STOP before a START, error in $filename");
				}
				$retarr['commands'][$out[2]]['completed'] = $out[1];
				$retarr['commands'][$out[2]]['exitcode'] = $out[3];
				if (!empty($out[4])) {
					$retarr['commands'][$out[2]]['output'] = base64_decode(trim($out[4]));
				} else {
					$retarr['commands'][$out[2]]['output'] = "";
				}
				continue;
			}

			// Unknown line in file
			throw new \Exception("Unknown line '$line' on line number $lineno in file $filename");
		}

		// Completed parsing file
		return $retarr;
	}

	/**
	 * Parse a timestamp line, and return an (int) utime
	 *
	 * Timestamp lines look like this:
	 * [ timestamp: 2016-12-29 05:52:00 ]
	 *
	 * @return int, will be zero if unable to parse.
	 */
	public function parseTimestamp($line) {
		if (!preg_match("/\[ timestamp: ([0-9:\-\s]+) \]/", $line, $out)) {
			return 0;
		}
		$dateint = strtotime($out[1]);
		if (!$dateint) {
			return 0;
		} // else
		return $dateint;
	}

	/**
	 * Return the current list of pending updates.
	 *
	 * @return array [ 	'lasttimestamp' => int, 'status' => {complete|inprogress|unknown}, 'updatesavail' => bool, 'rpms' => [ ... ] ]
	 */
	public function getPendingUpdates() {
		try {
			$updates = $this->parseYumOutput("/dev/shm/yumwrapper/yum-check-updates.log");
		} catch (\Exception $e) {
			@unlink("/dev/shm/yumwrapper/yum-check-updates.log");
			$retarr = [ 'lasttimestamp' => 0, 'status' => 'error', 'i18nstatus' => $this->strarr['unknown'],
				'updatesavail' => false, 'pbxupdateavail' => false, 'currentlog' => [ $e->getMessage() ], 'rpms' => [] ];
			return $retarr;
		}

		$retarr = [ 'lasttimestamp' => $updates['timestamp'],
			'status' => 'unknown',
			'i18nstatus' => $this->strarr['unknown'],
			'updatesavail' => false,
			'pbxupdateavail' => false,
			'currentlog' => [],
			'rpms' => []
		];

		// Do we have a title? If not, it was never run
		if ($updates['title'] !== 'yum-check-updates') {
			// Tell the browser to refresh after 1 sec.
			$retarr['retryafter'] = 1000;
			return $retarr;
		}

		// We should have some output that can be displayed to the user
		if (file_exists("/dev/shm/yumwrapper/yum-check-updates-current.log")) {
			$retarr['currentlog'] = file("/dev/shm/yumwrapper/yum-check-updates-current.log", FILE_IGNORE_NEW_LINES);
		}

		// Do we have a start for 'yum-clean-metadata'?
		if (empty($updates['commands']['yum-clean-metadata'])) {
			// We don't know what's going on, just return unknown
			return $retarr;
		}

		// We have a start, so it's in progress.
		$retarr['status'] = 'inprogress';

		// Do we have a stdout for yum-check-updates?
		if (empty($updates['commands']['yum-check-updates']['output'])) {
			// no. Still in progress
			$retarr['i18nstatus'] = $this->strarr[$retarr['status']];
			return $retarr;
		}

		// We do!
		$retarr['status'] = 'complete';

		// Did yum fail?
		if ($updates['commands']['yum-check-updates']['exitcode'] == 1) {
			$retarr['status'] = 'yumerror';
			$retarr['i18nstatus'] = $this->strarr['yumerror'];
			return $retarr;
		}

		// Are there any updates?
		$retarr['updatesavail'] = ($updates['commands']['yum-check-updates']['exitcode'] == 100);

		// Now we just need to parse that output into a list of RPMs to return.
		$retarr['rpms'] = $this->parseUpdates($updates['commands']['yum-check-updates']['output']);

		// If there is a 'sangoma-pbx.noarch' RPM, we have a PBX upgrade available
		if (isset($retarr['rpms']['sangoma-pbx.noarch'])) {
			$retarr['pbxupdateavail'] = [ "name" => 'sangoma-pbx.noarch', "version" => $retarr['rpms']['sangoma-pbx.noarch']['newvers'] ];
		} else {
			$retarr['pbxupdateavail'] = false;
		}

		$retarr['i18nstatus'] = $this->strarr[$retarr['status']];
		return $retarr;
	}

	/**
	 * Parse the output of yum-check-updates
	 *
	 * @return array
	 */
	public function parseUpdates($str) {
		$lines = explode("\n", $str);
		$rpms = [];
		$wrapped = null; //https://bugzilla.redhat.com/show_bug.cgi?id=584525
		foreach ($lines as $line) {
			// If the line is blank, or, starts with a space, ignore.
			if (!$line || trim($line) === "" || $line[0] === " ") {
				continue;
			}

			// Ignore any error lines
			$txt_ignore = array(
				"Trying other mirror",
				"Operation too slow",
				"is listed more than once in the configuration",
			);
			foreach ($txt_ignore as $txt) {
				if (strpos($line, $txt) !== false) {
					continue 2;
				}
			}

			$linearr = preg_split("/\s+/", $line);

			// Ignore if it's an 'Obsoleting Packages' line
			if ($linearr[0] === "Obsoleting") {
				continue;
			}

			if(!isset($linearr[1])) {
				$rpms[escapeshellcmd($linearr[0])] = ["newvers" => "", "repo" => ""];
				$wrapped = escapeshellcmd($linearr[0]);
				continue;
			}
			if(!empty($wrapped)) {
				$rpms[$wrapped] =  [ "newvers" => (isset($linearr[1]) ? $linearr[1] : ""), "repo" => (isset($linearr[2]) ? $linearr[2] : "") ];
				$wrapped = null;
			} else {
				$rpms[escapeshellcmd($linearr[0])] =  [ "newvers" => (isset($linearr[1]) ? $linearr[1] : ""), "repo" => (isset($linearr[2]) ? $linearr[2] : "") ];
			}
		}
		// Get our current versions
		$current = $this->getInstalledRpmVersions(array_keys($rpms));
		foreach ($current as $name => $ver) {
			if ($ver === false) {
				$rpms[$name]['installed'] = false;
				continue;
			}
			$rpms[$name]['installed'] = true;
			$rpms[$name]['currentversion'] = $ver;
		}
		return $rpms;
	}
	public function checkNotInstalledPkg(array $rpmResult) {
		$first_word = 'package';
		$last_word = 'is not installed';
		if (empty($rpmResult)) {
			return false;
		}
		$lastElem = end($rpmResult);
		if ($lastElem && preg_match('/^' . $first_word . '\b(.*)\b' . $last_word .'$/' , $lastElem, $matches)) {
			return true;
		}
                return false;
        }



	/**
	 * Run rpm to get the list of current versions
	 *
	 * Hand it an array of RPMs with the format of name.arch,
	 * and it returns the current version on the system
	 *
	 * @param array $rpms List of RPMS to query
	 *
	 * @return array Key/Val
	 */
	public function getInstalledRpmVersions(array $rpms) {
		$retarr = [];
		// If this is an empty array, we don't need to do anything
		if (!$rpms) {
			return $retarr;
		}

		// Our RPM Command
		$cmd = '/usr/bin/rpm -q --queryformat "%{NAME}.%{ARCH} %{VERSION}.%{RELEASE}\n" '.join(" ", $rpms);
		exec($cmd, $output, $ret);
		if ($ret == 1 && $this->checkNotInstalledPkg($output)) {
			/* if last package in rpm -q is not installed then it will have 1 as return code
			 * so we have to check if last line of return output has "package not installed"
			 * or not, if this is present then ignore the error and continue with processing */
		} else {
			if ($ret !== 0 && $ret !== 6) {
				// 6 = new packages are going to be installed
				if (function_exists("freepbx_log")) {
					freepbx_log(FPBX_LOG_CRITICAL, sprintf(_("Update error: Tried to run '%s', exit code %s"), $cmd, $ret));
				}
				throw new \Exception(sprintf(_("RPM command errored, Delete /dev/shm/yumwrapper/* and try again. Exit code %s - see FreePBX log for more info."), $ret));
			}
		}

		// Map the output of the rpm command to a temporary dict
		$current = [];
		foreach ($output as $line) {
			$tmparr = explode(" ", $line);
			if (strpos($line, "is not installed") === false) {
				$name = $tmparr[0];
				$ver = $tmparr[1];
			} else {
				// It's not installed
				$name = $tmparr[1];
				$ver = false;
			}
			$current[$name] = $ver;
		}

		// Now go through our RPMs and match them with the output
		foreach ($rpms as $i => $name) {
			if (!isset($current[$name])) {
				$retarr[$name] = false;
			} else {
				$retarr[$name] = $current[$name];
			}
		}
		return $retarr;
	}

	/**
	 * Get the Distro Version
	 */
	public function getDistroVersion() {
		if (!file_exists("/etc/sangoma/pbx-version")) {
			return "Unknown";
		}
		$vers = file("/etc/sangoma/pbx-version");
		// If it's empty, return an error
		if (!$vers || empty(trim($vers[0]))) {
			return "Error reading /etc/sangoma/pbx-version";
		}
		return trim($vers[0]);
	}

	/**
	 * Get the status of yum update
	 */
	public function getYumUpdateStatus() {
		try {
			$updates = $this->parseYumOutput("/dev/shm/yumwrapper/yum-update.log");
		} catch(\Exception $e) {
			freepbx_log(FPBX_LOG_ERROR,"Yum update failed to finish. Please see '/dev/shm/yumwrapper/yum-update.log' error was: {$e->getMessage()}");
			return [
				'lasttimestamp' => time(),
				'status' => 'yumerror',
				'i18nstatus' => $this->strarr['yumerror'],
				'currentlog' => [
					"Yum update failed to finish. Please see '/dev/shm/yumwrapper/yum-update.log'",
					$e->getMessage()
				],
			];
		}

		$retarr = [
			'lasttimestamp' => $updates['timestamp'],
			'status' => 'unknown',
			'i18nstatus' => $this->strarr['unknown'],
			'currentlog' => [],
		];

		// If we have a title, it started.
		if ($updates['title']) {
			$retarr['status'] = "inprogress";
		} else {
			// Nothing.
			return $retarr;
		}

		// We should have some output that can be displayed to the user
		if (file_exists("/dev/shm/yumwrapper/yum-update-current.log")) {
			$retarr['currentlog'] = file("/dev/shm/yumwrapper/yum-update-current.log", FILE_IGNORE_NEW_LINES);
			$summary = array();
			$summary[] = "--------------------------------------------------------------------------";
			$record_summary = false;
			foreach($retarr['currentlog'] as $index => $line) {
				$record_summary = (preg_match("~\bTransaction Summary\b~",$line)) ? true : $record_summary;
				$record_summary = ( preg_match("~\bDownloading packages\b~",$line) ) ? false : $record_summary;
				if (strpos($line, "No packages marked") === 0){
					$summary[] = "Transaction Summary : ".$retarr['currentlog'][$index];
					$retarr['currentlog'] =[];
					break;
				}
				if($record_summary && !(strpos($line, "============") === 0)){
					$summary[] = $retarr['currentlog'][$index];
				}
			}
			$summary[] = "-------------------------------------------------------------------------- \n \n \n ";
			$retarr['currentlog'] = array_merge($summary,$retarr['currentlog']);
		}

		// Has it finished?
		if (empty($updates['finishtimestamp'])) {
			// It's not finished, reload the page after 1 sec.
			$retarr['retryafter'] = 1000;
		} else {
			$retarr['status'] = "complete";
		}
		$retarr['i18nstatus'] = $this->strarr[$retarr['status']];
		return $retarr;
	}

	/**
	 * Render what is displayed in the System Updates tab
	 *
	 * This is used both in page.modules as well as ajax when it's asking for updates.
	 *
	 * Note: This generates onclick=... HTML. There is a valid (but, possibly, poor) reason for
	 * this, in that the elements are deleted on every reload, and I'd have to REMAP each one
	 * of them, every time the page is loaded.  This could easily lead to memory leaks, or,
	 * forgetting to map a new one when it's created.  So I made the decision to use onclick,
	 * and you can yell at me about it if you want.  --xrobau 2017-01-03
	 *
	 * @return string html to be displayed
	 */
	public function getSystemUpdatesPage() {
		$html = "<h3>"._("System Update Details")."</h3>";
		$yumstatus = $this->getYumUpdateStatus();
		$pending = $this->getPendingUpdates();
		// Are we idle, or are we doing something?
		if ($yumstatus['status'] !== "inprogress" && $pending['status'] !== "inprogress") {
			$idle = true;
			$currentstatus = _("Idle");
		} else {
			$idle = false;
			$currentstatus = _("Working");
		}
		if($this->checkIfTestingRepoEnabled()) {
			$html .= "<div class='container-fluid'>";
			$html .= show_help(_("This system has test repos enabled."), _('Test Repos Enabled'), false, false);
			$html .= "</div>";
		}
		$html .= "<div class='row'>
			<div class='col-xs-3'>"._("Current System Update Status:")."</div>
			<div class='col-xs-5'>$currentstatus</div>
			<div class='col-xs-4'><button id='refreshpagebutton' class='btn btn-default pull-right' onclick='reload_system_updates_tab()'>"._("Refresh page")."</button></div>
		</div>\n";
		$html .= "<div class='row'>
			<div class='col-xs-3'>"._("Last Online Check Status:")."</div>
			<div class='col-xs-5' id='pendingstatus' data-value='".$pending['status']."'>".($pending['lasttimestamp'] != 0 ? \FreePBX::View()->humanDiff($pending['lasttimestamp']) : _("Never"))." &nbsp; ".$this->strarr[$pending['status']]."</div>
		</div>\n";
		$html .= "<div class='row'>
			<div class='col-xs-3'>"._("Last System Update:")."</div>";
		// If lasttimestamp isn't false, we should have updates for the user to watch.
		if ($yumstatus['lasttimestamp']) {
			$html .= "<div class='col-xs-5' id='yumstatus' data-value='".$yumstatus['status']."'><a class='clickable' onclick='show_sysupdate_modal()'>".\FreePBX::View()->humanDiff($yumstatus['lasttimestamp'])." &nbsp; ".$this->strarr[$yumstatus['status']]."</a></div>";
		} else {
			$html .= "<div class='col-xs-5' id='yumstatus' data-value='complete'>"._("Unknown (System updates not run since last reboot)")."</div>";
		}

		$html .= "</div>\n";

		// If we have a yum update log, make it available
		if ($yumstatus['currentlog']) {
			$html .= "<script>window.currentupdate = ".json_encode($yumstatus)."</script>\n";
		}

		// If we're not idle, don't bother with anything else.
		if (!$idle) {
			return $html;
		}

		$rpmcount = count($pending['rpms']);
		if ($rpmcount == 0) {
			if ($pending['status'] == "yumerror") {
				$rpmtext = _("Unable to run 'yum check-updates', can't check for updates");
			} else {
				$rpmtext = _("No updates currently required!");
			}
		} else {
			if ($rpmcount == 1) {
				$rpmtext = _("1 RPM available for upgrade");
			} else {
				$rpmtext = sprintf(_("%s RPMs available for upgrade"), $rpmcount);
			}
		}
		$html .= "<div class='row'>
			<div class='col-xs-3'>"._("Updates Available:")."</div>
			<div class='col-xs-5'>$rpmtext</div>
		</div>";

		if ($rpmcount == 0) {
			// Add the 'Check Online' button.
			$html .= "<div class='row'>
				<div class='col-xs-12'>
					<span class='pull-right'>
						<button id='checkonlinebutton' class='btn btn-default pull-right' onclick='run_yum_checkonline()'>"._("Check Online")."</button>
					</span>
				</div>
			</div>\n";
			return $html;
		}

		// We're here because we have some RPMS available. Lets display them.
		$html .= "<table class='table'><tr><th>"._("RPM Name")."</th><th>"._("New Version")."</th><th>"._("Installed Version")."</th></tr>\n";
		foreach ($pending['rpms'] as $rpmname => $tmparr) {
			if (!$tmparr['installed']) {
				$html .= "<tr><td>$rpmname</td><td>".$tmparr['newvers']."</td><td>"._("New Package")."</td></tr>";
			} else {
				if ($pending['pbxupdateavail'] && $pending['pbxupdateavail']['name'] == $rpmname) {
					// Make it stand out as a major upgrade
					$style = 'style="background: #FEE"';
				} else {
					$style = "";
				}
				$html .= "<tr $style><td>$rpmname</td><td>".$tmparr['newvers']."</td><td>".$tmparr['currentversion']."</td></tr>";
			}
		}
		$html .= "</table>";
		$html .= "<div class='row'>
			<div class='col-xs-12'>
				<span class='pull-right'>
					<button id='updatesystembutton' class='btn btn-default pull-right' onclick='update_rpms()'>"._("Update System")."</button>
					<button id='checkonlinebutton' class='btn btn-default pull-right' onclick='run_yum_checkonline()'>"._("Check Online")."</button>
				</span>
			</div>
		</div>\n";
		return $html;
	}

	/**
	 * Implement locks to avoid multiple things modifying system
	 *
	 * @return void
	 */
	private function getLock() {
		if(!empty($this->lock)) {
			return $this->lock;
		}
		$lockStore = new SemaphoreStore();
		$factory = new Factory($lockStore);
		$this->lock = $factory->createLock('systemupdates',7200);
		return $this->lock;
	}

	private function checkIfTestingRepoEnabled() {
		//check "sangoma-devel" rpm installed
        $ret = exec("/usr/bin/rpm -qa|grep sangoma-devel");
        if (!empty($ret)) {
			//check if the yum repo "sng7-testing" is enabled
			$ret = exec("/usr/bin/yum repolist | grep -i sng7-testing");
			if (!empty($ret)) {
				return true;
			}
        }
        return false;
	}

	public function checkBrokenRpm()
	{
		if(!\FreePBX::Modules()->checkStatus('sysadmin')) {
			return false;
		}
		// in future add the broken rpm version to this array
		$brokenRpms = [
			'sysadmin-5.6-5.6.48.sng.noarch',
			'sysadmin-5.6-5.6.49.sng.noarch',
			'sysadmin-5.6-5.6.50.sng.noarch',
			'sysadmin-5.6-5.6.51.sng.noarch',
		];
		$rpmNames = ['sysadmin'];
		if(count($rpmNames) > 1) {
			$moduleStr = implode("|",$rpmNames);
			$process = exec("rpm -qa | grep -E '$moduleStr'");
		} else {
			$process = exec("rpm -qa | grep '".$rpmNames[0]."'");
		}
		foreach ($brokenRpms as $vers) {
			if (strpos($process, $vers) !== false) {
				return true;
			}
		}
		return false;
	}
}
