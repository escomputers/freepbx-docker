<?php
/**
* https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/

class MemTest extends PHPUnit_Framework_TestCase {

	protected static $f;
	protected static $m;

	public static function setUpBeforeClass() {
		global $amp_conf, $db;
		include "/etc/freepbx.conf";
		if (!class_exists('MemInfo')) {
			include __DIR__.'/../classes/MemInfo.class.php';
		}
		self::$f = FreePBX::create();
		self::$m = new MemInfo();
	}

	public function testNotLinux() {
		// So.. At the moment, we have NO non-linux checks.
		self::$m->systemtype = "other";
		$this->assertEmpty(self::$m->getAll(), "Information was returned for an unknown system");
		self::$m->systemtype = "linux";
		$this->assertNotEmpty(self::$m->getAll(), "Information was not returned for a linux system");
	}

	public function testMemInfo() {
		$all = self::$m->getAll();
		$this->assertNotEmpty($all, "Mem GetAll returned nothing");
	}

	public function testSwapSanity() {
		$all = self::$m->getAll();
		$swap = $all['swap'];
		$this->assertNotEquals($swap['total'], 0, 'Total Swap is zero? Do you have swap?');
		$this->assertTrue($swap['free'] > 0, 'No free swap, or, free is less than zero');
	}

	public function testMemSanity() {
		$all = self::$m->getAll();
		$mem = $all['raw'];
		$this->assertNotEquals($mem['MemTotal'], 0, 'MemTotal reports zero');
		$this->assertNotEquals($mem['MemFree'], 0, 'MemFree reports zero');
	}

}
