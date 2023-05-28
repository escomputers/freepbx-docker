<?php
// vim: set ai ts=4 sw=4 ft=php:
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//
class Dashboard extends FreePBX_Helpers implements BMO {

	public function __construct($freepbx) {
		$this->db = $freepbx->Database;
		$this->freepbx = $freepbx;
		$this->maxage = $this->freepbx->Config->get('SYS_STATS_MAXAGE');
		if ($this->maxage < 50) {
			$this->maxage = 50;
		}
	}

	// Always regen sys stats if they're older or equal to this, in seconds.
	private $maxage = 50;

	// If I've been polled, regen stats if it's been more than this
	// number of times the length of time it took to generate it (eg,
	// if it took .1 seconds, and this is set to 100, it'll regen the
	// status every 10 seconds (or if it took .2, every 20 seconds)
	// This is to avoid extra load on the server when it's not needed.
	private $regen = 100;

	// Keep this number of days worth of system stats
	private $history = 90;

	// IF you're adding a new builtin hook, add the descriptive name
	// and size and everything to classes/DashboardHooks, too.
	private $builtinhooks = array(
		"overview" => "getAjaxOverview",
		"blog" => "getBlogPosts",
		"notifications" => "getAjaxNotifications",
		"sysstat" => "getAjaxSysStat",
		"aststat" => "getAjaxAsteriskStat",
		"uptime" => "getAjaxUptime",
		"srvstat" => "getAjaxServerStats",
		"registered" => "getRegoInfo",
		"notepad_save" => "saveNote",
		"notepad_del" => "delNote",

		// This is for testing, and isn't used. If you remove it, tests
		// will fail.
		"fake" => "fake",
	);

	public function install() {
		$this->freepbx->Config->remove_conf_settings(array("DASHBOARD_INFO_UPDATE_TIME", "MAXCALLS", "DASHBOARD_STATS_UPDATE_TIME"));
		$this->freepbx->Config->define_conf_setting('SYS_STATS_DISABLE', array(
			'value'       => false,
			'defaultval'  => false,
			'readonly'    => false,
			'hidden'      => false,
			'level'       => 0,
			'module'      => 'dashboard',
			'category'    => 'Dashboard Module',
			'emptyok'     => false,
			'sortorder'   => 1,
			'name'        => 'Disable collection of system statistics',
			'description' => 'Set this to true to prevent persistent collection of system statistics such as CPU, memory, and channel usage.',
			'type'        => CONF_TYPE_BOOL
		),true);

		$this->freepbx->Config->define_conf_setting('SYS_STATS_MAXAGE', array(
			'value'       => 50,
			'defaultval'  => 50,
			'readonly'    => false,
			'hidden'      => false,
			'level'       => 0,
			'options'     => array(50, 86400),
			'module'      => 'dashboard',
			'category'    => 'Dashboard Module',
			'emptyok'     => false,
			'sortorder'   => 1,
			'name'        => 'Expiry time for system statistics',
			'description' => 'Set the maximum age in seconds before system statistics are refreshed. The minimum value is 50 seconds.',
			'type'        => CONF_TYPE_INT
		),true);

		$this->freepbx->Config->define_conf_setting('VIEW_FW_STATUS', array(
			'value'       => true,
			'defaultval'  => true,
			'readonly'    => false,
			'hidden'      => false,
			'level'       => 0,
			'module'      => 'dashboard',
			'category'    => 'Dashboard Module',
			'emptyok'     => false,
			'sortorder'   => 1,
			'name'        => 'Display firewall status',
			'description' => 'The Dashboard will display a warning when the PBX Firewall is disabled. When this is set to \'no\', the Dashboard warning will be permanently suppressed.',
			'type'        => CONF_TYPE_BOOL
		),true);
		
		$feeds = $this->freepbx->Config->get('RSSFEEDS');
		$feeds = str_replace("\r","",$feeds);
		if(!empty($feeds)) {
			$feeds = explode("\n",$feeds);
			$i = 0;
			$urls = array();
			foreach($feeds as $feed) {
				$this->setConfig($feed, null, "content");
				$this->setConfig($feed, null, "etag");
				$this->setConfig($feed, null, "last_modified");
			}
		}
	}
	
	public function uninstall() {
	}
	public function backup() {
	}
	public function restore($backup) {
	}
	public function runTests($db) {
		return true;
	}

	public function __get($var) {
		switch($var) {
			case 'cache':
				$this->cache = $this->freepbx->Cache->cloneByNamespace('dashboard');
				return $this->cache;
			break;
		}
	}

	public function doConfigPageInit() {
	}

	public function myDialplanHooks() {
		return true;
	}

