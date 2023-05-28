<?php
/**
* https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/

class CPUTest extends PHPUnit_Framework_TestCase {

	protected static $f;
	protected static $c;

	public static function setUpBeforeClass() {
		global $amp_conf, $db;
		include "/etc/freepbx.conf";
		include __DIR__.'/../classes/CPUInfo.class.php';
		self::$f = FreePBX::create();
		self::$c = new CPUInfo();
	}

	public function testPHPUnit() {
		$this->assertEquals("test", "test", "PHPUnit is broken.");
		$this->assertNotEquals("test", "nottest", "PHPUnit is broken.");

		// This is more along the lines of 'Check how this version of PHP does things'
		$float = "123.456";
		$string = "a string";
		$this->assertSame((int)$float, 123, "(int) not working as expected with a float");
		$this->assertSame((int)$string, 0, "(int) not working as expected with a string");
		$this->assertNotSame((int)$string, "0", "PHP Is confused about types.");
	}

	public function testNotLinux() {
		// So.. At the moment, we have NO non-linux checks.
		self::$c->systemtype = "other";
		$this->assertEmpty(self::$c->getAll(), "Information was returned for an unknown system");
		self::$c->systemtype = "linux";
	}

	public function testCPUInfo() {
		$all = self::$c->getAll();
		$cpu = $all['cpuinfo'];
		$this->assertNotEmpty($cpu, "CPU GetAll returned nothing");
		$this->assertTrue(isset($cpu[0]['mhz']), "No CPU MHz Returned");
		$this->assertNotEquals((int)$cpu[0]['mhz'], 0, "Invalid CPU MHz Returned");
		$this->assertTrue(isset($cpu[0]['modelname']), "No CPU Model Name Returned");
		// $this->assertTrue(isset($cpu['sockets']), "Unknown number of sockets"); // This may not be visible on virtual hosts.
		$this->assertTrue(isset($cpu['cores']), "Unknown number of cores");
	}

	public function testLoadAvg() {
		$all = self::$c->getAll();
		$loadavg = $all['loadavg'];
		$this->assertTrue(isset($loadavg['util1']), "No Util1 returned");
		$this->assertTrue(isset($loadavg['util5']), "No Util5 returned");
		$this->assertTrue(isset($loadavg['util15']), "No Util15 returned");
		$this->assertTrue(is_numeric($loadavg['util1']), "Util1 not numeric?");
		$this->assertTrue(is_numeric($loadavg['util15']), "Util15 not numeric?");
	}
}
