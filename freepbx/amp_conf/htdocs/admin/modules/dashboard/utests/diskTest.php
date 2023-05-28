<?php
/**
* https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/

class DiskTest extends PHPUnit_Framework_TestCase {

	protected static $f;
	protected static $d;

	public static function setUpBeforeClass() {
		global $amp_conf, $db;
		include "/etc/freepbx.conf";
		include __DIR__.'/../classes/DiskUsage.class.php';
		self::$f = FreePBX::create();
		self::$d = new DiskUsage();
	}

	public function testNotLinux() {
		// So.. At the moment, we have NO non-linux checks.
		self::$d->systemtype = "other";
		$this->assertEmpty(self::$d->getAll(), "Information was returned for an unknown system");
		self::$d->systemtype = "linux";
	}

	public function testDFOutput() {
		$all = self::$d->getAll();
		$df = $all['df'];
		$this->assertNotEmpty($df, "DiskUsage GetAll returned nothing");
		// Grab the first entry
		$keys = array_keys($df);
		$firstdisk = $keys[0];
		$this->assertEquals($firstdisk[0], "/", "First disk - $firstdisk - doesn't start with a slash");
		$diskarr = $df[$firstdisk];
		$this->assertEquals($diskarr['mountpoint'], "/", "Mountpoint of $firstdisk doesn't start with a slash");
	}
}
