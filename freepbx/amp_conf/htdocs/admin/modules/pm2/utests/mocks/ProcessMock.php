<?php

namespace FreePBX\modules\Pm2\utests\mocks;

class ProcessMock {
	private static $instantiated = false;
	private static $command = null;
	private static $timeout = null;
	private static $idleTimeout = null;
	private static $mustRun = false;

	static function reset() {
		self::$instantiated = false;
		self::$command = null;
		self::$timeout = null;
		self::$idleTimeout = null;
		self::$mustRun = null;
	}

	function __construct($command) {
		self::$command = $command;
		self::$instantiated = true;
	}

	public static function getCommand() {
		return self::$command;
	}

	public static function getTimeout() {
		return self::$timeout;
	}

	public static function getIdleTimeout() {
		return self::$idleTimeout;
	}

	public static function getMustRun() {
		return self::$mustRun;
	}

	public static function isInstantiated() {
		return self::$instantiated;
	}

	public function setIdleTimeout($idleTimeout) {
		self::$idleTimeout = $idleTimeout;
	}

	public function setTimeout($timeout) {
		self::$timeout = $timeout;
	}

	public function mustRun() {
		self::$mustRun = true;
	}

	public function getOutput() {
		return 'command response goes here';
	}
}