<?php
namespace FreePBX\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

class Voicemail extends Command{
	protected function configure(){
		$this->setName('voicemail')
		->setDescription(_('Voicemail notification'))
		->addArgument(
			'notification',
			InputArgument::IS_ARRAY,
			'Arguments from voicemail');
		}

		protected function execute(InputInterface $input, OutputInterface $output){
			$options = $input->getArgument('notification');

			$context = $options[0];
			$extension = $options[1];
			$vmcount = isset($options[2]) ? $options[2] : 0;
			$oldvmcount = isset($options[3]) ? $options[3] : 0;
			$urgvmcount = isset($options[4]) ? $options[4] : 0;

			$this->notification($context,$extension,$vmcount,$oldvmcount,$urgvmcount);
		}
		public function notification($context,$extension,$vmcount,$oldvmcount,$urgvmcount){
			\FreePBX::Voicemail()->hookExtNotify($context,$extension,$vmcount,$oldvmcount,$urgvmcount);
		}
}
