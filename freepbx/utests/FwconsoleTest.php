<?php
/**
* https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/
class FwconsoleTest extends PHPUnit_Framework_TestCase {

	protected static $f;

	public static function setUpBeforeClass() {
		self::$f = FreePBX::create();
	}

	public function testGenUnlockKey() {
		// Turn off remote unlock
		self::$f->Config()->set_conf_values(array('REMOTEUNLOCK' => 0),true,true);
		$out = `fwconsole genunlockkey 2>&1`;
		// Make sure it returns something that has 'KEY=' in it.
		$this->assertEquals(preg_match("/^KEY=/m", $out), 1, "Didn't find KEY= in output of genunlockkey");
		// Turn on remote unlock
		self::$f->Config()->set_conf_values(array('REMOTEUNLOCK' => 1),true,true);
		$out = `fwconsole genunlockkey 2>&1`;
		// Make sure it returns a key.
		$this->assertEquals(preg_match("/^KEY=[a-f0-9]{64}/m", $out), 1, "No key returned in '$out'");
	}
}
