<?php
// vim: set ai ts=4 sw=4 ft=php:
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2014 Schmooze Com Inc.
//
namespace FreePBX\modules;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Console\Helper\ProgressBar;
class Pm2 extends \FreePBX_Helpers implements \BMO {
	private $nodever = "0.12.18";
	private $npmver = "2.15.11";
	private $pm2Home = "/tmp";
	private $nodeloc = "/tmp";
	private static $pm2app = false;

	public function __construct($freepbx = null) {
		$this->astman = $freepbx->astman;
		$this->db = $freepbx->Database;
		$this->freepbx = $freepbx;
		$this->pm2Home = $this->getHomeDir() . "/.pm2";
		$this->nodeloc = __DIR__."/node";
	}

	public function install() {
		$output = exec("node --version"); //v0.10.29
		$output = str_replace("v","",trim($output));
		if(empty($output)) {
			out(_("Node is not installed"));
			return false;
		}
		if(version_compare($output,$this->nodever,"<")) {
			out(sprintf(_("Node version is: %s requirement is %s. Run 'yum upgrade nodejs' from the CLI as root"),$output,$this->nodever));
			return false;
		}


		$output = exec("npm --version"); //v0.10.29
		$output = trim($output);
		if(empty($output)) {
			out(_("Node Package Manager is not installed"));
			return false;
		}
		if(version_compare($output,$this->npmver,"<")) {
			out(sprintf(_("NPM version is: %s requirement is %s. Run 'yum upgrade nodejs' from the CLI as root"),$output,$this->npmver));
			return false;
		}

		$webgroup = $this->freepbx->Config->get('AMPASTERISKWEBGROUP');

		$data = posix_getgrgid(filegroup($this->getHomeDir()));
		if($data['name'] != $webgroup) {
			out(sprintf(_("Home directory [%s] is not writable"),$this->getHomeDir()));
			return false;
		}

		if(file_exists($this->getHomeDir()."/.npm")) {
			$data = posix_getgrgid(filegroup($this->getHomeDir()."/.npm"));
			if($data['name'] != $webgroup) {
				if (posix_getuid() == 0) {
					exec("chown -R ".$webuser." ".$this->getHomeDir()."/.npm");
				} else {
					out(sprintf(_("Home directory [%s] is not writable"),$this->getHomeDir()."/.npm"));
					return false;
				}

			}
		}

		$set = array();
		$set['module'] = 'pm2';
		$set['category'] = 'Process Management';

		// PM2DISABLELOG
		$set['value'] = false;
		$set['defaultval'] =& $set['value'];
		$set['options'] = '';
		$set['name'] = 'Disable PM2 Logging';
		$set['description'] = 'Whether or not to invoke the PM2 log facility.';
		$set['emptyok'] = 0;
		$set['level'] = 1;
		$set['readonly'] = 1;
		$set['type'] = CONF_TYPE_BOOL;
		$this->freepbx->Config->define_conf_setting('PM2DISABLELOG',$set);

		// PM2USEPROXY
		$set['value'] = false;
		$set['defaultval'] =& $set['value'];
		$set['options'] = '';
		$set['name'] = 'Use Proxy Server for NPM';
		$set['description'] = 'This should only be turned on if you have issues installing node modules from NPM';
		$set['emptyok'] = 0;
		$set['level'] = 1;
		$set['readonly'] = 1;
		$set['type'] = CONF_TYPE_BOOL;
		$this->freepbx->Config->define_conf_setting('PM2USEPROXY',$set);

		// NODEJSBINDADDRESS
		$set['value'] = 'http://mirror.freepbx.org:6767/';
		$set['defaultval'] =& $set['value'];
		$set['options'] = '';
		$set['name'] = 'NPM Proxy Server Address';
		$set['description'] = 'The NPM Proxy server address to use';
		$set['emptyok'] = 0;
		$set['type'] = CONF_TYPE_TEXT;
		$set['level'] = 2;
		$set['readonly'] = 1;
		$this->freepbx->Config->define_conf_setting('PM2PROXY',$set);

		// PM2USECACHE
		$set['value'] = true;
		$set['defaultval'] =& $set['value'];
		$set['options'] = '';
		$set['name'] = 'Use package caching for NPM';
		$set['description'] = 'This should only be turned off if you have issues installing node modules from NPM';
		$set['emptyok'] = 0;
		$set['level'] = 1;
		$set['readonly'] = 1;
		$set['type'] = CONF_TYPE_BOOL;
		$this->freepbx->Config->define_conf_setting('PM2USECACHE',$set);


		$set['value'] = '/bin/bash';
		$set['defaultval'] =& $set['value'];
		$set['emptyok'] = 0;
		$set['level'] = 1;
		$set['readonly'] = 1;
		$set['name'] = 'Shell Executable';
		$set['description'] = 'Location of the shell to use for pm2 tasks';
		$set['type'] = CONF_TYPE_TEXT;
		$this->freepbx->Config->define_conf_setting('PM2SHELL',$set,true);

		$this->freepbx->Config->commit_conf_settings();

		outn(_("Installing/Updating Required Libraries. This may take a while..."));
		if (php_sapi_name() == "cli") {
			out("The following messages are ONLY FOR DEBUGGING. Ignore anything that says 'WARN' or is just a warning");
		}

		$this->installNodeDependencies('',function($data) {
			outn($data);
		});
		out("");
		out(_("Finished updating libraries!"));
		if(!file_exists($this->nodeloc."/node_modules/pm2/bin/pm2")) {
			out("");
			if($this->freepbx->Config->get("PM2DISABLELOG")) {
				out(_("There was an error installing and PM2 Logging is disabled"));
			} else {
				out(sprintf(_("There was an error installing. Please review the install log. (%s)"),$this->nodeloc."/logs/install.log"));
			}
			return false;
		}

		//need pm2 to be executable
		if(!is_executable($this->nodeloc."/node_modules/pm2/bin/pm2")) {
			chmod($this->nodeloc."/node_modules/pm2/bin/pm2",0755);
		}

		if(file_exists($this->pm2Home."/pm2.log")) {
			@unlink($this->pm2Home."/pm2.log");
		}

		try {
			$this->pm2Apps()->runPM2Command("update");
		} catch(\Exception $e) {}

		$this->pm2Apps()->runPM2Command("ping");
	}

