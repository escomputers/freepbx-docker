<?php

/**
* https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
* @backupGlobals disabled
*/
class AstmanTest extends PHPUnit_Framework_TestCase {

	protected static $c;

	public static function setUpBeforeClass() {
		self::$c = \FreePBX::create()->astman;
	}

	public function testPHPUnit() {
		$this->assertEquals("test", "test", "PHPUnit is broken.");
		$this->assertNotEquals("test", "nottest", "PHPUnit is broken.");
	}

	public function testAppExists() {
		$this->assertTrue(self::$c->app_exists('While'), "app_exists says 'while' does not exist it should");
		$this->assertFalse(self::$c->app_exists('FOO'), "app_exists says 'FOO' exist it shouldn't");
	}
	public function testGetAstDB(){
		//TEST FOR FREEPBX-14752
		$unfiltered = astdb_get();
		$filtered = astdb_get(array('DEVICE'));
		$this->assertArrayHasKey('DEVICE', $unfiltered);
		$this->assertArrayNotHasKey('DEVICE', $filtered);
	}
	public function testCaching() {
		self::$c->useCaching = false;
		$normal = self::$c->database_show();
		self::$c->useCaching = true;
		$cached = self::$c->database_show();

		$this->assertEquals($normal, $cached, "Fully Cached database does not match uncached");

		$cache = self::$c->getDBCache();
		$this->assertEquals($normal, $cache, "Fully Cached database does not match uncached");


		self::$c->useCaching = false;
		$normal = self::$c->database_show('AMPUSER');
		self::$c->useCaching = true;
		$cached = self::$c->database_show('AMPUSER');

		$this->assertEquals($normal, $cached, "Partial Cached database does not match uncached");

		self::$c->useCaching = false;
		self::$c->database_put('TEST','TEST','TEST');
		$test = self::$c->database_get('TEST','TEST');
		$this->assertEquals($test, 'TEST', "Partial Cached database does not match uncached");

		self::$c->useCaching = true;
		self::$c->database_put('TEST','TEST','TEST');
		$out = self::$c->database_show('TEST');
		$this->assertEquals($out['/TEST/TEST'], 'TEST', "Partial Cached database does not match uncached");

		self::$c->database_del('TEST','TEST');
		$out = self::$c->database_show('TEST');
		$this->assertEquals($out, array(), "Partial Cached database does not match uncached");

		self::$c->database_put('TEST','TEST','TEST');
		$out = self::$c->database_show('TEST');
		$this->assertEquals($out['/TEST/TEST'], 'TEST', "Partial Cached database does not match uncached");
		self::$c->database_deltree('TEST');
		$out = self::$c->database_show('TEST');
		$this->assertEquals($out, array(), "Partial Cached database does not match uncached");

		$test = self::$c->database_get('TEST','TEST');
		$this->assertEquals($test, null, "Partial Cached database does not match uncached");

		self::$c->useCaching = false;
		self::$c->database_put('TEST','TEST','TEST');
		$out = self::$c->database_show('TEST');
		$this->assertEquals($out['/TEST/TEST'], 'TEST', "Partial Cached database does not match uncached");

		self::$c->database_del('TEST','TEST');
		$out = self::$c->database_show('TEST');
		$this->assertEquals($out, array(), "Partial Cached database does not match uncached");

		$test = self::$c->database_get('TEST','TEST');
		$this->assertEquals($test, null, "Partial Cached database does not match uncached");

		self::$c->database_put('TEST','TEST','TEST');
		$out = self::$c->database_show('TEST');
		$this->assertEquals($out['/TEST/TEST'], 'TEST', "Partial Cached database does not match uncached");
		self::$c->database_deltree('TEST');
		$out = self::$c->database_show('TEST');
		$this->assertEquals($out, array(), "Partial Cached database does not match uncached");
	}
}
