<?php
// vim: set ai ts=4 sw=4 ft=php:
class NatGetTest extends PHPUnit_Framework_TestCase {

	public static function setUpBeforeClass() {
		if (!class_exists('FreePBX\modules\Sipsettings\NatGet')) {
			include __DIR__."/../Natget.class.php";
		}
		if (!function_exists('fpbx_which')) {
			function fpbx_which($x) { return "/sbin/$x"; }
		}
	}

	public function testGetIP() {
		$nat = new FreePBX\modules\Sipsettings\NatGet();
		$ip = $nat->getVisibleIP();
		$this->assertEquals($ip, filter_var($ip, FILTER_VALIDATE_IP), "I wasn't returned a valid IP by getVisibleIP");
		// Lets change it to get some rubbish
		$nat->urls = array(array("/dev/null", "xml"));
		$ip = $nat->getVisibleIP();
		$this->assertFalse($ip, "An error didn't return false");
		$nat->urls = array(array("/dev/null", "xml"), array("/dev/null", "xml"), array("/dev/null", "xml"));
		$nat->urls[] = array("http://myip.freepbx.org:5060/whatismyip.php", "xml");
		$ip = $nat->getVisibleIP();
		$this->assertEquals($ip, filter_var($ip, FILTER_VALIDATE_IP), "Multiple failures then a success, but didn't get an IP");
	}

	public function testGetRoutes() {
		$nat = new FreePBX\modules\Sipsettings\NatGet();
		$routes = $nat->getRoutes();
		$this->assertTrue(is_array($routes), "Routes aren't an array? That's crazy");
		$this->assertFalse(empty($routes), "This machine doesn't have any extra routes");
		$cidr = $routes[0][1];
		if ($cidr != "24" && $cidr != "16" && $cidr != "8") {
			$this->fail("Is this route detection wrong? ".json_Encode($routes[0]));
		}
	}

}


