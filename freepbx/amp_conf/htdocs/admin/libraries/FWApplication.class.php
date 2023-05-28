<?php
namespace FreePBX\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\ListCommand;

class FWApplication extends Application{
	protected function getDefaultCommands() {
		$defaultCommands = array(new FWHelpCommand(), new FWListCommand());
		return $defaultCommands;
	}

}