	public function uninstall() {
	}

	public function backup(){
	}

	public function restore($backup){
	}

	public static function myConfigPageInits() {
	}

	public function doConfigPageInit($page) {
	}

	/**
	 * Get status of a process
	 * @method getStatus
	 * @param  string    $name The process name
	 * @return mixed          Return array of data if known or false if unknown
	 */
	public function getStatus($name) {
		$name = $this->cleanAppName($name);
		$processes = $this->listProcesses();
		foreach($processes as $process) {
			if($process['name'] == $name) {
				return $process;
			}
		}
		return false;
	}

	/**
	 * Start an application in the background
	 * @method start
	 * @param  string $name    The name of the application
	 * @param  string $process The process to run
	 * @return mixed           Output of getStatus
	 */
	public function start($name, $process, $environment=array(), $force = false) {
		$name = $this->cleanAppName($name);
		$pout = $this->getStatus($name);
		if(!$force && !empty($pout) && $pout['pm2_env']['status'] == 'online') {
			throw new \Exception("There is already a process by that name running!");
		}
		try {
			$this->pm2Apps()->runPM2Command("delete ".$name);
		} catch(\Exception $e) {}
		$processParts = explode(" ",$process,2);
		$force = ($force) ? '-f' : '';
		$astlogdir = $this->freepbx->Config->get("ASTLOGDIR");
		$PM2DISABLELOG = $this->freepbx->Config->get("PM2DISABLELOG");
		$errorLog = $PM2DISABLELOG ? '/dev/null' : $astlogdir."/".$name."_err.log";
		$outLog = $PM2DISABLELOG ? '/dev/null' : $astlogdir."/".$name."_out.log";
		$cwd = dirname($process);
		$args = !empty($processParts[1]) ? ' -- '.$processParts[1] : '';
		$this->pm2Apps()->runPM2Command("start ".$processParts[0]." ".$force." --update-env --name ".escapeshellarg($name)." -e ".escapeshellarg($errorLog)." -o ".escapeshellarg($outLog)." --log ".escapeshellarg("/dev/null")." --merge-logs --log-date-format 'YYYY-MM-DD HH:mm Z'".$args, $cwd, $environment);
		return $this->getStatus($name);
	}

