<?php
namespace FreePBX;
use Monolog as Mono;
class Logger {
	private $logDrivers = array();
	private $systemID;
	private $freepbx;
	private $defaultLogDir;
	private $configuredStreamPaths = array();

	const DEBUG = 100;
	const INFO = 200;
	const NOTICE = 250;
	const WARNING = 300;
	const ERROR = 400;
	const CRITICAL = 500;
	const ALERT = 550;
	const EMERGENCY = 600;

	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception("Need to be instantiated with a FreePBX Object");
		}
		$this->freepbx = $freepbx;
		$this->systemID = $this->freepbx->Config->get('FREEPBX_SYSTEM_IDENT');
		$this->defaultLogDir = $this->freepbx->Config->get('ASTLOGDIR');
	}

	/**
	 * Mapping for old freepbx_log calls
	 *
	 * @param [type] The level/severity of the error. Valid levels use constants:
	 *               FPBX_LOG_FATAL, FPBX_LOG_CRITICAL, FPBX_LOG_SECURITY, FPBX_LOG_UPDATE,
	 *               FPBX_LOG_ERROR, FPBX_LOG_WARNING, FPBX_LOG_NOTICE, FPBX_LOG_INFO.
	 * @param [type] $message The message
	 * @return void
	 */
	public function log($level, $message) {
		return $this->logWrite($message,array(),$level);
	}

	/**
	 * Write to freepbx.log as channel PBX
	 *
	 * @param string $message $message to log
	 * @param string $logLevel Level to log at
	 * @return void
	 */
	public function logWrite($message='',array $context = array(),$logLevel = self::DEBUG ){
		if(is_string($logLevel)){
			/** Anything with "LOG_" is added for backwards compatibility with FreePBX logging */
			$logLevel = ltrim($logLevel,'FPBX_');
		}
		switch ($logLevel) {
			case 'LOG_DEBUG':
			case 'DEBUG':
			case self::DEBUG:
				$logLevel = self::DEBUG;
			case 'LOG_NOTICE':
			case 'NOTICE':
			case FPBX_LOG_NOTICE:
			case self::NOTICE:
				$logLevel = self::NOTICE;
			case 'LOG_WARNING':
			case 'WARNING':
			case FPBX_LOG_WARNING:
			case self::WARNING:
				$logLevel = self::WARNING;
			case 'LOG_ERR':
			case 'ERROR':
			case self::ERROR:
			case FPBX_LOG_ERROR:
				$logLevel = self::ERROR;
			case 'LOG_CRIT':
			case FPBX_LOG_CRITICAL:
			case 'CRITICAL':
			case self::CRITICAL:
				$logLevel = self::CRITICAL;
			case 'LOG_ALERT':
			case FPBX_LOG_NOTICE:
			case 'ALERT':
			case self::ALERT:
				$logLevel = self::ALERT;
			case 'EMERGENCY':
			case self::EMERGENCY:
				$logLevel = self::EMERGENCY;
			case 'LOG_INFO':
			case FPBX_LOG_INFO:
			case 'INFO':
			case self::INFO:
			default:
				$logLevel = self::INFO;
		}
		return $this->channelLogWrite('',$message, $context, $logLevel);
	}

	/**
	 * Write to freepbx.log as channel $channel
	 *
	 * @param string $channel channel to log to
	 * @param string $message $message to log
	 * @param string $logLevel Level to log at
	 * @return void
	 */
	public function channelLogWrite($channel,$message='',array $context = array(),$logLevel = self::DEBUG ){
		return $this->driverChannelLogWrite('freepbx',$channel,$message,$context,$logLevel);
	}

	/**
	 * Write to $driver.log
	 *
	 * @param string $driver Driver to log to
	 * @param string $message $message to log
	 * @param string $logLevel Level to log at
	 * @return void
	 */
	public function driverLogWrite($driver,$message='',array $context = array(),$logLevel = self::DEBUG){
		return $this->driverChannelLogWrite($driver, '', $message, $context, $logLevel);
	}

	/**
	 * Write to $driver.log as channel $channel
	 *
	 * @param string $driver
	 * @param string $channel
	 * @param string $message
	 * @param string $logLevel
	 * @return void
	 */
	public function driverChannelLogWrite($driver,$channel='',$message='',array $context = array(),$logLevel = self::DEBUG){
		if ($driver != 'freepbx' || $driver != 'default') {
			//for normal per module logging we should create module specific log file.
			$this->createLogDriver($driver, $this->defaultLogDir.'/'.$driver.'.log');
		} else {
			$this->createLogDriver($driver);
		}
		$logger = !empty($channel) ? $this->logDrivers[$driver]->withName($channel) : $this->logDrivers[$driver];
		switch ($logLevel) {
			case self::DEBUG:
				return $logger->debug($message,$context);
			case self::NOTICE:
				return $logger->notice($message,$context);
			case self::WARNING:
				return $logger->warning($message,$context);
			case self::ERROR:
				return $logger->error($message,$context);
			case self::CRITICAL:
				return $logger->critical($message,$context);
			case self::ALERT:
				return $logger->alert($message,$context);
			case self::EMERGENCY:
				return $logger->emergency($message,$context);
			case self::INFO:
			default:
				return $logger->info($message,$context);
		}
	}

	/**
	 * Create a log driver that will log to $driver.log in the default log path
	 *
	 * @param string $driver
	 * @param string $path
	 * @param constant $minLogLevel
	 * @return object
	 */
	public function createLogDriver($driver, $path = '', $minLogLevel = self::DEBUG, $allowInlineLineBreaks = false){
		//default and freepbx are the same
		if($driver === 'default') {
			$driver = 'freepbx';
		}
		if(empty($path)) {
			$driver = 'freepbx';
		}
		if(isset($this->logDrivers[$driver])) {
			return $this->logDrivers[$driver];
		}
		if($driver === 'freepbx') {
			// during initial install, there may be no log file provided because the script has not fully bootstrapped
			// so we will default to a pre-install log file name. We will make a file name mandatory with a proper
			// default in FPBX_LOG_FILE
			$path = $this->freepbx->Config->get('FPBX_LOG_FILE');
			$path = !empty($path) ? $path : '/tmp/freepbx_pre_install.log';
		}

		if(in_array($path,$this->configuredStreamPaths)) {
			throw new \Exception("Multiple loggers for the same file isn't allowed");
		}
		$this->configuredStreamPaths[$driver] = $path;

		if($this->freepbx->Config->get('AMPDISABLELOG')) {
			$stream = new Mono\Handler\NullHandler($minLogLevel);
		} else {
			$AMPSYSLOGLEVEL = $this->freepbx->Config->get('AMPSYSLOGLEVEL');
			$AMPSYSLOGLEVEL = !empty($AMPSYSLOGLEVEL) ? $AMPSYSLOGLEVEL : 'FILE';
			switch ($AMPSYSLOGLEVEL) {
				case 'LOG_EMERG':
					$monlevel = 600;
				case 'LOG_ALERT':
					$monlevel = 550;
				case 'LOG_CRIT':
					$monlevel = 500;
				case 'LOG_ERR':
					$monlevel = 400;
				case 'LOG_WARNING':
					$monlevel = 300;
				case 'LOG_NOTICE':
					$monlevel = 250;
				case 'LOG_INFO':
					$monlevel = 200;
				case 'LOG_DEBUG':
					$monlevel = 100;
					$stream = new Mono\Handler\SyslogHandler($driver,LOG_USER,$monlevel);
					break;
				case 'SQL':     // Core will remove these settings once migrated,
				case 'LOG_SQL': // default to FILE during any interim steps.
				case 'FILE':
					$stream = new Mono\Handler\StreamHandler($path,$minLogLevel);
					break;
				default:
					throw new \Exception("Unknown AMPSYSLOGLEVEL of $AMPSYSLOGLEVEL");
			}
		}

		$dateFormat = "Y-m-d H:i:s";
		$output = "[%datetime%] [%channel%.%level_name%]: %message% %context% %extra%\n";
		$formatter = new Mono\Formatter\LineFormatter($output, $dateFormat, $allowInlineLineBreaks);
		$stream->setFormatter($formatter);

		$this->logDrivers[$driver] = new Mono\Logger($driver);
		$this->logDrivers[$driver]->pushHandler($stream);
		return $this->logDrivers[$driver];
	}

	/**
	 * Get the Monologger driver object
	 *
	 * @param string $driver
	 * @return object
	 */
	public function getDriver($driver) {
		if(!isset($this->logDrivers[$driver])) {
			$this->createLogDriver($driver, $this->defaultLogDir.'/'.$driver.'.log');
		}
		return $this->logDrivers[$driver];
	}
}
