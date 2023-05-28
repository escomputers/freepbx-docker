<?php
/**
 * https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
 * @backupGlobals disabled
 * @backupStaticAttributes disabled
 */

class ViewsTest extends PHPUnit_Framework_TestCase {

	protected static $f;
	protected static $o;

	public static function setUpBeforeClass() {
		self::$f = FreePBX::create();
		self::$o = self::$f->View();
	}
	public function testDrawClock(){
		$expect = 'PHNwYW4gY2xhc3M9ImJ0biBidG4tZGVmYXVsdCBkaXNhYmxlZCI+PGI+VW5pdFRlc3Q8L2I+IDxzcGFuIGlkPSJVbml0VGVzdCIgZGF0YS10aW1lPSIxOTg4MzQ1NyIgZGF0YS16b25lPSJBbWVyaWNhL1Bob2VuaXgiPlVuaXRUZXN0PC9zcGFuPjwvc3Bhbj48c2NyaXB0PmlmKCQoIiNVbml0VGVzdCIpLmxlbmd0aCkge3ZhciB0aW1lID0gJCgiI1VuaXRUZXN0IikuZGF0YSgidGltZSIpO3ZhciB0aW1lem9uZSA9ICQoIiNVbml0VGVzdCIpLmRhdGEoInpvbmUiKTt2YXIgdXBkYXRlVGltZSA9IGZ1bmN0aW9uKCkgeyQoIiNVbml0VGVzdCIpLnRleHQobW9tZW50LnVuaXgodGltZSkudHoodGltZXpvbmUpLmZvcm1hdCgnSEg6bW06c3MgeicpKTt0aW1lID0gdGltZSArIDE7fTtzZXRJbnRlcnZhbCh1cGRhdGVUaW1lLDEwMDApO308L3NjcmlwdD4=';
		$output = self::$o->drawClock(19883457, 'America/Phoenix', 'UnitTest', 'UnitTest', 'UnitTest');
		$this->assertEquals($expect, base64_encode($output), "HTML for drawClock didn't match");
	}
}
