<?php
/**
* https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/
class RequestTest extends PHPUnit_Framework_TestCase {

	protected static $f;
	public static $reqDefaults = array( "test5" => "default" );

	public static function setUpBeforeClass() {
		self::$f = FreePBX::create();
		$_REQUEST['test1'] = 1;
		$_REQUEST['test2'] = "two";
		$_REQUEST['test3'] = "3";
		$_REQUEST['test4'] = "'<>\"";
		$_REQUEST['radio'] = "radio=poot";
	}

	public function testRequest() {
		$f = self::$f;
		$this->assertEquals($f->getReq('test1'), 1, "Not Equal to 1");
		$this->assertEquals($f->getReq('test2'), "two", "Not Equal to 'two'");
		$f->setReq('test1', 'one');
		$this->assertEquals($f->getReq('test1'), "one", "Update didn't stick");
		$this->assertEquals($f->getReqUnsafe('test4'), "'<>\"", "Couldn't get unfiltered variables");
		$this->assertEquals($f->getReq('test4'), "&#039;&lt;&gt;&quot;", "HTML Filtering not working!");
		$this->assertEquals($f->getReq('test5'), "", "Default was found without classOverride");
		$f->classOverride = "RequestTest";
		$this->assertEquals($f->getReq('test5'), "default", "Default not found with classOverride");
		$this->assertEquals($f->getReq('test5'), "", "classOverride not resetting");
		$f->setReq('test1', false);
		$this->assertEquals($f->getReq('test1'), false, "Unable to delete a request");
		$this->assertEquals($f->getReq('test6'), false, "Found a request that didn't exist");
	}

	public function testRequestAll() {
		$f = self::$f;
		$f->delById('testreq');
		$this->assertEquals($f->getConfig("test1", "testreq"), "", "Test1 still exists");
		$this->assertEquals($f->getConfig("radio", "testreq"), "", "radio shouldn't exist for the first time");
		$f->importRequest(null, null, "testreq");
		$this->assertEquals($f->getConfig("test1", "testreq"), "1", "Test1 didn't import");
		$f->delById('testreq');
		$this->assertEquals($f->getConfig("test1", "testreq"), "", "Test1 still exists");
		$f->importRequest(array("test1", "radio"), null, "testreq");
		$this->assertEquals($f->getConfig("test1", "testreq"), "", "Test1 shouldn't exist");
		$this->assertEquals($f->getConfig("radio", "testreq"), "", "Excluded radio, it's still here");
		$f->delById('testreq');
		$f->importRequest(array("test1"), null, "testreq");
		$this->assertEquals($f->getConfig("test1", "testreq"), "", "Test1 shouldn't exist");
		$this->assertEquals($f->getConfig("radio", "testreq"), "poot", "Radio button not parsed correctly");
		$f->delById('testreq');
		$r = $f->importRequest(array("test1"), "/[34]$/", "testreq");
		$this->assertEquals($f->getConfig("test3", "testreq"), "", "Test3 shouldn't exist");
		$this->assertEquals($f->getConfig("test2", "testreq"), "two", "Test2 not loaded");
		$this->assertEquals($f->getConfig("radio", "testreq"), "poot", "Radio button not loaded");
		$this->assertEquals($r['test1'], 1, "Unloaded variable 1 not returned");
		$this->assertEquals($r['test3'], "3", "Unloaded variable 3 not returned");
		$this->assertEquals($r['test4'], "'<>\"", "Unloaded variable 4 not returned");
	}

}
