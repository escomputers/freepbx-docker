<?php
/**
 * https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
 * @backupGlobals disabled
 * @backupStaticAttributes disabled
 */

class CronTest extends PHPUnit_Framework_TestCase {

	protected static $c;

	public static function setUpBeforeClass() {
		self::$c = FreePBX::create()->Cron;
	}

	public function testPHPUnit() {
		$this->assertEquals("test", "test", "PHPUnit is broken.");
		$this->assertNotEquals("test", "nottest", "PHPUnit is broken.");
	}

	public function testCrons() {
		self::$c->add("@monthly /bin/false");
		$this->assertTrue(self::$c->checkLine("@monthly /bin/false"), "Line didn't exist when it should");
		self::$c->remove("@monthly /bin/false");
		$this->assertFalse(self::$c->checkLine("@monthly /bin/false"), "Line existed when it should");
		self::$c->add(array("@monthly /bin/false"));
		$this->assertTrue(self::$c->checkLine("@monthly /bin/false"), "Line didn't exist when it should (Again)");
		self::$c->add(array("magic" => "@daily", "command" => "/bin/false"));
		$this->assertTrue(self::$c->checkLine("@monthly /bin/false"), "Adding a magic line parsed incorrectly");
		self::$c->add(array("hour" => "1", "command" => "/bin/false"));
		$this->assertTrue(self::$c->checkLine("* 1 * * * /bin/false"), "Adding a logic line parsed incorrectly");
		self::$c->removeAll("/bin/false");
		$this->assertFalse(self::$c->checkLine("@monthly /bin/false"), "6: Line existed when it shouldn't");
		$this->assertFalse(self::$c->checkLine("* 1 * * * /bin/false"), "7: Line existed when it shouldn't");
	}
}
