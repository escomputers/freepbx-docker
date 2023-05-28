<?php

namespace FreePBX\modules\Pm2\utests;

require_once(__DIR__.'/mocks/ProcessMock.php');
require_once(__DIR__.'/../Pm2Apps.php');

use FreePBX\modules\pm2\Pm2Apps;
use FreePBX\modules\Pm2\utests\mocks\ProcessMock;

class Pm2AppsTest extends \PHPUnit_Framework_TestCase {

	public static function setUpBeforeClass() {
		exec('rm -rf /tmp/pm2test');
		mkdir('/tmp/pm2test');
		mkdir('/tmp/pm2test/homedir');
		mkdir('/tmp/pm2test/webuser');
		mkdir('/tmp/pm2test/varlibdir');
		mkdir('/tmp/pm2test/astlogdir');
	}

	public static function tearDownAfterClass() {
		exec('rm -rf /tmp/pm2test');
	}

	public function setUp() {
		ProcessMock::reset();
	}

	public function testRunPM2Command() {
		$testConfig = array(
			'homedir' => '/tmp/pm2test/homedir',
			'webuser' => 'asterisk',
			'varlibdir' => '/tmp/pm2test/varlibdir',
			'astlogdir' => '/tmp/pm2test/astlogdir',
			'disablelogs' => true,
			'useproxy' => false,
			'proxy' => false,
			'shell' => '/bin/bash',	
		);

		// Replace the Process Class with our ProcessMock Class
		// https://ericdraken.com/phpunit-mock-hard-dependencies-aliases/
		class_alias(
            'FreePBX\modules\Pm2\utests\mocks\ProcessMock',
            'Symfony\Component\Process\Process',
            true
        );

		$pm2Apps = new Pm2Apps($testConfig);
		$response = $pm2Apps->runPM2Command('testcommand');

		$this->assertTrue(ProcessMock::isInstantiated());
		$this->assertEquals(
			"runuser 'asterisk' -s '/bin/bash' -c 'cd /usr/src/freepbx/pm2/node && mkdir -p /tmp/pm2test/homedir/.pm2 && export HOME=/tmp/pm2test/homedir && export PM2_HOME=/tmp/pm2test/homedir/.pm2 && export ASTLOGDIR=/tmp/pm2test/astlogdir && export ASTVARLIBDIR=/tmp/pm2test/varlibdir && export PATH=\$HOME/.node/bin:\$PATH && export NODE_PATH=\$HOME/.node/lib/node_modules:\$NODE_PATH && export MANPATH=\$HOME/.node/share/man:\$MANPATH && /usr/src/freepbx/pm2/node/node_modules/pm2/bin/pm2 testcommand'", 
			ProcessMock::getCommand()
		);
		$this->assertEquals('command response goes here', $response);
		$this->assertEquals(240, ProcessMock::getIdleTimeout());
		$this->assertEquals(null, ProcessMock::getTimeout());
		$this->assertTrue(ProcessMock::getMustRun());
	}
}