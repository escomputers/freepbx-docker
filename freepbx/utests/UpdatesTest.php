<?php
/**
* https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/
class UpdatesTest extends PHPUnit_Framework_TestCase {

	protected static $f;

	public static function setUpBeforeClass() {
		self::$f = FreePBX::create();
	}

	public function testSchedulerSettings() {
		$s = new \FreePBX\Builtin\UpdateManager();
		$old = $s->getCurrentUpdateSettings();
		$s->updateUpdateSettings([ "system_ident" => "Unit Tests" ]);
		$new = $s->getCurrentUpdateSettings();
		$this->assertEquals("Unit Tests", $new['system_ident'], "System Ident change didn't save");
		$s->updateUpdateSettings($old);
		$validate = $s->getCurrentUpdateSettings();
		$this->assertEquals($old['system_ident'], $validate['system_ident'], "Old System Ident didn't go back");
	}
}
