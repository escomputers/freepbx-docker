<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/
class GetBindsTest extends PHPUnit_Framework_TestCase {

	protected static $f;

	public static function setUpBeforeClass() {
		include 'setuptests.php';
		self::$f = FreePBX::create();
	}

	public function testSipDrivers() {
		$driver = self::$f->Config->get_conf_setting('ASTSIPDRIVER');
		if ($driver !== "both" && $driver !== "chan_sip" && $driver !== "chan_pjsip") {
			$this->assertFail("Unknown driver '$driver'");
		} else {
			$this->assertTrue(true, "Driver OK");
		}
	}

	public function testPjsipBinds() {
		$binds = self::$f->SipSettings()->getBinds(true);
		$driver = self::$f->Config->get_conf_setting('ASTSIPDRIVER');
		if ($driver !== "both" && $driver !== "pjsip") {
			// I should get an empty array back for pjsip
			$this->assertEquals(array(), $binds['pjsip']['[::]'], "Didn't get an empty array when pjsip is not enabled");
		} else {
			// I should get, at least, udp back with a port.
			$this->assertTrue(isset($binds['pjsip']['[::]']['udp']), "UDP not returned when PJSIP is enabled");
			$this->assertTrue(is_numeric($binds['pjsip']['[::]']['udp']), "UDP port not numeric");
		}
	}

	public function testChansipBinds() {
		$binds = self::$f->SipSettings()->getBinds(true);
		$driver = self::$f->Config->get_conf_setting('ASTSIPDRIVER');
		if ($driver !== "both" && $driver !== "sip") {
			// I should get an empty array back for pjsip
			$this->assertEquals(array(), $binds['sip']['[::]'], "Didn't get an empty array when pjsip is not enabled");
		} else {
			// I should get, at least, udp back with a port.
			$this->assertTrue(isset($binds['sip']['[::]']['udp']), "UDP not returned when SIP is enabled");
			$this->assertTrue(is_numeric($binds['sip']['[::]']['udp']), "UDP port not numeric");
		}
	}
}
