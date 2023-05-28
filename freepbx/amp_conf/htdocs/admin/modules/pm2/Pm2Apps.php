<?php

namespace FreePBX\modules\Pm2;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Console\Helper\ProgressBar;

class Pm2Apps {
	
	private $nodeloc = "/tmp";
	private $homedir = null;
	private $pm2Home = null;
	private $webuser = null;
	private $varlibdir = null;
	private $astlogdir = null;
	private $disablelogs = false;
	private $useproxy = false;
	private $proxy = null;

	public function __construct($config = array()){
		$this->nodeloc = __DIR__."/node";
		$this->homedir = $config['homedir'];
		$this->pm2Home = $config['homedir'] . "/.pm2";
		$this->webuser = $config['webuser'];
		$this->varlibdir = $config['varlibdir'];
		$this->astlogdir = $config['astlogdir'];
		$this->disablelogs = $config['disablelogs'];
		$this->useproxy = $config['useproxy'];
		$this->proxy = $config['proxy'];
		$this->shell = $config['shell'];
	}
	
	/**
	 * getAppStatus
	 *
	 * @return void
	 */
	public function getAppStatus(){
		$output = $this->runPM2Command("jlist");
		$processes = json_decode($output,true);
		if(json_last_error() !== JSON_ERROR_NONE) {
			$output = $this->runPM2Command("jlist");
			$processes = json_decode($output,true);
		}
		$processes = (!empty($processes) && is_array($processes)) ? $processes : array();
		$final = array();
		foreach($processes as $process) {
			$result = array();
			$result['PID'] = isset($process['pid'])? $process['pid']: '';
			$result['name'] = $process['name'];
			$result['status'] = $process['pm2_env']['status'];
			$result['uptime']  = ($process['pm2_env']['status'] == 'online') ? $this->get_date_diff(time(),(int)round($process['pm2_env']['pm_uptime']/1000)) : 0;
			$result['memory'] = $this->human_filesize($process['monit']['memory']);

			array_push($final,$result);
		}
		return $final;
	}
	
	/**
	 * human_filesize
	 *
	 * @param  mixed $bytes
	 * @param  mixed $dec
	 * @return void
	 */
	public function human_filesize($bytes, $dec = 2) {
		$size   = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
		$factor = floor((strlen($bytes) - 1) / 3);

		return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
	}

	/**
	 * Get human readable time difference between 2 dates
	 *
	 * Return difference between 2 dates in year, month, hour, minute or second
	 * The $precision caps the number of time units used: for instance if
	 * $time1 - $time2 = 3 days, 4 hours, 12 minutes, 5 seconds
	 * - with precision = 1 : 3 days
	 * - with precision = 2 : 3 days, 4 hours
	 * - with precision = 3 : 3 days, 4 hours, 12 minutes
	 *
	 * From: http://www.if-not-true-then-false.com/2010/php-calculate-real-differences-between-two-dates-or-timestamps/
	 *
	 * @param mixed $time1 a time (string or timestamp)
	 * @param mixed $time2 a time (string or timestamp)
	 * @param integer $precision Optional precision
	 * @return string time difference
	 */
	public function get_date_diff( $time1, $time2, $precision = 2 ) {
		// If not numeric then convert timestamps
		if( !is_int( $time1 ) ) {
			$time1 = strtotime( $time1 );
		}
		if( !is_int( $time2 ) ) {
			$time2 = strtotime( $time2 );
		}
		// If time1 > time2 then swap the 2 values
		if( $time1 > $time2 ) {
			list( $time1, $time2 ) = array( $time2, $time1 );
		}
		// Set up intervals and diffs arrays
		$intervals = array( 'year', 'month', 'day', 'hour', 'minute', 'second' );
		$diffs = array();
		foreach( $intervals as $interval ) {
			// Create temp time from time1 and interval
			$ttime = strtotime( '+1 ' . $interval, $time1 );
			// Set initial values
			$add = 1;
			$looped = 0;
			// Loop until temp time is smaller than time2
			while ( $time2 >= $ttime ) {
				// Create new temp time from time1 and interval
				$add++;
				$ttime = strtotime( "+" . $add . " " . $interval, $time1 );
				$looped++;
			}
			$time1 = strtotime( "+" . $looped . " " . $interval, $time1 );
			$diffs[ $interval ] = $looped;
		}
		$count = 0;
		$times = array();
		foreach( $diffs as $interval => $value ) {
			// Break if we have needed precission
			if( $count >= $precision ) {
				break;
			}
			// Add value and interval if value is bigger than 0
			if( $value > 0 ) {
				if( $value != 1 ){
					$interval .= "s";
				}
				// Add value and interval to times array
				$times[] = $value . " " . $interval;
				$count++;
			}
		}
		// Return string with times
		return implode( ", ", $times );
	}