	public function startFromDirectory($name, $process, $directory, $environment=array(), $force = false) {
		$name = $this->cleanAppName($name);
		$pout = $this->getStatus($name);
		if(!$force && !empty($pout) && $pout['pm2_env']['status'] == 'online') {
			throw new \Exception("There is already a process by that name running!");
		}
		try {
			$this->pm2Apps()->runPM2Command("delete ".$name);
		} catch(\Exception $e) {}
		$processParts = explode(" ",$process,2);
		$force = ($force) ? '-f' : '';
		$astlogdir = $this->freepbx->Config->get("ASTLOGDIR");
		$PM2DISABLELOG = $this->freepbx->Config->get("PM2DISABLELOG");
		$errorLog = $PM2DISABLELOG ? '/dev/null' : $astlogdir."/".$name."_err.log";
		$outLog = $PM2DISABLELOG ? '/dev/null' : $astlogdir."/".$name."_out.log";
		$args = !empty($processParts[1]) ? ' -- '.$processParts[1] : '';
		$this->pm2Apps()->runPM2Command("start ".$processParts[0]." ".$force." --update-env --name ".escapeshellarg($name)." -e ".escapeshellarg($errorLog)." -o ".escapeshellarg($outLog)." --log ".escapeshellarg("/dev/null")." --merge-logs --log-date-format 'YYYY-MM-DD HH:mm Z'".$args, $directory, $environment);
		return $this->getStatus($name);
	}

	/**
	 * Stop an application from running in the background
	 * @method stop
	 * @param  string $name The application name
	 */
	public function stop($name) {
		$name = $this->cleanAppName($name);
		$out = $this->getStatus($name);
		if(empty($out)) {
			throw new \Exception("There is no process by that name");
		}
		$this->pm2Apps()->runPM2Command("stop ".$name);
	}

	/**
	 * Restart process
	 * @method restart
	 * @param  string  $name The application name
	 */
	public function restart($name) {
		$name = $this->cleanAppName($name);
		$out = $this->getStatus($name);
		if(empty($out)) {
			throw new \Exception("There is no process by that name");
		}
		$this->pm2Apps()->runPM2Command("restart ".$name." --update-env");
	}

	/**
	 * Delete process
	 * @method delete
	 * @param  string  $name The application name
	 */
	public function delete($name) {
		$name = $this->cleanAppName($name);
		$out = $this->getStatus($name);
		if(empty($out)) {
			throw new \Exception("There is no process by that name");
		}
		$this->pm2Apps()->runPM2Command("delete ".$name);
	}

	/**
	 * Update the underlying PM2 process
	 * @method update
	 */
	public function update() {
		$this->pm2Apps()->runPM2Command("update",'',array(),true);
	}

	/**
	 * Reset counters for application
	 * @method update
	 */
	public function reset($name) {
		$name = $this->cleanAppName($name);
		$out = $this->getStatus($name);
		if(empty($out)) {
			throw new \Exception("There is no process by that name");
		}
		$this->pm2Apps()->runPM2Command("reset ".$name);
	}

