<?php
/**
* https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/

class mainTest extends PHPUnit_Framework_TestCase {

	protected static $d;

	public static function setUpBeforeClass() {
		global $amp_conf, $db;
		include '/etc/freepbx.conf';
		self::$d = FreePBX::create()->Dashboard;
	}

	public function testSysInfo() {
		$d = self::$d;
		$si = $d->getSysInfo();
		$this->assertNotNull($si, 'Should return an array');
		$this->assertTrue(is_numeric($si['psi.Vitals.@attributes.LoadAvg.fifteen']), "Load Average not parsed correctly");
		$this->assertTrue(isset($si['psi.Network.NetDevice.0.@attributes.Info']), "IP Addresses not being detected");
	}

	public function testHour() {
		$d = self::$d;
		$null = $d->getSysInfo();
		$res = $d->getSysInfoPeriod("HOUR");
		$this->assertTrue(is_array($res), "getSysInfoPeriod didn't return an array");
		/* $this->assertGreaterThanOrEqual(1, count($res), "Res didn't return any rows");
		foreach ($res as $key => $row) {
			$this->assertTrue(is_array($row), "$key didn't return a row");
		} */
		$this->assertLessThanOrEqual(61, count($res), "Returned more than 61 rows for an hour");
	}

}
