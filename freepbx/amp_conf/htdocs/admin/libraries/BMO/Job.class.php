<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * This is the Job Handler for the FreePBX Big Module Object.
 *
 * See: https://wiki.freepbx.org/display/FOP/Job
 *
 */
namespace FreePBX;

class Job {
	private $db;
	private $freepbx;
	private $inited = false;

	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new Exception("Not given a FreePBX Object");
		}
		$this->freepbx = $freepbx;
		$this->db = $this->freepbx->Database;
	}

	/**
	 * Get all Jobs
	 *
	 * @return array
	 */
	public function getAll() {
		return $this->db->query("SELECT * FROM cron_jobs ORDER by execution_order ASC")->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Get all enabled jobs
	 *
	 * @return array
	 */
	public function getAllEnabled() {
		return $this->db->query("SELECT * FROM cron_jobs WHERE `enabled` = 1 ORDER by execution_order ASC")->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Add Job Command
	 *
	 * Add a job that will launch a command
	 *
	 * @param string $modulename The module rawname (used for uninstalling)
	 * @param string $jobname The job name
	 * @param string $command The command to run
	 * @param string $schedule The Cron Expression when to run
	 * @param integer $max_runtime The max run time in seconds
	 * @param boolean $enabled Whether this job is enabled or not
	 * @return void
	 */
	public function addCommand($modulename, $jobname, $command, $schedule, $max_runtime = 30, $enabled = true, $execution_order = 100) {
		return $this->add($modulename, $jobname, $command, null, $schedule, $max_runtime, $enabled, $execution_order);
	}

	/**
	 * Add Job Class
	 *
	 * @param string $modulename The module rawname (used for uninstalling)
	 * @param string $jobname The job name
	 * @param string $class The class to run. Must implement https://github.com/FreePBX/framework/blob/feature/newcrons/amp_conf/htdocs/admin/libraries/BMO/Job/Job.php
	 * @param string $schedule The Cron Expression when to run
	 * @param integer $max_runtime The max run time in seconds
	 * @param boolean $enabled Whether this job is enabled or not
	 * @return void
	 */
	public function addClass($modulename, $jobname, $class, $schedule, $max_runtime = 30, $enabled = true, $execution_order = 100) {
		return $this->add($modulename, $jobname, null, $class, $schedule, $max_runtime, $enabled, $execution_order);
	}

	/**
	 * Add a Job
	 *
	 * If the job already exists update everything *except* enabled value!
	 *
	 * @param string $modulename The module rawname (used for uninstalling)
	 * @param string $jobname The job name
	 * @param string $command The command to run
	 * @param string $class The class to run. Must implement https://github.com/FreePBX/framework/blob/feature/newcrons/amp_conf/htdocs/admin/libraries/BMO/Job/Job.php
	 * @param string $schedule The Cron Expression when to run
	 * @param integer $max_runtime The max run time in seconds
	 * @param boolean $enabled Whether this job is enabled or not
	 * @return void
	 */
	public function add($modulename, $jobname, $command, $class, $schedule, $max_runtime = 30, $enabled = true, $execution_order = 100) {
		$this->init();
		if(!\Cron\CronExpression::isValidExpression($schedule)) {
			throw new \Exception("$schedule is not a valid Cron Expression!");
		}
		$sth = $this->db->prepare("SELECT COUNT(*) as count FROM cron_jobs WHERE modulename = :modulename AND jobname = :jobname");
		$sth->execute([
			':modulename' => $modulename,
			':jobname' => $jobname
		]);
		$count = $sth->fetch(\PDO::FETCH_COLUMN);

		$variables = [
			':modulename' => $modulename,
			':jobname' => $jobname,
			':command' => $command,
			':class' => $class,
			':schedule' => $schedule,
			':max_runtime' => $max_runtime,
			':execution_order' => $execution_order
		];
		if(!$count) {
			$sth = $this->db->prepare("INSERT INTO cron_jobs (`modulename`, `jobname`, `command`, `class`, `schedule`, `max_runtime`, `enabled`, `execution_order`) VALUES (:modulename,:jobname,:command,:class,:schedule,:max_runtime,:enabled,:execution_order)");
			$variables[':enabled'] = ($enabled ? 1 : 0);
		} else {
			$sth = $this->db->prepare("UPDATE cron_jobs SET `command` = :command, `class` = :class, `schedule` = :schedule, `max_runtime` = :max_runtime, `execution_order`= :execution_order WHERE modulename = :modulename AND jobname = :jobname");
		}
		return $sth->execute($variables);
	}

	/**
	 * Remove a job by modulename and jobname
	 *
	 * @param string $modulename The module rawname (used for uninstalling)
	 * @param string $jobname The job name
	 * @return void
	 */
	public function remove($modulename, $jobname) {
		$sth = $this->db->prepare("DELETE FROM cron_jobs WHERE `modulename` = :modulename AND `jobname` = :jobname");
		return $sth->execute([
			':modulename' => $modulename,
			':jobname' => $jobname
		]);
	}

	/**
	 * Update a job
	 *
	 * @param string $modulename The module rawname (used for uninstalling)
	 * @param string $jobname The job name
	 * @param string $command The command to run
	 * @param string $class The class to run. Must implement https://github.com/FreePBX/framework/blob/feature/newcrons/amp_conf/htdocs/admin/libraries/BMO/Job/Job.php
	 * @param string $schedule The Cron Expression when to run
	 * @param integer $max_runtime The max run time in seconds
	 * @param boolean $enabled Whether this job is enabled or not
	 * @return void
	 */
	public function update($modulename, $jobname, $command, $class, $schedule, $max_runtime = 30, $enabled = true,$execution_order = 100) {
		$this->init();
		return $this->add($modulename, $jobname, $command, $class, $schedule, $max_runtime, $enabled,$execution_order);
	}

	/**
	 * Remove all jobs
	 *
	 * @return void
	 */
	public function removeAll() {
		return $this->db->query("DELETE FROM cron_jobs");
	}

	/**
	 * Remove all jobs by module name
	 *
	 * @param string $modulename The module rawname (used for uninstalling)
	 * @return void
	 */
	public function removeAllByModule($modulename) {
		$sth = $this->db->prepare("DELETE FROM cron_jobs WHERE `modulename` = :modulename");
		return $sth->execute([
			':modulename' => $modulename
		]);
	}

	/**
	 * Toggle Enabled on a job
	 *
	 * @param string $modulename The module rawname (used for uninstalling)
	 * @param string $jobname The job name
	 * @param boolean $enabled Whether this job is enabled or not
	 * @return void
	 */
	public function setEnabled($modulename, $jobname, $enabled = true) {
		$this->init();
		$sth = $this->db->prepare("UPDATE cron_jobs SET `enabled` = :enabled WHERE `modulename` = :modulename AND `jobname` = :jobname");
		return $sth->execute([
			':modulename' => $modulename,
			':jobname' => $jobname,
			':enabled' => ($enabled ? 1 : 0)
		]);
	}

	/**
	 * Set Enabled by Module Rawname
	 *
	 * @param string $modulename
	 * @param boolean $enabled
	 * @return void
	 */
	public function setEnabledByModule($modulename, $enabled = true) {
		$this->init();
		$sth = $this->db->prepare("UPDATE cron_jobs SET `enabled` = :enabled WHERE `modulename` = :modulename");
		return $sth->execute([
			':modulename' => $modulename,
			':enabled' => ($enabled ? 1 : 0)
		]);
	}

	/**
	 * Update schedule of a job
	 *
	 * @param string $modulename The module rawname (used for uninstalling)
	 * @param string $jobname The job name
	 * @param string $schedule The Cron Expression when to run
	 * @return void
	 */
	public function updateSchedule($modulename, $jobname, $schedule) {
		$this->init();
		$sth = $this->db->prepare("UPDATE cron_jobs SET `schedule` = :schedule WHERE `modulename` = :modulename AND `jobname` = :jobname");
		return $sth->execute([
			':modulename' => $modulename,
			':jobname' => $jobname,
			':schedule' => $schedule
		]);
	}

	/**
	 * Initialize the crontab to run the jobs
	 *
	 * @return void
	 */
	public function init() {
		if($this->inited) {
			return;
		}
		$crons = $this->freepbx->Cron->getAll();
		foreach($crons as $c) {
			if(preg_match('/fwconsole job --run/',$c,$matches)) {
				$this->freepbx->Cron->remove($c);
			}
		}

		$ampbin = $this->freepbx->Config->get('AMPSBIN');
		$sleeptime = $this->freepbx->Config->get_conf_setting('JOBSRANDOMSLEEP');
		if(isset($sleeptime) && $sleeptime > 0 ) {
		       //we need to set the Random sleep time
			$this->freepbx->Cron->add('* * * * * [ -e '.$ampbin.'/fwconsole ] && sleep $((RANDOM\%'.$sleeptime.')) && '.$ampbin.'/fwconsole job --run --quiet 2>&1 > /dev/null');
		}
		else {
			$this->freepbx->Cron->add('* * * * * [ -e '.$ampbin.'/fwconsole ] && '.$ampbin.'/fwconsole job --run --quiet 2>&1 > /dev/null');
		}
		$this->inited = true;
	}
}
