<?php
namespace FreePBX\Console\Command;
//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class Extip extends Command {
	protected function configure(){
		$this->setName('extip')
		->setAliases(array('externalip'))
		->setDescription(_('Get External IP'))
		->setDefinition(array(
			new InputArgument('args', InputArgument::IS_ARRAY, null, null),));
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$extipsrc = 'http://mirror.freepbx.org/whatismyip.php';
		$extip = file_get_contents($extipsrc);
		$xml = simplexml_load_string($extip);
		if($xml){
			if(filter_var($xml->ipaddress,FILTER_VALIDATE_IP)){
				$output->writeln($xml->ipaddress);
			}else{
				$output->writeln(_('We received data but it was not a valid IP address'));
			}
		}else{
			$output->writeln(_('We were unable to obtain a valid IP Data'));
		}
	}
}
