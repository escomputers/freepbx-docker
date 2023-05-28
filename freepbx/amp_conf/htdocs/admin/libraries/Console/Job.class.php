<?php
namespace FreePBX\Console\Command;
//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Console\Command\HelpCommand;
class Job extends Command {
	private $output;
	private $input;
	private $force = false;
	private $fwjobsLogEnabled=false;
	private $fwjobslogfd=null;

	use LockableTrait;


	protected function configure() {
		$this->setName('job')
		->setDescription(_('Centralized job management'))
		->setDefinition(array(
			new InputOption('enable', '', InputOption::VALUE_REQUIRED, _('Enable a specific job')),
			new InputOption('disable', '', InputOption::VALUE_REQUIRED, _('Disable a specific job')),
			new InputOption('run', '', InputOption::VALUE_OPTIONAL, _('Run all jobs, or optionally run a single job if job id is appended')),
			new InputOption('list', '', InputOption::VALUE_NONE, _('List known jobs')),
			new InputOption('force', '', InputOption::VALUE_NONE, _('Force run even if disabled or not the right time')),
		));
	}

	protected function execute(InputInterface $input, OutputInterface $output){
		$this->output = $output;
		$this->input = $input;

		if (!$this->lock()) {
			$output->writeln('The command is already running in another process.');
			return 0;
		}

		if($input->getOption('force')) {
			$this->force = true;
		}

		if($input->getOption('enable')) {
			$this->enableJob($input->getOption('enable'), true);
			return;
		}

		if($input->getOption('disable')) {
			$this->enableJob($input->getOption('disable'), false);
			return;
		}

		if($input->getOption('disable')) {
			$this->runJobs($this->registerTasks($this->findAllJobs([$input->getOption('run')])));
			return;
		}

		if($input->hasParameterOption('--run') && is_null($input->getOption('run'))) {
			//run all
			$this->runJobs($this->registerTasks($this->findAllJobs()));
			return;
		}

		if($input->getOption('run')) {
			$this->runJobs($this->registerTasks($this->findAllJobs([$input->getOption('run')])));
			return;
		}

		if($input->getOption('list')) {
			$table = new Table($output);
			$table->setHeaders(array(_('ID'),_('Module'),_('Job'),_('Cron'),_('Next Run'),_('Action'),_("Enabled")));
			$rows = array();
			foreach(\FreePBX::Job()->getAll() as $job) {
				$rows[] = array(
					$job['id'],
					$job['modulename'],
					$job['jobname'],
					$job['schedule'],
					\Cron\CronExpression::factory($job['schedule'])->getNextRunDate()->format('Y-m-d H:i:s'),
					(!empty($job['command']) ? _('Command').': '.$job['command'] : _('Class').': '.$job['class']),
					!empty($job['enabled']) ? 'Yes' : 'No'
				);
			}
			$table->setRows($rows);
			$table->render();
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

	/**
	 * Toggle job enabled
	 *
	 * @param string $id The Job ID
	 * @param boolean $enabled to enable or not
	 * @return void
	 */
	private function enableJob($id, $enabled) {
		$jobs = $this->findAllJobs([$id]);
		if(empty($jobs)) {
			$this->output->writeln('No jobs found');
			return;
		}
		foreach($jobs as $job) {
			\FreePBX::Job()->setEnabled($job['modulename'], $job['jobname'], $enabled);
			if($enabled) {
				$this->output->writeln('Enabled job '.$job['modulename'].'::'.$job['jobname']);
			} else {
				$this->output->writeln('Disabled job '.$job['modulename'].'::'.$job['jobname']);
			}
		}
	}

	/**
	 * Run Jobs
	 *
	 * @param array $jobs
	 * @return void
	 */
	private function runJobs($jobs=[]) {
		$this->freePBX = \FreePBX::Create();
		$this->fwjobsLogEnabled  = $this->freePBX->Config->get('FWJOBS_LOGS');
		$this->fwjobslogfd = null ;
		if ($this->fwjobsLogEnabled) {
			$astLogdir = $this->freePBX->Config->get('ASTLOGDIR');
			$fwjobsLogfile = $astLogdir.'/fwjobs.log';
			$this->fwjobslogfd = fopen($fwjobsLogfile,'a+');
		}
		$time = new \DateTimeImmutable("now");
		foreach($jobs as $config) {
			if (!$this->force && !\Cron\CronExpression::factory($config['schedule'])->isDue($time)) {
				if ($this->output->isVerbose() || !empty($this->input->getOption('run'))) {
					$msg = sprintf(_('Skipping %s::%s because schedule does not match'), $config['module'], $config['job']);
					$this->writelog($msg);
				}
				continue;
			}
		$msg = sprintf(_('Running %s :: %s ...'),$config['module'],$config['job']);
		$this->writelog($msg);
			try {
				switch($config['type']) {
					case 'command':
						$process = new Process($config['command']);
						$config['max_runtime'] = ($config['max_runtime'] == 0) ? null : $config['max_runtime'];
						$process->setTimeout($config['max_runtime']);
						$process->run(function ($type, $buffer) {
							if (Process::ERR === $type) {
								$msg = '<error>'.$buffer.'</error>';
								$this->writelog($msg);
							} else {
								$this->writelog($buffer);
							}
						});
					break;
					case 'class':
						if($config['closure']($this->input, $this->output) !== true) {
							$msg =  sprintf(_("Error: Command[%s] did not return true!"),$config['command']);
							$this->writelog($msg);
							throw new \Exception($msg);
						};
					break;
				}
				$msg = sprintf(_("Done with %s"),$config['job']);
				$this->writelog($msg);
			} catch(\Exception $e) {
				$msg = "<error> ". sprintf(_("Error in the task  %s Exception = %s"),$config['job'],$e->getMessage())." </error>";
				$this->writelog($msg);
			}
		}
		if ($this->fwjobslogfd != null) {
			fclose($this->fwjobslogfd);
		}
	}

	private function writelog($msg) {
		$this->output->writeln($msg);
		if (($this->fwjobslogfd != null) && 
				($this->fwjobsLogEnabled == true)) {
			$msg = "\n" . date('d/m/Y H:i:s') .' '.$msg."\n";
			fwrite($this->fwjobslogfd, $msg);
		}
	}

	/**
	 * Register tasks
	 *
	 * @param array $jobs
	 * @return array
	 */
	private function registerTasks($jobs = []) {
		$logdir = \FreePBX::Config()->get('ASTLOGDIR');
		$tasks = [];
		foreach($jobs as $task ) {
			$task_config = [
				'output'   => $logdir.'/jobs.log',
				'schedule' => $task['schedule'],
				'module' => $task['modulename'],
				'job' => $task['jobname'],
				'type' => $task['type'],
				'max_runtime' => $task['max_runtime']
			];
			switch($task['type']) {
				case 'command':
					$task_config['command'] = $task['command'];
				break;
				case 'class':
					$class = $task['class'];
					$task_config['closure'] = function() use ($class) {
						return call_user_func_array( $class.'::run', func_get_args());
					};
				break;
			}

			if(!empty($task_config)) {
				$tasks[] = $task_config;
			}
		}
		return $tasks;
	}

	/**
	 * Find all jobs to run
	 *
	 * @param array $filterIds Filter enabled jobs
	 * @return void
	 */
	private function findAllJobs($filterIds=[]) {
		$jobs = [];
		foreach(\FreePBX::Job()->getAll() as $job) {
			if(!empty($filterIds) && !in_array($job['id'], $filterIds)) {
				continue;
			}

			if(!$this->force && empty($filterIds) && empty($job['enabled'])) {
				continue;
			}

			if(empty(!$job['command']) && !empty($job['class'])) {
				$this->output->writeln("<error>Both class and command are defined</error>");
				continue;
			}

			if(!empty($job['class'])) {
				// Before we include this job in the job list, we need to make sure it implements \Crony\TaskInterface
				$implementations = class_implements( $job['class'] );
				if ( in_array( 'FreePBX\Job\TaskInterface', $implementations ) ) {
					$job['type'] = 'class';
					$jobs[] = $job;
				}
			}

			if(empty(!$job['command'])) {
				$job['type'] = 'command';
				$jobs[] = $job;
			}
		}

		return $jobs;
	}
}