	public function reloadLogs() {
		try {
			$this->pm2Apps()->runPM2Command("reloadLogs",'',array(),false,5,5);
		} catch(\Exception $e) {
			//https://github.com/Unitech/pm2/issues/3521
			if(get_class($e) == "Symfony\Component\Process\Exception\ProcessTimedOutException") {
				return;
			}
			throw $e;
		}
	}

	/**
	 * List Processes that PM2 is maintaining
	 * @method listProcesses
	 * @return array        Array of processes
	 */
	public function listProcesses() {
		$output = $this->pm2Apps()->runPM2Command("jlist");
		$processes = json_decode($output,true);
		//check for errors because of this:
		// [PM2] Spawning PM2 daemon with pm2_home=/home/asterisk/.pm2
		// [PM2] PM2 Successfully daemonized
		if(json_last_error() !== JSON_ERROR_NONE) {
			$output = $this->pm2Apps()->runPM2Command("jlist");
			$processes = json_decode($output,true);
		}
		$processes = (!empty($processes) && is_array($processes)) ? $processes : array();
		$final = array();
		foreach($processes as $process) {
			$process['pm2_env']['created_at_human_diff'] = ($process['pm2_env']['status'] == 'online') ? $this->pm2Apps()->get_date_diff(time(),(int)round($process['pm2_env']['created_at']/1000)) : 0;
			$process['monit']['human_memory'] = $this->pm2Apps()->human_filesize($process['monit']['memory']);
			$final[] = $process;
		}
		return $final;
	}

	public function chownFreepbx() {
		$files = array();
		$files[] = array('type' => 'execdir',
			'path' => __DIR__.'/node/node_modules/pm2/bin',
			'perms' => 0755);
		$files[] = array('type' => 'rdir',
			'path' => $this->pm2Home,
			'perms' => 0775);
		return $files;
	}

	/**
	 * Get the Asterisk Users Home Directory
	 * @method getHomeDir
	 * @return string     The home directory location
	 */
	private function getHomeDir() {
		$webuser = \FreePBX::Freepbx_conf()->get('AMPASTERISKWEBUSER');
		$web = posix_getpwnam($webuser);
		$home = trim($web['dir']);
		if (!is_dir($home)) {
			// Well, that's handy. It doesn't exist. Let's use ASTSPOOLDIR instead, because
			// that should exist and be writable.
			$home = \FreePBX::Freepbx_conf()->get('ASTSPOOLDIR');
			if (!is_dir($home)) {
				// OK, I give up.
				throw new \Exception(sprintf(_("Asterisk home dir (%s) doesn't exist, and, ASTSPOOLDIR doesn't exist. Aborting"),$home));
			}
		}
		return $home;
	}

