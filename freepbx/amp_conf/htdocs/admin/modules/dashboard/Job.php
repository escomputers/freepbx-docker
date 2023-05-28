<?php

namespace FreePBX\modules\Dashboard;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
class Job implements \FreePBX\Job\TaskInterface {
	public static function run(InputInterface $input, OutputInterface $output) {
		$astrundir = \FreePBX::Config()->get('ASTRUNDIR');
		if(!is_dir($astrundir) || !is_writable($astrundir)) {
			$output->writeln("Asterisk Run Dir [".$astrundir."] is missing or not writable! Is Asterisk running?");
			return false;
		}

		if(!\FreePBX::create()->astman->connected()){
			return false;
		}
		// Run the trigger
		\FreePBX::Dashboard()->runTrigger();
		return true;
	}
}