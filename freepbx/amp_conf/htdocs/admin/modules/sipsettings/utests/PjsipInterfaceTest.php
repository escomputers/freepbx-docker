<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/
class PjsipInterfaceTest extends PHPUnit_Framework_TestCase {

	protected static $f;

	public static function setUpBeforeClass() {
		include 'setuptests.php';
		self::$f = FreePBX::create();
	}

	public function testParse1() {
		$ss = self::$f->Sipsettings();
		$test = $ss->parseIpAddr(file(__DIR__."/test_ipaddr_1"));
		$this->assertTrue(is_array($test['auto']));
		$this->assertTrue(is_array($test['eth0']));
		$this->assertEquals("192.168.1.5", $test['eth0'][0]);
		$this->assertEquals("192.168.1.7", $test['eth0:ext-ip'][0]);
	}

	public function testParse2() {
		$ss = self::$f->Sipsettings();
		$test = $ss->parseIpAddr(file(__DIR__."/test_ipaddr_2"));
		$this->assertTrue(is_array($test['auto']));
		$this->assertTrue(is_array($test['eth0']));
		$this->assertEquals("22.22.22.22", $test['eth0'][0]);
		$this->assertEquals("2.3.4.5", $test['eth0:0'][0]);
	}
}
