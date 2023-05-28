<?php
/**
* https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/
class MailTest extends PHPUnit_Framework_TestCase {

	protected static $f;

	public static function setUpBeforeClass() {
		self::$f = FreePBX::create();
	}

	public function testPHPUnit() {
		$this->assertEquals("test", "test", "PHPUnit is broken.");
		$this->assertNotEquals("test", "nottest", "PHPUnit is broken.");
	}

	public function testAttach() {
    $mail = self::$f->Mail();
		$from = 'unittest@localhost';
    touch('/tmp/mailtest.txt');
		$mail = self::$f->Mail();
		$mail->setSubject('Test');
		$mail->setFrom($from,$from);
		$mail->setTo(array('root@localhost'));
		$mail->setBody("UNIT TEST");
		$mail->addAttachment('/tmp/mailtest.txt');
    $ret = $mail->send();
    $this->assertTrue((bool)$ret, "Mail didn't send");
	}
}
