<?php
/**
 * This is the FreePBX Big Module Object.
 *
 * Framework built-in BMO Class.
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
namespace FreePBX;
class Framework extends FreePBX_Helpers implements BMO {

	private static $sysUpdate = false;
	private static $monitoringObj = false;

	public function __construct($freepbx = null) {
		$this->freepbx = $freepbx;
	}
	/** BMO Required Interfaces */
	public function install() {
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
	public function doConfigPageInit() {
	}

	public function ajaxRequest($req, &$setting) {
		switch ($req) {
			case 'authping':
			case 'scheduler':
			case 'sysupdate':
			case 'reload':
			case 'navbarToogle':
			return true;
		}
		return false;
	}
	
	/**
	 * setSystemObj
	 *
	 * @param  mixed $obj
	 * @return void
	 */
	public function setSystemObj($obj){
		self::$sysUpdate = $obj;
	}
	
	/**
	 * getSystemObj
	 *
	 * @return void
	 */
	public function getSystemObj() {
		if(!self::$sysUpdate){
			 self::$sysUpdate = new \FreePBX\Builtin\SystemUpdates(true);
		}
		return self::$sysUpdate;
	}
	
	public function ajaxHandler() {
		switch ($_REQUEST['command']) {
		case 'authping':
			return 'authpong';
		case 'scheduler':
			$s = new Builtin\UpdateManager();
			return $s->ajax($_REQUEST);
		case 'sysupdate':
			$s = new Builtin\SystemUpdates();
			return $s->ajax($_REQUEST);
		case 'reload':
			return $this->doReload();
		case 'navbarToogle':
			$current = $this->getConfig("navbarToogle");
			if(empty($current)){
				$this->setConfig("navbarToogle", "no");
				$current = $this->getConfig("navbarToogle");
			}
			if(!empty($_REQUEST["click"]) && $_REQUEST["click"] == true){
				switch($current){
					case "yes":
						$this->setConfig("navbarToogle", "no");
						$current = "no";
						break;
					case "no":
						$this->setConfig("navbarToogle", "yes");
						$current = "yes";
						break;
				}
			}			
			return $current;

		}
		return false;
	}

	public function doReload($passthru=false) {
		$AMPSBIN = $this->freepbx->Config->get('AMPSBIN');
		if(!file_exists($AMPSBIN.'/fwconsole')) {
			$out = fpbx_which('fwconsole');
			if(empty($out)) {
				return [
					'status' => false,
					'message' => sprintf(_("Unable to find fwconsole at %s, Consider updating 'sbin Dir' to the location of fwconsole"),$AMPSBIN),
					'code' => 127,
					'raw' => []
				];
			}
			$this->freepbx->Config->update('AMPSBIN',dirname($out));
			$AMPSBIN = dirname($out);

		}
		if(!is_executable($AMPSBIN.'/fwconsole')) {
			return [
				'status' => false,
				'message' => sprintf(_("fwconsole is not executable at %s"),$AMPSBIN),
				'code' => 127,
				'raw' => []
			];
		}
		$process = new \Symfony\Component\Process\Process($AMPSBIN.'/fwconsole reload --json');
		$process->setTimeout(1800);
		$process->run();
		$output = $process->getOutput();
		$code = $process->getExitCode();

		preg_match_all("/^({.*})$/m", $output, $array);
		$output = !empty($array[1][0]) ? json_decode($array[1][0],true) : array("error" => _('Unknown Error. Please Run: fwconsole reload --verbose '));

		if($code !== 0 || isset($output['error'])) {
			return [
				'status' => false,
				'message' => isset($output['error']) ? $output['error'] : _('Unknown Error. Please Run: fwconsole reload --verbose '),
				'code' => $code,
				'raw' => $output
			];
		}

		return [
			'status' => true,
			'message' => _('Successfully reloaded'),
			'code' => $code,
			'raw' => $output
		];
	}

	/**
	 * Update AMI credentials in manager.conf
	 *
	 * @author Philippe Lindheimer
	 * @pram mixed $user false means don't change
	 * @pram mixed $pass password false means don't change
	 * @pram mixed $writetimeout false means don't change
	 * @returns boolean
	 *
	 * allows FreePBX to update the manager credentials primarily used by Advanced Settings and Backup and Restore.
	 */
	function amiUpdate($user=false, $pass=false, $writetimeout = false) {
		$ASTETCDIR = $this->freepbx->Config->get('ASTETCDIR');
		$conf_file = $ASTETCDIR . '/manager.conf';
		$ret = $ret2 = $ret3 = 0;
		$output = array();

		if(strpos($ASTETCDIR,"..") === false && !file_exists($conf_file)) {
			return;
		}

		if ($user === true) {
			$AMPMGRUSER = $this->freepbx->Config->get('AMPMGRUSER');
			$sed_arg = escapeshellarg('s/\s*\[general\].*$/TEMPCONTEXT/;s/\[.*\]/\[' . $AMPMGRUSER . '\]/;s/^TEMPCONTEXT$/\[general\]/');
			exec("sed -i.bak $sed_arg $conf_file", $output, $ret);
			if ($ret) {
				$this->freepbx->Logger->log(FPBX_LOG_ERROR,sprintf(_("Failed changing AMI user to [%s], internal failure details follow:"),$AMPMGRUSER));
				foreach ($output as $line) {
					$this->freepbx->Logger->log(FPBX_LOG_ERROR,sprintf(_("AMI failure details:"),$line));
				}
			}
		}

		unset($output);
		if ($pass === true) {
			$AMPMGRPASS = $this->freepbx->Config->get('AMPMGRPASS');
			$sed_arg = escapeshellarg('s/secret\s*=.*$/secret = ' . $AMPMGRPASS . '/');
			exec("sed -i.bak $sed_arg $conf_file", $output2, $ret2);
			if ($ret2) {
				$this->freepbx->Logger->log(FPBX_LOG_ERROR,sprintf(_("Failed changing AMI password to [%s], internal failure details follow:"), $AMPMGRPASS));
				foreach ($output2 as $line) {
					$this->freepbx->Logger->log(FPBX_LOG_ERROR,sprintf(_("AMI failure details:"),$line));
				}
			}

			// We've changed the password, let's update the notification
			//
			if ($AMPMGRPASS === 'amp111') {
				if (!$this->freepbx->Notifications->exists('core', 'AMPMGRPASS')) {
					$this->freepbx->Notifications->add_warning('core', 'AMPMGRPASS', _("Default Asterisk Manager Password Used"), _("You are using the default Asterisk Manager password that is widely known, you should set a secure password"));
				}
			} else {
				$this->freepbx->Notifications->delete('core', 'AMPMGRPASS');
			}
		}

		//attempt to set writetimeout
		unset($output);
		if ($writetimeout === true) {
			$ASTMGRWRITETIMEOUT = $this->freepbx->Config->get('ASTMGRWRITETIMEOUT');
			$sed_arg = escapeshellarg('s/writetimeout\s*=.*$/writetimeout = ' . $ASTMGRWRITETIMEOUT . '/');
			exec("sed -i.bak $sed_arg $conf_file", $output3, $ret3);
			if ($ret3) {
				$this->freepbx->Logger->log(FPBX_LOG_ERROR,sprintf(_("Failed changing AMI writetimout to [%s], internal failure details follow:"),$ASTMGRWRITETIMEOUT));
				foreach ($output3 as $line) {
					$this->freepbx->Logger->log(FPBX_LOG_ERROR,sprintf(_("AMI failure details:"),$line));
				}
			}
		}
		if ($ret || $ret2 || $ret3) {
			dbug("aborting early because previous errors");
			return false;
		}
		if ($this->freepbx->astman && $this->freepbx->astman->connected()) {
			$ast_ret = $this->freepbx->astman->Command('module reload manager');
		} else {
			unset($output);
			dbug("no astman connection so trying to force through linux command line");
			exec(fpbx_which('asterisk') . " -rx 'module reload manager'", $output, $ret2);
			if ($ret2) {
				$this->freepbx->Logger->log(FPBX_LOG_ERROR,_("Failed to reload AMI, manual reload will be necessary, try: [asterisk -rx 'module reload manager']"));
			}
		}
		if ($this->freepbx->astman && $this->freepbx->astman->connected()) {
			$this->freepbx->astman->disconnect();
		}
		global $bootstrap_settings;

		if (!$this->freepbx->astman || !$res = $this->freepbx->astman->connect($this->freepbx->Config->get('ASTMANAGERHOST') . ":" . $this->freepbx->Config->get('ASTMANAGERPORT'), $this->freepbx->Config->get('AMPMGRUSER') , $this->freepbx->Config->get('AMPMGRPASS'), $bootstrap_settings['astman_events'])) {
			// couldn't connect at all
			$this->freepbx->Logger->log(FPBX_LOG_CRITICAL,"Connection attmempt to AMI failed");
			return false;
		} else {
			global $astman;
			$astman = $this->freepbx->astman;
		}
		return true;
	}
	
	/**
	 * getMonitoringObj
	 *
	 * @return void
	 */
	public function getMonitoringObj() {
		if (!self::$monitoringObj) {
			self::$monitoringObj  = $this->freepbx->Monitoring();
		}
		return self::$monitoringObj;
	}
			
	/**
	 * setMonitoringObj
	 *
	 * @param  mixed $obj
	 * @return void
	 */
	public function setMonitoringObj($obj){
		return self::$monitoringObj = $obj; 
	}
	
	/**
	 * checkBackUpAndRestoreProgressStatus
	 *
	 * @return void
	 */
	public function checkBackUpAndRestoreProgressStatus() {
		
		$ret = exec("ps aux | grep backup", $outcome, $status);
		if(file_exists('/var/run/asterisk/restore_running.lock') || strpos(implode(",", $outcome), 'php /usr/sbin/fwconsole backup') !== false) {
			return false;
		}
		return true;
	}

	/**
	 * getInstalledModulesList
	 *
	 * @return array
	 */
	public function getInstalledModulesList()
	{
		$output=null;
		$result=null;
		exec('fwconsole ma list --format=json', $output, $result);
		return ['result' => $result, 'output' => $output];
	}
}
