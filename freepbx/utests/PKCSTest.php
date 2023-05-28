<?php
/**
* https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/
class PKCSTest extends PHPUnit_Framework_TestCase {

	protected static $p;

	public static function setUpBeforeClass() {
		self::$p = FreePBX::PKCS();
		`rm -rf /tmp/ssltest`;
		`mkdir /tmp/ssltest`;
		self::$p->setKeysLocation("/tmp/ssltest");
	}

	public function testPKCS() {
		$p = self::$p;
		$this->assertFalse(file_exists("/tmp/ssltest/unittests.cfg"), "Unittests.cfg shouldn't exist");
		$p->createConfig("unittests", "utests.local", "Fakename Pty Ltd");
		$this->assertTrue(file_exists("/tmp/ssltest/unittests.cfg"), "Unittests SHOULD exist, but it doesnt");
		// Make sure we can't generate a bad key
		try {
			$p->generateKey("unittests", "short");
			$this->fail("I was able to create a short password");
		} catch (\Exception $e) {
			// Errored Successfully
		}
		$p->generateKey("unittests", "thisisapassword", 1024);
		$this->assertTrue(file_exists("/tmp/ssltest/unittests.key"), "Key doesn't exist");
		// Check key size of generateKey
		$size = trim(`openssl rsa -in /tmp/ssltest/unittests.key -text -noout -passin pass:thisisapassword | head -1`);
		$this->assertEquals($size, "Private-Key: (1024 bit)", "Incorrect key size");
		// Try to use that key with an incorrect password
		try {
			$p->createCA("unittests", "wrongpassword");
			$this->fail("Invalid password didn't throw an exception");
		} catch (\Exception $e) {
			// It threw. Good.
		}
		$p->createCA("unittests", "thisisapassword", true);
		$this->assertTrue(file_exists("/tmp/ssltest/unittests.crt"), "CA Cert doesn't exist");
		$state = trim(`openssl x509 -in /tmp/ssltest/unittests.crt -purpose -noout -passin pass:thisisapassword | grep "SSL server CA : Yes"`);
		$this->assertTrue(!empty($state), "Didn't generate a CA Cert");

		// Now, generate a client csr.
		$csr = array("O" => "Unit Testing, Intl.", "CN" => "tests.local");
		$p->createCSR("client", $csr, true);
		$algo = trim(`openssl req -in /tmp/ssltest/client.csr -noout -text | grep "Signature Algo"`);
		$this->assertEquals($algo, "Signature Algorithm: sha256WithRSAEncryption", "Incorrect Signature Algo on CSR");

		// And sign it
		$p->selfSignCert("client", "unittests", "thisisapassword");
		$this->assertTrue(file_exists("/tmp/ssltest/client.crt"), "Certificate not created");
		// Make sure this is NOT a CA!
		$state = trim(`openssl x509 -in /tmp/ssltest/client.crt -purpose -noout | grep "SSL server CA : Yes"`);
		$this->assertTrue(empty($state), "Generated a CA cert, when it shouldn't have");

		// Everything's good! Clean up.
		`rm -rf /tmp/ssltest`;
	}
}
