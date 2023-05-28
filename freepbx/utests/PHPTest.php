<?php
/**
* https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/
class PHPTest extends PHPUnit_Framework_TestCase {

	public function testPHPPop() {
		// BMO's Performance->Start and ->Stop rely on array_pop returning the last thing
		// that was added to the array, no matter what the name.
		// This is not explicitly specified anywhere, but does currently work in 5.3.28.
		// Let's make sure it works in the version you're using.
		$arr = array("one", 1, "three");
		$arr[] = "four";
		$arr['five'] = "five";
		$arr[6] = "six";
		$this->assertEquals(array_pop($arr), "six", "Array_pop not working as expected (six)");
		$this->assertEquals(array_pop($arr), "five", "Array_pop not working as expected (five)");
		$this->assertEquals(array_pop($arr), "four", "Array_pop not working as expected (four)");
	}
}
