<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * This is the Cron Handler for the FreePBX Big Module Object.
 * Cron Class. Adds and removes entries to Crontab.
 *
 * If run as root, can manage any user:
 *   $cron = new Cron('username');
 *
 * Otherwise manages current user.
 *
 * $cron->add("@monthly /bin/true");
 * $cron->remove("@monthly /bin/true");
 * $cron->add(array("magic" => "@monthly", "command" => "/bin/true"));
 * $cron->add(array("hour" => "1", "command" => "/bin/true"));
 * $cron->removeAll("/bin/true");
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
namespace FreePBX;
use Symfony\Component\Lock\Factory;
use Symfony\Component\Lock\Store\SemaphoreStore;
class Cron {

	private $user;
	private $uoption = "";
	private $lock;

	/**
	 * Constructor for Cron Tab Manager Class
	 *
	 * This allows either Cron($freepbxobject, $username) or Cron($username),
	 * which is why it looks slightly confusing below.
	 *
	 * @param  {mixed} $var1 = 'asterisk' Can either be a FreePBX object or just a username to manage crons for
	 * @param  {string} $var2 = 'asterisk' Username to manage crons for
	 */
	public function __construct($var1 = false, $var2 = false) {

		// Lets figure out if we were given a FreePBX Object, or a user.
		if (is_object($var1)) {
			$this->freepbx = $var1;
			$user = $var2;
		} else {
			$user = $var1;
		}

		// Were we given a user?
		if (!$user) {
			// No. Default to AMPASTERISKWEBUSER (probably 'asterisk', but may be something else)
			$user = \FreePBX::Config()->get('AMPASTERISKWEBUSER');
		}

		$this->user = $user;

		// If we're not root, we can only edit our own cron.
		if (posix_geteuid() != 0) {
			$userArray = posix_getpwuid(posix_geteuid());
			if ($userArray['name'] != $user)
				throw new \Exception("Trying to edit user $user, when I'm running as ".$userArray['name']);
		} else {
			$this->uoption = "-u ".escapeshellarg($this->user)." ";
		}
	}

	/**
	 * Returns an array of all the lines for the user
	 * @return array Crontab lines for user
	 */
	public function getAll() {
		exec('/usr/bin/crontab '.$this->uoption.' -l 2>/dev/null', $output, $ret);
		// If it's empty
		if (!$output) {
			return array();
		}
		// Linux output
		if (preg_match('/^no crontab for/', $output[0]))
			return array();
		// FreeBSD output
		if (preg_match('/^crontab: no crontab for/', $output[0]))
			return array();

		// Sanity: If any line has a null in it, it's broken. Remove
		// that line.
		return array_filter($output, function($line) {
			return (strpos($line, "\x00") === false);
		});
	}

	/**
	 * Checks if the line exists exactly as is in this users crontab
	 * @param {string} $line Line to check
	 * @return {bool} True or false if the line exists
	 */
	public function checkLine($line = null) {
		if ($line == null) {
			throw new \Exception("Null handed to checkLine");
		}

		$allLines = $this->getAll();
		return in_array($line, $allLines);
	}

	/**
	 * Add the line given to this users crontab
	 * @param {string} $line The line to add
	 * @return {bool} Return true if the line was added
	 */
	public function addLine($line) {
		$this->getLock()->acquire(true);
		try {
			$line = trim($line);
			$backup = $this->getAll();
			$newCrontab = $backup;

			if (!$this->checkLine($line)) {
				$newCrontab[] = $line;
				$this->installCrontab($newCrontab);
				if ($this->checkLine($line))
					return true;
				// It didn't stick. WTF? Put our original one back.
				$this->installCrontab($backup);
				throw new \Exception("Cron line added didn't remain in crontab on final check. Check /tmp/cron.error for reason.");
			} else {
				// It was already there.
				return true;
			}
		} finally {
			$this->getLock()->release();
		}

	}

	/**
	 * Alias of the function below, removing a line
	 * @param {string} $line The line to remove
	 * @return {bool} True, if removed, false if not found
	 */
	public function removeLine($line) {
		return $this->remove($line);
	}

	/**
	 * Remove the line given (if it exists) from this users cronttab.
	 * Note: this will only remove the first if there's a duplicate.
	 * @param  {string} $line The line to remove
	 * @return {bool} True if removed, false if not found
	 */
	public function remove($line) {
		$this->getLock()->acquire(true);
		try {
			$line = trim($line);
			$backup = $this->getAll();
			$newCrontab = $backup;

			$ret = array_search($line, $newCrontab);
			if ($ret !== false) {
				unset($newCrontab[$ret]);
				$this->installCrontab($newCrontab);
				return true;
			} else {
				return false;
			}
		} finally {
			$this->getLock()->release();
		}

	}

