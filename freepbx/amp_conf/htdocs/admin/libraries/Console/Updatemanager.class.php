<?php
namespace FreePBX\Console\Command;
//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

//la mesa
use Symfony\Component\Console\Helper\Table;

use Symfony\Component\Console\Command\HelpCommand;

class UpdateManager extends Command {
	protected function configure(){
		$this->FreePBXConf = \FreePBX::Config();
		$this->setName('updatemanager')
		->setAliases(array('msm','modulesystemmanager'))
		->setDescription(_('View and change Update/Notification Manager Settings'))
		->setDefinition(array(
			new InputOption('list', 'l', InputOption::VALUE_NONE, _('List Configs')),
			new InputArgument('args', InputArgument::IS_ARRAY, null, null),));
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$this->um = new \FreePBX\Builtin\UpdateManager();
		$this->su = new \FreePBX\Builtin\SystemUpdates();
		$this->settings = $this->um->getCurrentUpdateSettings(false);

		if ($input->getOption('list')){
			$table = new Table($output);
			$table->setHeaders(array(_('Name'),_('Value')));
			$rows = array();
			foreach ($this->settings as $key => $val){
				if($key === 'auto_system_updates' && !$this->su->canDoSystemUpdates()) {
					$rows[] = array(
						$key,
						_('Not Supported'),
					);
				} else {
					$rows[] = array(
						$key,
						$val,
					);
				}

			}
			$table->setRows($rows);
			$table->render();
			return;
		}

		$args = $input->getArgument('args');
		if(isset($args[0])) {
			$setting = trim($args[0]);
			if(isset($this->settings[$setting])){
				if(!isset($args[1])) {
					$output->writeln(sprintf(_('Setting of "%s" is "%s"'),$setting,$this->settings[$setting]));
				} else {
					$this->changeConfSetting($setting, $args[1], $input, $output);
				}
			}else{
				$output->writeln(sprintf(_('The setting %s was not found!'),$setting));
			}
			return;
		}

		$this->outputHelp($input,$output);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 * @throws \Symfony\Component\Console\Exception\ExceptionInterface
	 */
	protected function outputHelp(InputInterface $input, OutputInterface $output)	 {
		$help = new HelpCommand();
		$help->setCommand($this);
		return $help->run($input, $output);
	}

	private function changeConfSetting($setting, $value, InputInterface $input, OutputInterface $output) {
		switch($setting) {
			case "notification_emails":
				if(!filter_var($value,FILTER_VALIDATE_EMAIL)) {
					$output->writeln("<error>".sprintf(_('Error: "%s" must be a valid email address')."</error>",$setting));
					return;
				}
			break;
			case "system_ident":
				if(empty($value)) {
					$output->writeln("<error>".sprintf(_('Error: "%s" must not be blank/empty')."</error>",$setting));
					return;
				}
			break;
			case "auto_system_updates":
				if(!$this->su->canDoSystemUpdates()) {
					$output->writeln("<error>".sprintf(_('Error: "%s" is not supported on this system')."</error>",$setting));
					return;
				}
			case "auto_module_updates":
				$valid = [
					"enabled",
					"emailonly",
					"disabled"
				];
				if(!in_array($value,$valid)) {
					$output->writeln("<error>".sprintf(_('Error: "%s" must be one of "%s"')."</error>",$setting,implode(",",$valid)));
					return;
				}
			break;
			case "auto_module_security_updates":
				$valid = [
					"enabled",
					"emailonly",
				];
				if(!in_array($value,$valid)) {
					$output->writeln("<error>".sprintf(_('Error: "%s" must be one of "%s"')."</error>",$setting,implode(",",$valid)));
					return;
				}
			break;
			case "unsigned_module_emails":
				$valid = [
					"enabled",
					"disabled"
				];
				if(!in_array($value,$valid)) {
					$output->writeln("<error>".sprintf(_('Error: "%s" must be one of "%s"')."</error>",$setting,implode(",",$valid)));
					return;
				}
			break;
			case "update_every":
				$valid = [
					"day",
					"sunday",
					"monday",
					"tuesday",
					"wednesday",
					"thursday",
					"friday",
					"saturday"
				];
				if(!in_array($value,$valid)) {
					$output->writeln("<error>".sprintf(_('Error: "%s" must be one of "%s"')."</error>",$setting,implode(",",$valid)));
					return;
				}
			break;
			case "update_period":
				$valid = [
					"0to4" ,
					"4to8",
					"8to12",
					"12to16",
					"16to20",
					"20to0"
				];
				if(!in_array($value,$valid)) {
					$output->writeln("<error>".sprintf(_('Error: "%s" must be one of "%s"')."</error>",$setting,implode(",",$valid)));
					return;
				}
			break;
		}

		$output->writeln(sprintf(_('Changing "%s" from [%s] to [%s]'),$setting,$this->settings[$setting],$value));
		$this->um->updateUpdateSettings([
			$setting => $value
		]);
	}
}
