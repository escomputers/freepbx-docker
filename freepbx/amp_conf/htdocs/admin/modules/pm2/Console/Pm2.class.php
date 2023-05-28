<?php
namespace FreePBX\Console\Command;
//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
//la mesa
use Symfony\Component\Console\Helper\Table;

use Symfony\Component\Process\Process;

use Symfony\Component\Console\Command\HelpCommand;

class Pm2 extends Command {
	protected function configure(){
		$this->setName('pm2')
		->setDescription(_('Manage long running processes'))
		->setDefinition(array(
			new InputOption('list', null, InputOption::VALUE_NONE, _('List Process')),
			new InputOption('stop', null, InputOption::VALUE_REQUIRED, _('Stop Process')),
			new InputOption('restart', null, InputOption::VALUE_REQUIRED, _('Restart Process')),
			new InputOption('delete', null, InputOption::VALUE_REQUIRED, _('Delete Process')),
			new InputOption('update', null, InputOption::VALUE_NONE, _('Save processes, kill PM2 and restore processes')),
			new InputOption('reload-logs', null, InputOption::VALUE_NONE, _('Reload all log file pointers')),
			new InputOption('log', null, InputOption::VALUE_REQUIRED, _('Stream Logs from Process')),
			new InputOption('lines', null, InputOption::VALUE_REQUIRED, _('How many lines to stream'))
		));
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		if($input->getOption('list')){
			$data = \FreePBX::Pm2()->listProcesses();
			$table = new Table($output);
			$table->setHeaders(array(_('Process Name'),'PID',_('Status'),_('Restarts'),_("Uptime"), _("CPU"),_("Mem")));
			$rows = array();
			foreach($data as $process) {
				$rows[] = array(
					$process['name'],
					$process['pid'],
					$process['pm2_env']['status'],
					$process['pm2_env']['restart_time'],
					$process['pm2_env']['created_at_human_diff'],
					$process['monit']['cpu'].'%',
					$process['monit']['human_memory'],
				);
			}
			$table->setRows($rows);
			$table->render();
			return;
		}
		if($input->getOption('log')){
			$app = $input->getOption('log');
			$lines = 10;
			if ($input->hasParameterOption('--lines')) {
				$lines = $input->getOption('lines');
			}
			$status = \FreePBX::Pm2()->getStatus($app);
			$logs = array(
				$status['pm2_env']['pm_err_log_path'],
				$status['pm2_env']['pm_out_log_path']
			);
			$files = implode(' ', $logs);
			//passthru('tail -f ' . $files);
			$process = new Process('tail --lines='.$lines.' -f ' . $files);
			//Timeout for the above process. Not sure if there is a no limit but 42 Years seems long enough.
			$process->setTimeout(1325390892);
			$process->run(function ($type, $buffer) {
				if (Process::ERR === $type) {
					echo 'ERR > '.$buffer;
				} else {
					echo 'OUT > '.$buffer;
				}
			});
			return;
		}
		if($input->getOption('restart')){
			$app = $input->getOption('restart');
			\FreePBX::Pm2()->restart($app);
			$output->writeln("Process Restarted");
			return;
		}
		if($input->getOption('stop')){
			$app = $input->getOption('stop');
			\FreePBX::Pm2()->stop($app);
			$output->writeln("Process Stopped");
			return;
		}
		if($input->getOption('update')){
			$app = $input->getOption('update');
			\FreePBX::Pm2()->update();
			$output->writeln("Update PM2 Process");
			return;
		}
		if($input->getOption('delete')){
			$app = $input->getOption('delete');
			\FreePBX::Pm2()->delete($app);
			$output->writeln("Process Stopped and Deleted");
			return;
		}
		if($input->getOption('reload-logs')){
			\FreePBX::Pm2()->reloadLogs();
			$output->writeln("All logs reloaded");
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
}