	public function doDialplanHook(&$ext, $engine, $priority) {
		// While we're here, we should check that our cronjob is
		// still there.
		$crons = $this->freepbx->Cron->getAll();
		foreach($crons as $c) {
			if(preg_match('/scheduler\.php/',$c,$matches)) {
				$this->freepbx->Cron->remove($c);
			}
		}

		$this->freepbx->Job->addClass('dashboard', 'scheduler', 'FreePBX\modules\Dashboard\Job', '* * * * *');
	}

	public function ajaxRequest($req, &$setting) {
		return true;
	}

	/**
	 * Chown hook for freepbx fwconsole
	 */
	public function chownFreepbx() {
		$files = array(
			array('type' => 'file', 'path' => __DIR__."/scheduler.php", 'perms' => 0755),
			array('type' => 'file', 'path' => __DIR__."/netmon.php", 'perms' => 0755),
		);
		return $files;
	}

	public function ajaxHandler() {
		if (!class_exists('DashboardHooks')) {
			include 'classes/DashboardHooks.class.php';
		}

		switch ($_REQUEST['command']) {
		case "deletemessage":
			\FreePBX::create()->Notifications->safe_delete($_REQUEST['raw'], $_REQUEST['id']);
			return array("status" => true);
			break;
		case "resetmessage":
			\FreePBX::create()->Notifications->reset($_REQUEST['raw'], $_REQUEST['id']);
			return array("status" => true);
			break;
		case "saveorder":
			$this->setConfig('visualorder',$_REQUEST['order']);
			return array("status" => true);
			break;
		case "getcontent":
				# Diskspace graph is comming from sysadmin
				if ($_REQUEST['rawname'] == 'Diskspace' && $this->freepbx->Modules->checkStatus("sysadmin") && method_exists($this->freepbx->Sysadmin, 'DashboardGraph')) {
					return array("status" => true, "content" => $this->freepbx->Sysadmin->DashboardGraph()->getContent());
				} else {
					if (file_exists(__DIR__ . '/sections/' . $_REQUEST['rawname'] . '.class.php')) {
						include(__DIR__ . '/sections/' . $_REQUEST['rawname'] . '.class.php');
						$class = '\\FreePBX\\modules\\Dashboard\\Sections\\' . $_REQUEST['rawname'];
						$class = new $class();
						return array("status" => true, "content" => $class->getContent($_REQUEST['section']));
					} else {
						return array("status" => false, "message" => _("Missing Class Object!"));
					}
			}
			break;
		case "gethooks":
			if (!$this->getConfig('allhooks')) {
				$e = null;
				$this->doDialplanHook($e, null, null); // Avoid warnings.
			}
			$config = $this->getConfig('allhooks');
			$order = $this->getConfig('visualorder');
			if(is_array($order)) {
				foreach($config as &$page) {
					# Call dashboard disk graph hook
					if ($this->freepbx->Modules->checkStatus("sysadmin") && method_exists($this->freepbx->Sysadmin, 'DashboardGraph')) {
						$page['entries'][] = $this->freepbx->Sysadmin->DashboardGraph()->getSections();
					}
					$entries = array();
					foreach($page['entries'] as $k => $e) {
						$o = isset($order[$e['section']]) ? $order[$e['section']] : $k;
						while(isset($entries[$o])) {
							$o++;
						}
						$entries[$o] = $e;
					}
					ksort($entries);
					$page['entries'] = $entries;
				}
			}
			return $config;
			break;
		case "sysstat":
			if (!class_exists('Statistics')) {
				include 'classes/Statistics.class.php';
			}
			$s = new Statistics();
			return $s->getStats();
			break;
		default:
			return DashboardHooks::runHook($_REQUEST['command']);
			break;
		}
	}

	public function ajaxCustomHandler() {
		switch($_REQUEST['command']){
			case "netmon":
				if (!class_exists('Netmon')) {
					include 'classes/Netmon.class.php';
				}
				$n = new \FreePBX\modules\Dashboard\Netmon();
				$n->getLiveStats();
				die();
			break;
		}
	}

	public function runTrigger() {
		// This is run every minute.
		if (!$this->freepbx->Config->get('SYS_STATS_DISABLE')) {
			$this->getSysInfo();
		}
	}

	public function genSysInfo() {

		// PHP SysInfo requires 'php-xml'. If it doesn't exist,
		// then we can't really do anything.
		if (!class_exists('DOMDocument') || !extension_loaded('mbstring')) {
			return false;
		}

		// Time how long it takes to run
		$start = microtime(true);
		if (!class_exists('SysInfo')) {
			include 'classes/SysInfo.class.php';
		}
		$si = SysInfo::create();
		$info = $si->getSysInfo();
		$end = microtime(true);
		$delay = (float) $end - $start;
		// This is now a float in seconds of how long it took
		// to generate the sysinfo.
		// Make sure it's a valid number, and it's not too small.
		if ($delay < 0.1 || is_nan($delay)) {
			$delay = 0.1;
		}

		$info['generationlength'] = $delay;

		$this->cache->save('latestsysinfo', $info);
		$this->setConfig($info['timestamp'], $info, 'MINUTES');

		$this->pruneSysInfo();

		return $info;
	}

