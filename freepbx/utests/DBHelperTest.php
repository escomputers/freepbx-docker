<?php
/**
* https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/
class DBHelperTest extends PHPUnit_Framework_TestCase {

	protected static $f;

	public static function setUpBeforeClass() {
		self::$f = FreePBX::create();
	}

	public function testConnection() {
		$db = self::$f->Database();
		$db->query('drop table if exists `phpunittests`');
		$db->query('create table `phpunittests` (`x` CHAR(64) NOT NULL)');
		$db->query('insert into `phpunittests` values ("test")');
		$db->query('insert into `phpunittests` values ("test")');
		$ret = $db->query("SELECT DISTINCT(`x`) FROM `phpunittests`")->fetchAll(PDO::FETCH_COLUMN, 0);
		$this->assertEquals($ret[0], "test", "Database didn't return what I gave it");
		$this->assertTrue(!isset($ret[1]), "Database returned more than one row, should have only returned one");
		$db->query('drop table `phpunittests`');
	}

	public function testGetSet() {
		$f = self::$f;
		$this->assertTrue($f->setConfig('TESTVAR1', 'Tests'), 'Unable to Set Config Option');
		$this->assertEquals($f->getConfig('TESTVAR1'), 'Tests', 'TESTVAR1 is not equal to Tests in config');
		$this->assertFalse($f->getConfig('UNSETVAR'), 'getConfigOption did not return false on an unknown key');
		$this->assertTrue($f->setConfig('TESTVAR1'), 'Unable to delete config var TESTVAR1');
		$this->assertFalse($f->getConfig('TESTVAR1'), 'TESTVAR1 should have returned false');
	}

	public function testIds() {
		$f = self::$f;
		$this->assertTrue($f->setConfig('TESTVAR1', 'test1'), 'Unable to Set Option with a key');
		$this->assertTrue($f->setConfig('TESTVAR1', 'test2', 'id'), 'Unable to Set Option with a key');
		$this->assertEquals($f->getConfig('TESTVAR1'), 'test1', 'Wrong value returned without a key!');
		$this->assertEquals($f->getConfig('TESTVAR1', 'id'), 'test2', 'Wrong value returned with a key!');
		$f->setConfig('TESTVAR1');
		$this->assertFalse($f->getConfig('TESTVAR1'), 'Returned a value when it shouldnt!');
		$this->assertEquals($f->getConfig('TESTVAR1', 'id'), 'test2', 'IDed key not there any more');
		$f->setConfig('TESTVAR1', false, 'id');
		$this->assertFalse($f->getConfig('TESTVAR1', 'id'), 'Couldnt delete value with a key');
	}

	public function testGetRanges() {
		$f = self::$f;
		// Build a random array.
		for ($x = 0; $x<20; $x++) {
			$arr[$x] = rand(0,5000);
		}
		// Save it
		foreach ($arr as $k => $v) {
			$f->setConfig($k, $v, 'testrange');
		}

		// Load it
		$newarr = $f->getAll('testrange');

		// Check it!
		foreach ($arr as $k => $v) {
			$this->assertEquals($newarr[$k], $arr[$k], "Difference with returned data!");
		}

		// Now blow it away
		$f->delById('testrange');

		// They all should be gone.
		foreach ($arr as $k => $v) {
			$this->assertFalse($f->getConfig($k, 'testrange'), "delById didn't delete everything");
		}
	}

	public function testComplex() {
		$f = self::$f;
		// Build a random array.
		for ($x = 0; $x<20; $x++) {
			$arr[$x] = rand(0,5000);
		}

		$f->setConfig('tmparr', $arr);
		$res = $f->getConfig('tmparr');
		// Delete it, no need to leave it hanging around
		$f->setConfig('tmparr');
		$this->assertTrue(is_array($res), "Wsn't returned an array when I handed it one");
		// Check it!
		foreach ($res as $k => $v) {
			$this->assertEquals($res[$k], $arr[$k], "Difference with returned data!");
		}

		// OK, now an object.
		$obj = new StdClass();
		$obj->bp = "twisp";
		$obj->rd = "cool";
		$f->setConfig('tmpobj', $obj);
		$res = $f->getConfig('tmpobj');
		// Delete it, no need to leave it hanging around
		$f->setConfig('tmpobj');
		$this->assertTrue(is_object($res), "Wasn't returned an object when I handed it one");
		// Check it!
		$this->assertEquals($res->bp, "twisp", "BP isn't TwiSp");
		$this->assertEquals($res->rd, "cool", "RD isn't Cool!");
	}

	public function testFirstAndLast() {
		$f = self::$f;
		// Build a random array.
		for ($x = 10; $x<=40; $x++) {
			$arr[$x] = rand(0,5000);
		}

		// Save it
		foreach ($arr as $k => $v) {
			$f->setConfig($k, $v, 'testrange');
		}

		$this->assertEquals($f->getFirst('testrange'), 10, "getFirst didn't return 10");
		$this->assertEquals($f->getLast('testrange'), 40, "getFirst didn't return 40");
		$f->delById('testrange');
	}

	public function testGetKeys() {
		$f = self::$f;
		// Build a random array.
		for ($x = 10; $x<=40; $x++) {
			$arr[rand(0,5000)] = true;
		}

		// Now, load it into an id.
		foreach ($arr as $k => $v) {
			$f->setConfig($k, 'set', 'testrange');
		}

		// Grab all the keys from the test range.
		$keys = $f->getAllKeys('testrange');
		foreach ($keys as $k) {
			$this->assertTrue(isset($arr[$k]), "getAllKeys returned $k, but it wasn't in arr.");
		}

		// Now check that we were handed back EVERYTHING.
		foreach ($arr as $k => $v) {
			$this->assertTrue(in_array($k, $keys), "I couldn't find $k in the return from getAllKeys");
		}
		$f->delById('testrange');
	}

	public function testMulti() {
		$f = self::$f;
		// Create our array
		$arr = array( "TESTVAR1" => "t1", "TESTVAR2" => "t2", "TESTVAR3" => "t3" );
		$f->setMultiConfig($arr, 'testrange');
		$this->assertEquals($f->getConfig('TESTVAR1', 'testrange'), 't1', 'TESTVAR1 not multi-set correctly');
		$this->assertEquals($f->getConfig('TESTVAR2', 'testrange'), 't2', 'TESTVAR2 not multi-set correctly');
		$this->assertEquals($f->getConfig('TESTVAR3', 'testrange'), 't3', 'TESTVAR3 not multi-set correctly');
		$f->delById('testrange');
	}

	public function testDupes() {
		$f = self::$f;
		$db = self::$f->Database();
		// Ensure that a value overwrites a previous one
		$f->setConfig('TESTVAR1');
		$f->setConfig('TESTVAR1', 't1');
		$ret = $db->query("SELECT COUNT(`key`) FROM `kvstore_FreePBX` WHERE `key`='TESTVAR1' and `id`='noid'")->fetchAll(PDO::FETCH_COLUMN, 0);
		$this->assertEquals(1, $ret[0], "One value set, but didn't find one row");
		$f->setConfig('TESTVAR1', 't2');
		$ret = $db->query("SELECT COUNT(`key`) FROM `kvstore_FreePBX` WHERE `key`='TESTVAR1' and `id`='noid'")->fetchAll(PDO::FETCH_COLUMN, 0);
		$this->assertEquals(1, $ret[0], "One value set, but didn't find one row");
		$firsttestarr = array("this", "is" => "an", "annoying", 0, array("test", "of", "arrays"), false, true, -1);
		$othertestarr = array("another" => "annoying", 0, array("test", "of", "arrays"), false, true, -1);
		$f->setConfig('TESTVAR1', $firsttestarr);
		$ret = $db->query("SELECT COUNT(`key`) FROM `kvstore_FreePBX` WHERE `key`='TESTVAR1' and `id`='noid'")->fetchAll(PDO::FETCH_COLUMN, 0);
		$this->assertEquals(1, $ret[0], "One value set, but didn't find one row");
		$f->setConfig('TESTVAR1', $othertestarr);
		$ret = $db->query("SELECT COUNT(`key`) FROM `kvstore_FreePBX` WHERE `key`='TESTVAR1' and `id`='noid'")->fetchAll(PDO::FETCH_COLUMN, 0);
		$this->assertEquals(1, $ret[0], "One value set, but didn't find one row");
	}

	public function testDupesWithID() {
		$f = self::$f;
		$db = self::$f->Database();
		// Ensure that a value overwrites a previous one
		$f->setConfig('TESTVAR1', false, "withid");
		$f->setConfig('TESTVAR1', 't1', "withid");
		$ret = $db->query("SELECT COUNT(`key`) FROM `kvstore_FreePBX` where `key`='TESTVAR1' and `id`='withid'")->fetchAll(PDO::FETCH_COLUMN, 0);
		$this->assertEquals(1, $ret[0], "One value set, but didn't find one row");
		$f->setConfig('TESTVAR1', 't2', "withid");
		$ret = $db->query("SELECT COUNT(`key`) FROM `kvstore_FreePBX` where `key`='TESTVAR1' and `id`='withid'")->fetchAll(PDO::FETCH_COLUMN, 0);
		$this->assertEquals(1, $ret[0], "One value set, but didn't find one row");
		$firsttestarr = array("this", "is" => "an", "annoying", 0, array("test", "of", "arrays"), false, true, -1);
		$othertestarr = array("another" => "annoying", 0, array("test", "of", "arrays"), false, true, -1);
		$f->setConfig('TESTVAR1', $firsttestarr, "withid");
		$ret = $db->query("SELECT COUNT(`key`) FROM `kvstore_FreePBX` where `key`='TESTVAR1' and `id`='withid'")->fetchAll(PDO::FETCH_COLUMN, 0);
		$this->assertEquals(1, $ret[0], "One value set, but didn't find one row");
		$f->setConfig('TESTVAR1', $othertestarr, "withid");
		$ret = $db->query("SELECT COUNT(`key`) FROM `kvstore_FreePBX` where `key`='TESTVAR1' and `id`='withid'")->fetchAll(PDO::FETCH_COLUMN, 0);
		$this->assertEquals(1, $ret[0], "One value set, but didn't find one row");
	}

	public function testMigration() {
		$f = self::$f;
		$db = self::$f->Database();
		$kvstore = false;
		// Skip this test if kvstore doesn't exist
		try {
			$db->query("SELECT * FROM `kvstore` LIMIT 1");
			$kvstore = true;
		} catch (\Exception $e) {
			// Nothing
		}

		if (!$kvstore) {
			return;
		}

		$db->query("DELETE FROM `kvstore` WHERE `module`='MigrationTest'");
		$db->query("INSERT INTO `kvstore` VALUES ('MigrationTest', 'TESTVAR1', 'testcontents', NULL, 'noid')");
		$f->classOverride = "MigrationTest";
		$this->assertEquals("testcontents", $f->getConfig("TESTVAR1"), "TESTVAR1 wasn't migrated");
		// Make sure that it's gone
		$remains = $db->query("SELECT * FROM `kvstore` WHERE `module`='MigrationTest'")->fetchAll();
		$this->assertEquals(array(), $remains, "Migration didn't remove old data from kvstore");
		// Clean up
		$db->query("DROP TABLE `kvstore_MigrationTest`");

		// Multiple entry tests
		$db->query("DELETE FROM `kvstore` WHERE `module`='MigrationTest2'");
		$db->query("INSERT INTO `kvstore` VALUES ('MigrationTest2', 'TESTVAR1', 'moretestcontents', NULL, 'noid')");
		$db->query("INSERT INTO `kvstore` VALUES ('MigrationTest2', 'TESTVAR2', '[1,2,3,4]', 'json-arr', 'noid')");
		$db->query("INSERT INTO `kvstore` VALUES ('MigrationTest2', 'TESTVAR3', '{\"derp\":\"foo\"}', 'json-obj', 'noid')");
		// Get the first one, which should migrate all of them
		$f->classOverride = "MigrationTest2";
		$this->assertEquals("moretestcontents", $f->getConfig("TESTVAR1"), "TESTVAR1 wasn't migrated");
		$f->classOverride = "MigrationTest2";
		$testvar = $f->getConfig('TESTVAR2');
		$this->assertTrue(is_array($testvar), "TestVar2 isn't an array");
		$f->classOverride = "MigrationTest2";
		$testvar = $f->getConfig('TESTVAR3');
		$this->assertTrue(is_object($testvar), "TestVar3 isn't an object");
		// Make sure that it's gone
		$remains = $db->query("SELECT * FROM `kvstore` WHERE `module`='MigrationTest2'")->fetchAll();
		$this->assertEquals(array(), $remains, "Migration Test 2 didn't remove old data from kvstore");
		// Clean up
		$db->query("DROP TABLE `kvstore_MigrationTest2`");
	}

	public function testGetAllIds() {
		$f = self::$f;
		$f->setConfig("var1", "var1", "id1");
		$this->assertTrue(in_array("id1", $f->getAllids()), "id1 wasn't in the list of ids");
		$this->assertFalse(in_array("fakeid1", $f->getAllids()), "fakeid1 WAS in the list of ids??");
	}

	public function testBlobStorage() {
		$f = self::$f;
		$uuid = $f->insertBlob("this is text");
		$retarr = $f->getBlob($uuid);
		$this->assertEquals("raw", $retarr['type'], "Raw wasn't returned as the type");
		$this->assertEquals("this is text", $retarr['content'], "Content wasn't returned correctly");
		$f->deleteBlob($uuid);
		$retarr = $f->getBlob($uuid);
		$this->assertEquals(false, $retarr['type'], "Blob wasn't deleted - type not false");
		$this->assertEquals("", $retarr['content'], "Content wasn't deleted - string not empty");
	}

	public function testDbDelete() {
		$f = self::$f;
		$db = self::$f->Database();
		$f->classOverride = "DBDelTest";
		$f->setConfig("test1", "test1");
		$res = $db->query("SELECT * FROM `kvstore_DBDelTest`")->fetchAll();
		$this->assertTrue(isset($res[0]), "nothing written to kvstore_DBDelTest");
		$f->classOverride = "DBDelTest";
		$f->deleteAll();
		// This must now throw, as the table should be deleted
		try {
			$res = $db->query("SELECT * FROM `kvstore_DBDelTest`")->fetchAll();
			$this->fail("Table not dropped");
		} catch (\Exception $e) {
			// null
		}
		// Now make sure the data is gone, in case something crazy happened
		$f->classOverride = "DBDelTest";
		$this->assertFalse($f->getConfig("test1"), "test1 didn't return false");
		// And delete it again
		$f->classOverride = "DBDelTest";
		$f->deleteAll();
	}
}
