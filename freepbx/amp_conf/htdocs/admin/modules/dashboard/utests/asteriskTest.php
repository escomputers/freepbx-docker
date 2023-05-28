<?php
/**
* https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/

class AsteriskTest extends PHPUnit_Framework_TestCase {

	protected static $f;
	protected static $a;

	public static function setUpBeforeClass() {
		global $amp_conf, $db;
		include "/etc/freepbx.conf";
		include __DIR__.'/../classes/AsteriskInfo.class.php';
		self::$f = FreePBX::create();
		self::$a = new AsteriskInfo2();
	}

	public function testAstmanConnection() {
		$astman = self::$f->astman;
		$response = $astman->send_request('Command',array('Command'=>"core show uptime"));
		$lines = explode("\n", $response['data']);
		$this->assertSame(strpos($lines[1], "System uptime"), (int)0, "Uptime string incorrect");
		$this->assertTrue(is_object(self::$a->astman), "AsteriskInfo's astman isn't an object");
	}

	public function testChannelTotals() {
		$ct = self::$a->get_channel_totals();
		$this->assertNotEmpty($ct, "get_channel_totals returned nothing");
		$this->assertNotNull($ct['total_channels'], "get_channel_totals didn't return total_channels");
	}

	public function testConnections() {
		$cn = self::$a->get_connections();
		$this->assertNotEmpty($cn, "get_connections returned nothing");
	}

	public function testUptime() {
		$up = self::$a->get_uptime();
		$this->assertNotEmpty($up, "get_uptime returned nothing");
		$this->assertTrue(isset($up['system']), "System uptime not returned");
		$this->assertTrue(isset($up['reload']), "Time since reload not returned");
	}
}