	public function pruneSysInfo() {
		if (!class_exists('PruneHistory')) {
			include 'classes/PruneHistory.class.php';
		}

		$p = new PruneHistory($this->freepbx);
		$p->doPrune();
	}

	public function getSysInfo() {
		$si = $this->cache->fetch('latestsysinfo');

		// Does it need to be updated? Older than $maxage seconds?
		if (!$si || $si['timestamp'] + $this->maxage <= time()) {
			$si = $this->genSysInfo();
			return $si;
		}

		// Is this older than $regen * generation time? (See header of this file)
		$genafter = ($si['generationlength'] * $this->regen) + $si['timestamp'];

		// If it is, regenerate. If not, keep using the cached data.
		if ($genafter < time()) {
			$si = $this->genSysInfo();
		}
		return $si;
	}

	public function getSysInfoPeriod($period = null) {
		// Return all of Period's SysInfo
		if ($period === null) {
			throw new Exception("No Period given");
		}
		$retarr = $this->getAll($period);
		return $retarr;
	}

	// Manage Built in Hooks
	public function doBuiltInHook($hookname) {
		$funcname = substr($hookname, 8);
		if (!isset($this->builtinhooks[$funcname]))
			throw new Exception("I was asked for $funcname, but I don't know what it is!");

		$methodname = $this->builtinhooks[$funcname];
		if (!method_exists($this, $methodname))
			throw new Exception("$funcname wants to use $methodname, but it doesn't exist");

		return $this->$methodname();
	}

	public function genStatusIcon($res, $tt = null) {
		$glyphs = array(
			"ok" => "glyphicon-ok text-success",
			"warning" => "glyphicon-warning-sign text-warning",
			"error" => "glyphicon-remove text-danger",
			"unknown" => "glyphicon-question-sign text-info",
			"info" => "glyphicon-info-sign text-info",
			"critical" => "glyphicon-fire text-danger"
		);
		// Are we being asked for an alert we actually know about?
		if (!isset($glyphs[$res])) {
			return array('type' => 'unknown', "tooltip" => "Don't know what $res is", "glyph-class" => $glyphs['unknown']);
		}

		if ($tt === null) {
			// No Tooltip
			return array('type' => $res, "tooltip" => null, "glyph-class" => $glyphs[$res]);
		} else {
			// Generate a tooltip
			$html = '';
			if (is_array($tt)) {
				foreach ($tt as $line) {
					$html .= htmlentities(\ForceUTF8\Encoding::fixUTF8($line), ENT_QUOTES,"UTF-8")."\n";
				}
			} else {
				$html .= htmlentities(\ForceUTF8\Encoding::fixUTF8($tt), ENT_QUOTES,"UTF-8");
			}

			return array('type' => $res, "tooltip" => $html, "glyph-class" => $glyphs[$res]);
		}
		return '';
	}

	// The actual hooks themselves!
	private function getAjaxOverview() {
		// Autoloaded!
		$o = new Overview();
		return $o->getHTML();
	}

	private function getAjaxSysStat() {
		if (!class_exists('SysStat')) {
			include 'classes/SysStat.class.php';
		}
		$sysstat = new SysStat();
		return $sysstat->getHTML();
	}

	private function getAjaxUptime() {
		if (!class_exists('Uptime')) {
			include 'classes/Uptime.class.php';
		}
		$tmp = new Uptime();
		return $tmp->getHTML();
	}

	private function getRegoInfo() {
		$html = "<p>There would be system info here</p><p>If rob had written it</p>\n";
		return $html;
	}

	private function saveNote() {
		$content = new \StdClass;
		$content->content = substr($_REQUEST["content"], 0, 2048);
		$result = $this->setConfig(time(), $content, "notes");
		return $result ? _("Saved note") : _("An error occured");
	}

	private function delNote() {
		$result = $this->setConfig($_REQUEST["id"], false, "notes");
		return $result ? _("Deleted note") : _("An error occured");
	}

	private function getAjaxServerStats() {
		return "No\n";
	}
	public function extIgnoreList(){
		$numbers = array();
		$hooks = \FreePBX::Hooks()->processHooks();
		foreach ($hooks as $key => $value) {
			if(is_array($value)){
				$numbers = array_merge($numbers,$value);
			}
		}
		return $numbers;
	}

	public function getdiskspace(){
		include_once __DIR__.'/classes/DiskUsage.class.php';
		$obj_diskusage = new \DiskUsage();
		return $obj_diskusage->parsedf();
	}
}
