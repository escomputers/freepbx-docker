<?php
/**
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/

class NetworkTest extends PHPUnit_Framework_TestCase {

	private static $cdr;

	public static function setUpBeforeClass() {
		include "setuptests.php";
		self::$cdr = \FreePBX::Cdr();
	}

	public function testDBHandle() {
		$dbh = self::$cdr->getCdrDbHandle();
		$this->assertEquals("object", gettype($dbh), "DBH Not an object");
		$this->assertEquals("Database", get_class($dbh), "Not a Database object");
	}
}

