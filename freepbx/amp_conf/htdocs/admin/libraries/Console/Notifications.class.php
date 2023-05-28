<?php
namespace FreePBX\Console\Command;
//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
//Ask stuff
use Symfony\Component\Console\Question\ChoiceQuestion;
//la mesa
use Symfony\Component\Console\Helper\Table;

class Notifications extends Command {
	protected function configure(){
		$this->setName('notifications')
		->setAliases(array('notification'))
		->setDescription(_('Manage notifications'))
		->setDefinition(array(
			new InputOption('list', null, InputOption::VALUE_NONE, _('list notifications')),
			new InputOption('json', null, InputOption::VALUE_NONE, _('format list as xml')),
			new InputOption('delete', null, InputOption::VALUE_NONE, _('Delete notification')),
			new InputArgument('args', InputArgument::IS_ARRAY, null, null),))
		->setHelp($this->showHelp());
	}

	protected function execute(InputInterface $input, OutputInterface $output){
		$args = $input->getArgument('args');
		$nt = \notifications::create();
		if($input->getOption('list')){
			$notifications = array();
			foreach($nt->list_all() as $notif){
				$notifications[] = array($notif['module'], $notif['id'], $notif['display_text']);
			}
			if($input->getOption('json')){
				$output->writeln(json_encode($notifications));
			}else{
				$table = new Table($output);
				$table
					->setHeaders(array('Module', 'ID', 'Text'))
					->setRows($notifications);
					$table->render();
			}
		}

		if($input->getOption('delete')){
			if(!isset($args[1])){
				$output->writeln("Usage: fwconsole notifications --delete module id");
			}
			if($nt->exists($args[0], $args[1])){
				$output->writeln("Deleting notification");
				$nt->delete($args[0], $args[1]);
				if(!$nt->exists($args[0], $args[1])){
						$output->writeln("Notification Deleted");
				}else{
						$output->writeln("<error>Notification did not delete</error>");
				}
			}else{
				$output->writeln("<error>Specified notification does not exist</error>");
			}
		}

		//show the help text when no options are included
		$options = $input->getOptions();
		foreach($options as $key => $val) {
			if (empty($val)) {
				unset($options[$key]);
			}
		}
		if (empty($options)) {
			$output->writeln($this->showHelp());
		}
	}

	private function showHelp(){
		$help = '<info>'._('Notifications Help').':'.PHP_EOL;
		$help .= _('Usage').': fwconsole notifications [--list]' . PHP_EOL;
		$help .= _('Usage').': fwconsole notifications [--delete module id]</info>' . PHP_EOL;

		return $help;
	}
}