	function safefilerewrite($fileName, $dataToSave) {
		if ($fp = fopen($fileName, 'w')) {
			$startTime = microtime(TRUE);
			do {
				$canWrite = flock($fp, LOCK_EX);
				// If lock not obtained sleep for 0 - 100 milliseconds, to avoid collision and CPU load
				if(!$canWrite) {
					usleep(round(rand(0, 100)*1000));
				};
			} while ((!$canWrite)and((microtime(TRUE)-$startTime) < 5));

			//file was locked so now we can store information
			if ($canWrite) {
				fwrite($fp, $dataToSave);
				flock($fp, LOCK_UN);
			}
			fclose($fp);
		}
	}

	function write_php_ini($file, $array) {
		$res = array();
		foreach($array as $key => $val) {
			if(is_array($val)) {
				$res[] = "[$key]";
				foreach($val as $skey => $sval) {
					$res[] = "$skey = ".(is_numeric($sval) ? $sval : '"'.$sval.'"');
				};
			} else {
				$res[] = "$key = ".(is_numeric($val) ? $val : '"'.$val.'"');
			};
		}
		$this->safefilerewrite($file, implode("\r\n", $res));
	}

	/**
	 * Generate run command string
	 * @method generateRunAsAsteriskCommand
	 * @param  string                       $command The command to run
	 * @param  string                       $environment Array of environment variables to run
	 * @return string                                The finalized command
	 */
	public function generateRunAsAsteriskCommand($command,$cwd='',$environment=array()) {
		$cwd = !empty($cwd) ? $cwd : $this->nodeloc;

		$npmrc = $this->homedir . "/.npmrc";
		if(!file_exists($npmrc)) {
			touch($npmrc);
			if (posix_getuid() == 0) {
				chown($npmrc,$this->webuser);
			}
		}

		$cmds = array(
			'cd '.$cwd,
			'mkdir -p '.$this->pm2Home
		);

		if(!$this->disablelogs) {
			$cmds[] = 'mkdir -p '.$cwd.'/logs';
		}

		$contents = file_get_contents($npmrc);
		$contents .= "\n";
		$ini = parse_ini_string($contents, false, INI_SCANNER_RAW);
		$ini = is_array($ini) ? $ini : array();
		$ini['prefix'] = '~/.node';
		if($this->useproxy) {
			$ini['proxy'] = $this->proxy;
			$ini['https-proxy'] = $this->proxy;
			$ini['strict-ssl'] = 'false';
			$cmds[] = 'export NODE_TLS_REJECT_UNAUTHORIZED=0';
		} else {
			unset($ini['proxy'],$ini['https-proxy'],$ini['strict-ssl']);
		}

		$this->write_php_ini($npmrc,$ini);

		foreach($environment as $k => $v) {
			if(empty($k) || !is_string($v)) {
				continue;
			}
			$cmds[] = 'export '.escapeshellarg($k).'='.escapeshellarg($v);
		}

		$cmds = array_merge($cmds,array(
			'export HOME='.escapeshellcmd($this->homedir),
			'export PM2_HOME='.escapeshellcmd($this->pm2Home),
			'export ASTLOGDIR='.escapeshellcmd($this->astlogdir),
			'export ASTVARLIBDIR='.escapeshellcmd($this->varlibdir),
			'export PATH=$HOME/.node/bin:$PATH',
			'export NODE_PATH=$HOME/.node/lib/node_modules:$NODE_PATH',
			'export MANPATH=$HOME/.node/share/man:$MANPATH',

		));
		$cmds[] = escapeshellcmd($command);
		$final = implode(" && ", $cmds);

		if (posix_getuid() == 0) {
			$shell = $this->shell;
			$shell = !empty($shell) ? $shell : '/bin/bash';
			$final = "runuser ".escapeshellarg($this->webuser)." -s ".escapeshellarg($shell)." -c ".escapeshellarg($final);
		}

		return $final;
	}

	/**
	 * Run a command against PM2
	 * @method runPM2Command
	 * @param  string        $cmd    The command to run
	 * @param  boolean       $stream Whether to stream the output or return it
	 */
	public function runPM2Command($cmd,$cwd='',$environment=array(),$stream=false,$timeout=240,$idleTimeout=null) {
		if(!file_exists($this->nodeloc."/node_modules/pm2/bin/pm2")){
			throw new \Exception("pm2 binary does not exist run fwconsole ma install pm2 and try again", 1);
		}
		if(!is_executable($this->nodeloc."/node_modules/pm2/bin/pm2")) {
			chmod($this->nodeloc."/node_modules/pm2/bin/pm2",0755);
		}
		$command = $this->generateRunAsAsteriskCommand($this->nodeloc."/node_modules/pm2/bin/pm2 ".$cmd,$cwd,$environment);
		$process = new Process($command);
		$process->setIdleTimeout($timeout);
		if(!empty($idleTimeout)) {
			$process->setTimeout($idleTimeout);
		}
		if(!$stream) {
			$process->mustRun();
			return $process->getOutput();
		} else {
			$process->setTty(true);
			$process->run(function ($type, $buffer) {
				if (Process::ERR === $type) {
					echo $buffer;
				} else {
					echo $buffer;
				}
			});
		}
	}
}
