<?php
/**
* https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/

class RealtimeTest extends PHPUnit_Framework_TestCase {

	protected static $f;

	public static function setUpBeforeClass() {
		self::$f = FreePBX::create();
	}

	public function testEnableQueueLog() {
		self::$f->Realtime()->enableQueueLog();
		self::$f->Realtime()->write();
		$this->assertTrue(file_exists("/etc/asterisk/extconfig.conf"), "Extconfig doesn't exist");
		$extconfig = file_get_contents("/etc/asterisk/extconfig.conf");
		$pos = strpos($extconfig, "queue_log=odbc,asteriskcdrdb,queuelog");
		$this->assertTrue(($pos !== false), "queue_log not in extconfig.conf");
	}

	public function testDisableQueueLog() {
		self::$f->Realtime()->disableQueueLog();
		self::$f->Realtime()->write();
		$this->assertTrue(file_exists("/etc/asterisk/extconfig.conf"), "Extconfig doesn't exist");
		$extconfig = file_get_contents("/etc/asterisk/extconfig.conf");
		$pos = strpos($extconfig, "queue_log=odbc,asteriskcdrdb,queuelog");
		$this->assertTrue(($pos === false), "queue_log is in extconfig.conf");
	}
}
