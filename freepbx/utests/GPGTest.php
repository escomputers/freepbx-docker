<?php
/**
* https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/
class GPGTest extends PHPUnit_Framework_TestCase {

	protected static $f;

	public static function setUpBeforeClass() {
		self::$f = FreePBX::create();
		// Ensure that our /etc/freepbx.secure directory exists
		if (!is_dir("/etc/freepbx.secure")) {
			if (posix_geteuid() !== 0) {
				throw new \Exception("Can't create /etc/freepbx.secure, not runnign tests as root");
			} else {
				mkdir("/etc/freepbx.secure");
			}
		}
		chmod("/etc/freepbx.secure", 0644);
	}

	public function testGPG() {
		$f = self::$f;
		$this->assertTrue($f->GPG->trustFreePBX(), "Unable to trust FreePBX");
	}

	public function testPermissions() {
		$f = self::$f;
		$dir = "/home/asterisk/.gnupg";
		$user = "asterisk";
		$getpwnam = posix_getpwnam($user);
		$uid = $getpwnam['uid'];
		$gid = $getpwnam['gid'];
		`rm -rf $dir`;
		$this->assertFalse(is_dir($dir), "Directory not removed");
		$this->assertTrue($f->GPG->trustFreePBX(), "Unable to trust FreePBX");
		// Now, check permissions
		$this->assertTrue(is_dir($dir), "Directory not created");
		$stat = stat($dir);
		$this->assertEquals($uid, $stat['uid'], "$dir has the wrong permissions");
		// Check all the files.
		$files = glob($dir."/*");
		foreach ($files as $f) {
			$fstat = stat($f);
			$this->assertEquals($uid, $fstat['uid'], "$f has the wrong uid");
			$this->assertEquals($gid, $fstat['gid'], "$f has the wrong gid");
		}
	}

	public function testVerifyFile() {
		$file = "owEBagKV/ZANAwACAVH1to0lFV3LAaw6YgR0ZXN0VDrnF1RoaXMgaXMgYSBmaWxlIHVzZWQgZm9yIHRlc3RpbmcgR1BHOjp2ZXJpZnlGaWxlCokCHAQAAQIABgUCVDrnFwAKCRBR9baNJRVdyw19D/9yaOxzsZF/sVQVZuhT1jsQLTTJPTQnguiQYOVHr8o5dQ+Os6dUlrNX+PxxAINIS9iY/XmnxjUbG3GXiWrMIc6DK2GEpMj/T46IXcKCyixuWSukCcOAT/dBAFbsBuGVTV78a6qeZZp0NGhvrDuEYCVaYxx1aGLIssL/SvMMB2pR0eynxGK+P4so6zK2D6YIM3s5YyS3Kj9A19vzVQpEoGi4hpmG1dWP3zMdPLwSe6rwdEE4QEkkwITHXIxa5o9+IhaFxTDUFuLC6iMgaOQCLVAYM2mPZzloWac8yQIxF12nA+9b4zryftzVsfakJXO4jVBglygIHiRjGTmQI8pB8cwWRNSe4+PReVgWFi6sNUrPEEzw5CRTtdLq/TajWXTH12tqCFXJwtA6Vl7jbQYu5cG47b6hd3dfxHivSN0v1nCqyQh/qDNJ2iSWQGRVD1h+rKSTdX5L01fz7f6tRsX0mJ0pnfDNbmeJmPIZWQY5f0cXzHLYUb6n3I2Z1V159GDVKVjmxhWZdjlhpXtn+KDs6SwC0oqO3+ZH373ibMF+AjGvVowqw9rgI0fwdPRDommpFYXv5XKIUGg0V/Vx88IA+XP1EEYJi7O9g2uRjyN7SaigCbXpkjRpHjnyTty9H0whqWDltaj9N2hVGRUM9zkIUtuY5RCU0Y9xhZ6CwV5zUt9/fAo=";
		@unlink("/tmp/verifyfile.gpg");
		if (!file_put_contents("/tmp/verifyfile.php", base64_decode($file))) {
			$this->fail("Unable to save /tmp/verifyfile.php");
		}
		$f = self::$f;
		$this->assertTrue($f->GPG()->verifyFile("/tmp/verifyfile.php"));

		// Now, mangle it slightly.
		$file[46] = "S"; // Change 'This' to 'Uhis'
		@unlink("/tmp/verifyfile.gpg");
		if (!file_put_contents("/tmp/verifyfile.php", base64_decode($file))) {
			$this->fail("Unable to save /tmp/verifyfile.php");
		}
		$this->assertFalse($f->GPG()->verifyFile("/tmp/verifyfile.php"), "Invalid file validated");
	}

	public function testUntrustedFile() {
		$file = "owEBdwGI/pANAwACAeli+sssISJQActHYgBUOu2iVGhpcyBpcyBhIHZhbGlkLCBidXQgdW50cnVzdGVkIGZpbGUgZm9yIGNoZWNraW5nIEdQRzo6dmVyaWZ5RmlsZQqJARwEAAECAAYFAlQ67cAACgkQ6WL6yywhIlDaZAf+MPROYVwNHZDt+lyQNUJ1DwQvxCQql6yIeNPzCpLOHSRuqO1P3UBySAl03WUqm7WE8Acqg7ZUBE/jgOQ9Sdjv0odF9rtKidXY5c8C9A2gV+tJsYpBPTfs2o3OfOTTSMpRZj55JdhL8c2ziamSHdWK5pGexrDG8CtiLMShUGboK95IwRhrVBgW5/szl2vg/ZY4HX1hVqLS6OizQg+PTFqyzXgNFNAmxlWQ0mLCw4ZzQIPMwYJ/HOvJdX7beQzvyzoaSDnzQnkCQQPI1RILKjQ8q84YXUUh/lcgUTpgYL0Bg0j83+qOzrLRF0gX4H1utlu31eECGbQUG1d2FviDO8clmQ==";
		@unlink("/tmp/verifyfile.gpg");
		if (!file_put_contents("/tmp/verifyfile.php", base64_decode($file))) {
			$this->fail("Unable to save /tmp/verifyfile.php");
		}
		$f = self::$f;
		$this->assertFalse($f->GPG()->verifyFile("/tmp/verifyfile.php"), "Untrusted file is trusted");

		// Now, make it trusted by this machine
		$trust= array("AC402EA5C2717B8FF6E7D9A6551E31DCF65F39A6:6:", "2016349F5BC6F49340FCCAF99F9169F4B33B4659:6:", "0BDE0BFA09946D732091E26E1588A7366BD35B34:6");
		$fd = fopen("php://temp", "r+");
		$tr = join("\n", $trust)."\n" ;
		fwrite($fd, $tr);
		fseek($fd, 0);
		$out = $f->GPG()->runGPG("--import-ownertrust", $fd);
		if ($out['exitcode'] != 0) {
			print_r($out);
			$this->fail("Unable to locally trust the other key!");
		}
		$this->assertFalse($f->GPG()->verifyFile("/tmp/verifyfile.php"), "Locally trusted file is trusted, it shouldn't be.");

	}

	public function testLocalValidation() {
		$gpg = \FreePBX::GPG();

		$modulexml = base64_decode("PG1vZHVsZT4KCTxyYXduYW1lPmdwZ3Rlc3Q8L3Jhd25hbWU+Cgk8bmFtZT5Vbml0IFRlc3QgTW9kdWxlPC9uYW1lPgoJPHZlcnNpb24+MTMuMC4xPC92ZXJzaW9uPgoJPHB1Ymxpc2hlcj5TYW5nb21hIFRlY2hub2xvZ2llcyBDb3Jwb3JhdGlvbjwvcHVibGlzaGVyPgoJPGxpY2Vuc2U+QUdQTHYzKzwvbGljZW5zZT4KCTxsaWNlbnNlbGluaz5odHRwOi8vd3d3LmdudS5vcmcvbGljZW5zZXMvYWdwbC0zLjAudHh0PC9saWNlbnNlbGluaz4KCTxkZXNjcmlwdGlvbj5Vc2VkIG9ubHkgZm9yIFVuaXQgVGVzdGluZzwvZGVzY3JpcHRpb24+Cgk8Y2hhbmdlbG9nPioxMy4wLjEqIFJlbGVhc2U8L2NoYW5nZWxvZz4KCTxtZW51aXRlbXM+PGdwZ3Rlc3Q+RmlyZXdhbGw8L2dwZ3Rlc3Q+PC9tZW51aXRlbXM+Cgk8c3VwcG9ydGVkPjx2ZXJzaW9uPjEzLjA8L3ZlcnNpb24+PC9zdXBwb3J0ZWQ+CjwvbW9kdWxlPgo=");
		$finc = base64_decode("PD9waHAKLy8gdGhpcyBmaWxlIGRvZXMgbm90aGluZwpyZXR1cm4gZmFsc2U7Cg==");
		$root = "/var/www/html/admin/modules/gpgtest";
		`rm -rf $root`;
		@unlink("/etc/freepbx.secure/gpgtest.sig");
		mkdir($root);
		file_put_contents("$root/module.xml", $modulexml);
		file_put_contents("$root/functions.inc.php", $finc);

		// Various module.sig files
		$unsigned = gzuncompress(base64_decode("eJydUk1z2jAQvetXKJNLOy3ENvJXOj44YBsIeOwYktBMDpIlG8VGBn+Aya+vCXR6aC/NXqTV7tu3+7S9Xmd3jjfxYeAFMJp4vjOCcyeKbM85xXpgjKv1LYzGtgzAj+v/tA4BL+aWjAV3z3Be0CZnMOKpwHVTMujyzv1t15/jGBVQFDXEec1KWK8ZjAtRM1FXsEg6n1cw6ViuIJwkZ44/j7A7a7zZspJReOD1+vtHgc25zQPP8wsiwTyHe5xzimteCIgFhaRLxGXWIXEFufiIXn12DvDSdZ3w9BXsWVl1FJYC1p36VrXGiqqBqpOM0VOLFpJUw1A0TTds3XSH6BIjR+t06W/XW1CybWFtsGhwDkp8EHjDrHSb1qyqQX3cMmvbkJzH4OVEwapXkDQiPg1WneDQgmqMsWnGipwQrOIBlSRGVUKoyQxTxgM16XxkyirRJJ3pxJBkZCBNi3VNkk2iUnCWsN9u8q7agCWIUZ0SwyBU1lSDGURLKNUGKFExijVk6FjRTF2WqSbhmMYmolhhSKKJTFT59AOOoKD398bai+XDZVkfz8LdQk80gQf3Sl/qywh+8fzlzYyLpv0KAJ8eHDt0hra9cu18lUfz0B6mWRi6fPhTcCGMaLpLA9NdzSovJJm7coyqGZRqGXtLFbXgzTve8OLQTldOkSqRsvHfA7YW+uJOT1Fr5Hq0dx9yubLps1OjZZu4bVNk95Nj1EYkIE9gqr/76Ml73+TOOHNjKQsP99Wu3uW1F38bJdq0Sxr5AxaWM+Y/zH3XLHePQsyq1Cmni7cV8Bd2lqqr4GmYHe/8uSHCwgLWWLjJWR/HH/1LnV+kbBsi"));
		$signed = gzuncompress(base64_decode("eJydU8m2ozYQ3fMV9OlNcpz3DJixc7wAgw0eGIzx1KcXEgiQLQYz2OCvD+73crJINunaSKWrqlt1pXp7G0wzFpZNuwuX9q2Fbej0xvB9dWG8sDfKBHX6jfZNlaWoP7/+Txsi6E+bVwi52pHeFFFLEO3jJAdNWyF6jgf3b/v6axx6QedFQwPSoIpuUkSHRd6gvKnpIh58XNPxwPKFpq34g+OfQ3pYG5CVqEIR/cBN+sfPBNlHmQ9MyGdEDDCh74DgCDS4yGmQRzQcLoLqOkSCmsb5T/TLr/ZBfR+qjnHyg7qjqh4ophyVDupP6xRwgkjVg2QoepU4Fdi5oImyzgmsIOgz7ROD/fS1eS/TkqpQWUwzkLeAUBV45CBD06RMGlQ3VNOXaFq2kOCQ+v6iQPUPKm7z8NVY/Y7z8JWCntJCCICihBwbQyCAScQwKBIgjBQkKyyYCPHg8worQJGRkARlhuVlXhRDSWRYBQoR9SHje5eRIdsExTyKpAjKMoxYUZCRDMU4isQJHwuAD0VelgAnKhLLRiIDwihU+AhwiGeimIUC+3oFI4+ot3//WnUXbD8/7P5DvG/0Im/dBX3n3pl3lqd/W9jBeI3ztvudorBnhZpqaGqiLjRveVgespmqFktjbrJNwZD5frJu9MIdcwXXrgRVAbdtse5GaZss6o2qUZtj2dlaeb6O2AFlDM2SDhlhrw5YZlDk+EWRBHYN1+VT6O2+bAyF3aRYZYm6ZHoTXCnbAS0h50dKVi3UFen23G7llb4OknrLna18LK12mcKoj3N0XWnShb0gL/Bl7cS36Y3cV1TceMsJYNeCWp+a2wRXx/6CZ7hBfGV2MQHbw7X2yEyThf345OzIk+G7p3/f9+vzVT8edWq8D4gQBSdptwHczeuN8f4wI7Ml2s8T09QUrRdEdbxtH5cSL8KOiWCXqdDLVSkweei2lHme8ZV7Xx0IvlnAPekSmB3O+8XBGSUrfHd7eEPsg3Q+xMTxb+DCe/2xjcx43ey32zFPabITVgGL0hmYhfXD0Wc+a6lj4iELx9bWNLYwcff71lm5zGh1uUsJQVJd+SN86khfzSllJpx7ZhxG6fNZqZuVXU3yYx+NjaM4OimGzcLqWjo53lTc/O4Y8CCWwXUeS92t5ws1PlFNmV6SKjhcU8jsi+OukcKk6opwXhXqfIUXpv0s8lsEL1Z/ljlzLW28UNcgWiqlkTe7lupF9uobGPoP+Q4fQdD1cng/+tpVarCKrEc8dmUmK0DlWcX6JNlmGU5GT9/xt66ztrYJVd55qdTZ2y3QeeNY4drLJbteZgrgZa8xS4tj5t7tpCtHxR1JTS1Uu72Tb0h8dqJN5iZUpxG5KtV0lmvpLludbCHVqGnAVYuPOTFs/b+m5C+M4chs"));

		$localsecure = gzuncompress(base64_decode("eJydUstymzAU3esrlMmmndYOYJ7psIAYE6cxfhDbMZksJCRAMRYEgV9fXxy700W7ac5Gujq699x7pE6nhev5wwBO/AkMh37g9eHIC0PH905cB9wjkd3C8N6RAfhx/Z9oM+AFg4rSifsMRwVpcgpDlnJUNxWFA9aGv3H9OY1+AXlRQ5TXtIJ1RmFc8JryWsAiaWMmYNKqXEE4TM4afw5hu9ZoU9KKErhjdfb9o8Dm3OaO5fklI0Esh1uUM4JqVnCIOIG4vYiqdZuJBGT8g7367Bzgpe06Yekr2NJKtBK2ArLWfVtkSNF0IFrLKDm1aKuSZpqKrhumY1iDO/XC4YN92nTLrAQVLQt7g3iDclChHUcbaqdlWlNRg/pQUjsv4pZ6OSlQ8QqShsenuUSX8fhUAdpQixGyrFiRE4w01COSRImGMbGoacmopyVtrFqyhnXJoAY2JVk1VV2PDV2SLawRcHaxu9/kbbUeTVRKDIJNExNZ10xqYj0hRO+piYbUWFdNAym6Zcgy0SUUk9hSCVKoKpFExpp8egSPE9D5+9M6T/PZ5b8uzt7dQp83Ex9ula7UlVX4xQ/mN4+MN/uvALCHnedMvTvHWQ2cfJVHSuzcpevpdMDuIs44N8P+ejr5NpamaSXGkirezc2TNWrenfCngg3grpVy6bG5G1izkHvbanus6pCjfd8VmeiVRvOsHKPigcz8BW4Wbrb0ZDRbvg0CYj37mQXG/fJ4GAbHN+wQfbhcYS1/6HE/XtfNIZX0d+vejTyFaonYPw6LtIyWZmA2kTGIhHZTH/cgOLqHUZnSSHubL4zZWPq2soGNNsXk7I8X9P/lzi+vixtj"));
		$modsecure = gzuncompress(base64_decode("eJydUstymzAU3esrlMmmndYPMC+nwwI7gJ3ExDYktpPJQoAAxbLAILDp11eO3emi3TRno/s+955RpyMwst2pB+fuHPpT17Nv4cz2fcu1T7kOmKAqu4H+xJIA+HH9nxAd8AKnxHg+WsNZHtcUQ5+kDPG6xNAhwv2N689x3OaQ5RwiynEJeYZhlDOOGa9gngifVDARLFcQTpMzx58gFC9HuwKXOIYHwrPvHwN25zUPhNJLR4IIhQ2iJEac5AwiFsNQFKJyKzpRBQn7yF599g7wKrZOSPoGGlxWgsKUQSbUN6sMyaoGKiEZjk8rmkpfNQxZ03TD0ofOWLnkwtY8Gd0iK0CJi9ykeYQo4G2BL+braSCu3kBapBxXvCvqoQlRiA1Jj/VEDYfISFSkRNpADbVkYCiSgmRZiuJBrISKpsu6hOIwEXF1GA+URB7Kugw6f38kK3haXv7Q8/meG+iyeu7CRu72u5ICv7jeU++BsPr4FQByd7CthT22rI1j0Q19kSNrnG4XC4eMXxhhzFhmqTXv4R5a5a6GDO19Pw3KPgtWW/3FaQFV0ju9T1SvlB4SNzvaQU2dcE+dxA3qb/Lo57E/3tgsraPGdatq+Gi14WwfqdnaP8zSbwNQSOSeT+VBMm4X4VHymkheFV6zTb2A7lz/zp748W5tl/WuQZMHZRJy7ljLvvMetvrg3dkAun4eNe1qHuyz+2Vv2D7NFiYwe/rj7qyP7d3+S51f1s75KA=="));
		$modexpired = gzuncompress(base64_decode("eJydVEmXozYQvvMrmDeX5DndzSaWyfMBzGZsY2NsbJg3BwHCbBaYxZj+9cHTnZdDcsnURSqVqr6qT1V6eZlE0YylTe6MHekuDVtTyY3murKhPW0vhAnb9BvpmjJNEH9+/Z8yeZCfojcI7ZQzuanivkSkm10w7PoGkXo2qX/L11/DUCsSVx0Jyw41ZJciMqpwh3DXklUy6VlLJhPKF5JcJh8Y/xyS09rBa40aFJND1qV//Axw/UhzyMry0yOBWUneYZnFsMsqTEIck+F0ETbF5AlbMsM/rV9+tQ7i+5R1kl1+EHfUtBPEnCHSif15m0IG8EQ7UYbiZ4pzQOtA4UWVATQA6kL5tIXj/Ll5rdOaaFBdza8Q97AkGjhgeEXzS33pUNsR3Vijed2HZRYR6FFnDWphMlE3pxmWA8T3JypqfxBJj6Nnre1rhqNnVHJOgghCSYoYOgkhgGxMUSgGYRhLSJRoyIJk0jmJBiFPCUgIRYrmRI7nI4GnaCkEMfHB7OvjWk7RWJRwKBbiUBTDmOaBiMSQT+KYZ7kEQC7iOVGADC8JNB3zFIziSOJiyCCOihM6BPTzYTQcEy//bmT5cNx/9rD3wec30sD9ziDvzCv1yjDkb4Z9fFtnuH/8ThCZs4wUWVPki2wojgVD7qLLcmVpukl3FVXqHrvebTeObF3QSrDKzsIm61T+KYdUUN4wgSQ40MvRcz0L3Qt7jX0exEWuYjHxMtc2T6vdrfdZZKY4T1voDSvVocRiXaanOjX2N0J5V88DA3o/qE7bAghvKJdihU98dkTnNEf4zF83yypS/Fk+yrm+SxzPlHQnMtxNOYo88ba4a9rtXDojcjWlj31bD4/jkB90xzoJ15NLvc0gEH3lvlijgTe0HSdvvauI5FwsTlOfeMdhv67bB5iBwVWuTHA878ukAkc3Cg0Wz1z9jXlY5lbzeMYvGk49XVfHd/9yx16+agVA3DXh5ndArHRbNHG25YE7ioZGBShc10XR5tko3bxjweyYwnTVKPMfVHYzx2V6uA1ptCcoRvclrw8SwVlvoxl4uE0w0OIspGorcq1zciguFRds7mwZ3E90fDZjvR+jzbE8Fj53vRABncszh8vkcvpcxlJdzEz7Egb29BIQA8ESZuMOtN4uv7zdAve4oRoGm9vWXHRWHriHG4GGbD1KaN/3TY4pI47lfu/4YGc6tbdfbMJFDscISmC7HFfDfXlWFVcJTo+TvlnwvSoGRMhpXYs1XXSAtnaO7MDR9l5pbsy5x+IV3dfbLBgdyMqufSjF8SKwXt3479nKMmeI11RitTwYVdrQfSpsdNydC8fytsCTB2GzTEfbSjr1EAeDcpX6RTxNl/WWJNKiyTzKRh3VVwRGjzFtWoq+y+metg0R7BExX+422cecaLb6X1PyFw49zMI="));

		// Now put them in place and check their status.
		file_put_contents("$root/module.sig", $unsigned);
		$check = $gpg->verifyModule("gpgtest");
		$this->assertTrue(($check['status'] & FreePBX\GPG::STATE_TAMPERED) == FreePBX\GPG::STATE_TAMPERED, "Not Tampered");
		file_put_contents("$root/module.sig", $signed);
		$check = $gpg->verifyModule("gpgtest");
		$good = FreePBX\GPG::STATE_GOOD | FreePBX\GPG::STATE_TRUSTED;
		$this->assertTrue($check['status'] === $good, "Not trusted");
		file_put_contents("$root/module.sig", $modexpired);
		$check = $gpg->verifyModule("gpgtest");
		$this->assertTrue($check['status'] === FreePBX\GPG::STATE_REVOKED, "Not trusted");
		file_put_contents("$root/module.sig", $modsecure);
		try {
			$check = $gpg->verifyModule("gpgtest", true);
			$this->assertFalse($check['status'] === $good, "Local shouldn't be trusted, but it is");
			$this->fail("This shouldn't have been reached");
		} catch (\Exception $e) {
			// Passed
			$this->assertTrue(true, "threw when it should have");
		}
		file_put_contents("/etc/freepbx.secure/gpgtest.sig", $localsecure);
		$check = $gpg->verifyModule("gpgtest");
		$this->assertTrue($check['status'] === $good, "Local Not trusted when it should be");
		// Check tampered status
		file_put_contents("$root/functions.inc.php", "$finc\n\n");
		$check = $gpg->verifyModule("gpgtest", true);
		$this->assertTrue(($check['status'] & FreePBX\GPG::STATE_TAMPERED) == FreePBX\GPG::STATE_TAMPERED, "Not Tampered");
		file_put_contents("$root/module.sig", $signed);
		$check = $gpg->verifyModule("gpgtest");
		$this->assertTrue(($check['status'] & FreePBX\GPG::STATE_TAMPERED) == FreePBX\GPG::STATE_TAMPERED, "Not Tampered");
		unlink("/etc/freepbx.secure/gpgtest.sig");
	}
}