	public function installNodeDependencies($cwd='',$callback=null,$environment=array(),$production=true) {
		$cwd = !empty($cwd) ? $cwd : $this->nodeloc;
		if($this->freepbx->Config->get('PM2USECACHE')) {
			$command = $this->pm2Apps()->generateRunAsAsteriskCommand('npm-cache -v',$cwd,$environment);
			$process = new Process($command);
			try {
				$process->mustRun();
				if(is_callable($callback)) {
					$callback("Found npm-cache v".$process->getOutput());
				}
			} catch (ProcessFailedException $e) {
				$command = $this->pm2Apps()->generateRunAsAsteriskCommand('npm install -g npm-cache',$cwd,$environment);
				exec($command);

				$command = $this->pm2Apps()->generateRunAsAsteriskCommand('npm-cache -v',$cwd,$environment);
				$process = new Process($command);
				try {
					$process->mustRun();
					if(is_callable($callback)) {
						$callback("Installed npm-cache v".$process->getOutput());
					}
				} catch (ProcessFailedException $e) {
					out($e->getMessage());
					$this->freepbx->Config->update('PM2USECACHE',0);
				}
			}
		}

		if(is_callable($callback)) {
			$callback("Running installation..\n");
		}

		$PM2DISABLELOG = $this->freepbx->Config->get("PM2DISABLELOG");

		if(!$PM2DISABLELOG && !file_exists($cwd."/logs")) {
			mkdir($cwd."/logs",0777,true);
		}

		if(!$PM2DISABLELOG) {
			file_put_contents($cwd."/logs/install.log","");
			$webuser = $this->freepbx->Config->get('AMPASTERISKWEBUSER');
			$webgroup = $this->freepbx->Config->get('AMPASTERISKWEBGROUP');
			chown($cwd."/logs/install.log",$webuser);
		}

		$prod = ($production) ? ' --only=production' : '';
		if($this->freepbx->Config->get('PM2USECACHE')) {
			$command = $this->pm2Apps()->generateRunAsAsteriskCommand('npm-cache install'.$prod,$cwd,$environment);
		} else {
			$command = $this->pm2Apps()->generateRunAsAsteriskCommand('npm install'.$prod,$cwd,$environment);
		}
		if(!$PM2DISABLELOG) {
			$log = fopen($cwd."/logs/install.log", "a");
		}
		$output = function($message) use ($PM2DISABLELOG, $log, $callback) {
			if(!$PM2DISABLELOG) {
				fwrite($log,$message);
			}
			if (php_sapi_name() == "cli") {
				if(is_callable($callback)) {
					$callback($message);
				}
			} else {
				if(is_callable($callback)) {
					$callback(".");
				}
			}
		};
		try {
			$process = new Process($command);
			$process->setTimeout(3600);
			$process->setIdleTimeout(600);
			$process->run(function ($type, $buffer) use ($output) {
				$output($buffer);
			});
		} catch(\Exception $e) {
			$output($e->getMessage());
			return false;
		}
		if(!$PM2DISABLELOG) {
			fclose($log);
		}
		return $process->isSuccessful();
	}

	/**
	 * Turn all spaces into underscores and remove all utf8
	 * from filenames
	 * @param  string $name The filename
	 * @return string       The cleaned filename
	 */
	private function cleanAppName($name) {
		$name = pathinfo($name,PATHINFO_FILENAME);
		$name = str_replace(" ","-",$name);
		if(function_exists('iconv')) {
			$name = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $name);
		}
		$name = preg_replace('/\s+|\'+|\\\+|\$+|`+|\"+|<+|>+|\?+|\*+|,+|\.+|&+|;+|\/+/','-',$name);
		$name = preg_replace('/[\x00-\x1F\x80-\xFF]/u', '', $name);
		return $name;
	}

	/**
	 * getPm2AppsObj
	 *
	 * @param  mixed $freepbx
	 * @return void
	 */
	public function pm2Apps() {
		if (!self::$pm2app) {
			if (!class_exists('FreePBX\\modules\\pm2\Pm2Apps')) {
				include_once __DIR__."/admin/modules/pm2/Pm2Apps.php";
            }

			$config = array(
				'homedir' => $this->getHomeDir(),
				'webuser' => $this->freepbx->Config->get('AMPASTERISKWEBUSER'),
				'varlibdir' => $this->freepbx->Config->get("ASTVARLIBDIR"),
				'astlogdir' => $this->freepbx->Config->get("ASTLOGDIR"),
				'disablelogs' => $this->freepbx->Config->get("PM2DISABLELOG"),
				'useproxy' => $this->freepbx->Config->get('PM2USEPROXY'),
				'proxy' => $this->freepbx->Config->get('PM2PROXY'),
				'shell' => $this->freepbx->Config->get('PM2SHELL'),
			);
			self::$pm2app = new \FreePBX\modules\pm2\Pm2Apps($config);
		}
		return self::$pm2app;
	}
			
	/**
	 * setPm2Obj
	 *
	 * @param  mixed $obj
	 * @return void
	 */
	public function setPm2AppsObj($obj){
		return self::$pm2app = $obj; 
	}
}
