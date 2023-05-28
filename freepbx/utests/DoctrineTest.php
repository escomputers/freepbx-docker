<?php
/**
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/
class DoctrineDbTest extends PHPUnit_Framework_TestCase {

	protected static $f;

	public static function setUpBeforeClass() {
		self::$f = FreePBX::create();
	}

	public function testTableCreate() {
		$db = self::$f->Database();
		$db->query('drop table if exists `doctrinetests`');
		$table = $db->migrate('doctrinetests');
		$cols = array("key" => array("type" => "string", "length" => 10), "val" => array("type" => "string", "length" => 20));
		$table->modify($cols);
		unset($table);
		$ret = $db->query("describe `doctrinetests`")->fetchAll();
		$this->assertEquals("varchar(10)", $ret[0]['Type'], "DB Not created correctly");
		$this->assertEquals("varchar(20)", $ret[1]['Type'], "DB Not created correctly");
		$cols = array("key" => array("type" => "string", "length" => 30), "val" => array("type" => "string", "length" => 40));
		$table = $db->migrate('doctrinetests');
		$table->modify($cols);
		unset($table);
		$ret = $db->query("describe `doctrinetests`")->fetchAll();
		$this->assertEquals("varchar(30)", $ret[0]['Type'], "DB Not modified correctly");
		$this->assertEquals("varchar(40)", $ret[1]['Type'], "DB Not modified correctly");
		$db->query('drop table `doctrinetests`');
	}

	public function testTableInOtherDb() {
		// This test will fail if you have done non-standard stuff to astertiskcdrdb, it should
		// use the same username and password as the normal asterisk db
		$cdrdb = self::$f->Database('mysql:dbname=asteriskcdrdb');
		$cdrdb->query('drop table if exists `asteriskcdrdb`.`doctrinetests`');
		try {
			$ret = $cdrdb->query("describe `asteriskcdrdb`.`doctrinetests`")->fetchAll();
			$this->fail("The database should never exist, it was just dropped");
		} catch (\Exception $e) {
			// Pass
		}
		$table = $cdrdb->migrate('doctrinetests');
		$cols = array("key" => array("type" => "string", "length" => 10), "val" => array("type" => "string", "length" => 20));
		$table->modify($cols);
		unset($table);
		// If this throws an error, then it means the doctrinetests table was created in
		// the wrong database
		$ret = $cdrdb->query("describe `asteriskcdrdb`.`doctrinetests`")->fetchAll();
		$this->assertEquals("varchar(10)", $ret[0]['Type'], "DB Not created correctly");
		$this->assertEquals("varchar(20)", $ret[1]['Type'], "DB Not created correctly");
		$cols = array("key" => array("type" => "string", "length" => 30), "val" => array("type" => "string", "length" => 40));
		$table = $cdrdb->migrate('doctrinetests');
		$table->modify($cols);
		unset($table);
		$ret = $cdrdb->query("describe `doctrinetests`")->fetchAll();
		$this->assertEquals("varchar(30)", $ret[0]['Type'], "DB Not modified correctly");
		$this->assertEquals("varchar(40)", $ret[1]['Type'], "DB Not modified correctly");
		$cdrdb->query('drop table `doctrinetests`');
		$cdrdb->query('use asterisk');
	}
}
