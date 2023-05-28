<?php
//Namespace should be FreePBX\Console\Command
namespace FreePBX\Console\Command;

//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
//progress bar
use Symfony\Component\Console\Helper\ProgressBar;

class Restart extends Command {
	protected function configure(){
		$this->setName('restart')
			->setDescription(_('Start Asterisk and run other needed FreePBX commands'))
			->setDefinition(array(
				new InputOption('immediate', 'i', InputOption::VALUE_NONE, _('Shutdown NOW rather than convieniently')),
				new InputArgument('args', InputArgument::IS_ARRAY, null, null),));
	}

	protected function execute(InputInterface $input, OutputInterface $output){
		if(posix_geteuid() != 0) {
			$output->writeln("<error>"._("You need to be root to run this command")."</error>");
			exit(1);
		}
		$start = new Start();
		$stop = new Stop();
		$start->skipChown = true;

		$stop->execute($input, $output);
		$start->execute($input, $output);
	}
}