	/**
	 * Add an entry to Cron. Takes either a direct string, or an array of the following options:
	 * Either (a string):
	 *   * * * * * /bin/command/to/run
	 * or
	 *  array (
	 *    array("command" => "/bin/command/to/run",  "minute" => "1"), // Runs command at 1 minute past the hour, every hour
	 *    array("command" => "/bin/command/to/run", "magic" => "@hourly"), // Runs it hourly
	 *    "* * * * * /bin/command/to/run",
	 *    array("@monthly /bin/command/to/run"), // Runs it monhtly
	 *  )
	 *
	 * See the end of 'man 5 crontab' for the extension commands you can use.
	 * crontab does sanity checking when importing a crontab. If this is throwing an exception
	 * about being unable to add an entry,check the error file /tmp/cron.error for reasons.
	 */
	public function add() {
		// Takes either an array, or a series of params
		$args = func_get_args();
		if (!isset($args[0]))
			throw new \Exception("add takes at least one parameter");

		if (is_array($args[0])) {
			$addArray[] = $args[0];
		} else {
			$addArray[] = array($args[0]);
		}

		foreach ($addArray as $add) {
			if (isset($add[0])) {
				$this->addLine($add[0]);
				continue;
			} else if (is_array($add)) {
				if (!isset($add['command']))
					throw new \Exception("No command to execute by cron");

				if (isset($add['magic'])) {
					$newline = $add['magic']." ";
				} else {
					$cronTime = array("minute", "hour", "dom", "month", "dow");
					foreach ($cronTime as $check) {
						if (isset($add[$check])) {
							$cronEntry[$check] = $add[$check];
						} else {
							$cronEntry[$check] = "*";
						}
					}
					$newline = implode(" ", $cronEntry);
				}
				if ($newline == "* * * * *")
					throw new \Exception("Can't add * * * * * programatically. Add it as a line. Probably a bug");

				$newline .= " ".$add['command'];
				$this->addLine($newline);
			}
		}
	}

	/**
	 * Removes all reference of $cmd in cron
	 * @param {string} $cmd The command to remove
	 */
	public function removeAll($cmd) {
		$this->getLock()->acquire(true);
		try {
			$crontab = $this->getAll();
			$changed = false;
			foreach ($crontab as $i => $v) {
				// Ignore if it's an empty line
				if (!$v) {
					continue;
				}
				if (preg_match("/^#/", $v))
					continue;
				$cronline = preg_split("/\s/", $v);
				if ($cronline[0][0] == "@") {
					array_shift($cronline);
				} else {
					// Yuck.
					array_shift($cronline);
					array_shift($cronline);
					array_shift($cronline);
					array_shift($cronline);
					array_shift($cronline);
				}
				if (in_array($cmd, $cronline)) {
					unset($crontab[$i]);
					$changed = true;
				}
			}
			if ($changed) {
				$this->installCrontab($crontab);
			}
		} finally {
			$this->getLock()->release();
		}
	}

	/**
	 * Actually import the stuff to the crontab
	 * @param {array} $arr The array of elements to add
	 */
	private function installCrontab($arr) {
		if(!file_exists('/tmp/cron.error')) {
			touch('/tmp/cron.error');
		}
		// Run crontab, hand it the array as stdin
		$fds = array( array('pipe', 'r'), array('pipe', 'w'), array('file', '/tmp/cron.error', 'a') );
		$rsc = proc_open('/usr/bin/crontab '.$this->uoption.' -', $fds, $pipes);
		if (!is_resource($rsc))
			throw new \Exception("Unable to run crontab");

		fwrite($pipes[0], join("\n", $arr)."\n");
		fclose($pipes[0]);
		proc_close($rsc);
		// Ensure that the logfile is writable by everyone, if I created it
		@chmod("/tmp/cron.error", 0777);
	}

	/**
	 * Implement locks to avoid multiple things modifying same cron
	 *
	 * @return void
	 */
	private function getLock() {
		if(!empty($this->lock)) {
			return $this->lock;
		}
		$lockStore = new SemaphoreStore();
		$factory = new Factory($lockStore);
		$this->lock = $factory->createLock('crontab-'.$this->user,60);
		return $this->lock;
	}

}
