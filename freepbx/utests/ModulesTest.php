<?php
/**
* https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/
class ModulesTest extends PHPUnit_Framework_TestCase {

	protected static $f;

	public static function setUpBeforeClass() {
		self::$f = FreePBX::create();
	}

	public function testPHPUnit() {
		$this->assertEquals("test", "test", "PHPUnit is broken.");
		$this->assertNotEquals("test", "nottest", "PHPUnit is broken.");
	}

	public function testModuleNames() {
		$this->assertEquals("Sipsettings", self::$f->Modules->getClassName("sipsettings"), "Unable to resolve sipsettings - is module not installed?");
		$this->assertEquals("Core", self::$f->Modules->getClassName("astmodules"), "Unable to resolve astmodules - is core disabled?");
		$this->assertEquals(false, self::$f->Modules->getClassName("randomwrongmodule"), "bug");
	}
}
