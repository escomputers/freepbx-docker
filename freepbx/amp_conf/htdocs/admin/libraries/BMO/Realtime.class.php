<?php
// vim: set ai ts=4 sw=4 ft=php:
namespace FreePBX;

/**
 * This is part of the FreePBX Big Module Object.
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2015 Sangoma Technologies
 */

/**
 * This controls the realtime parts of Asterisk. At the moment,
 * the only thing that FreePBX tries to modify is the queue log,
 * but this may expand in the future.
 */
class Realtime extends FreePBX_Helpers {

	public function enableQueueLog($driver = 'odbc', $dbname = 'asteriskcdrdb', $table = 'queuelog') {
		$this->setConfig("queuelog", true);
		$this->setConfig("queuelog-conf", [ "driver" => $driver, "dbname" => $dbname, "table" => $table ]);
	}

	public function disableQueueLog() {
		$this->setConfig("queuelog", false);
		$this->setConfig("queuelog-conf", false);
	}

	public function queueLogEnabled() {
		return $this->getConfig("queuelog");
	}

	public function write() {
		// Does the file exist already?  If it doesn't, ConfigFile will crash.
		if (!file_exists("/etc/asterisk/extconfig.conf")) {
			touch("/etc/asterisk/extconfig.conf");
		}
		$conf['extconfig.conf'] = '#include extconfig_custom.conf';
		$this->FreePBX->WriteConfig($conf);
		if (!file_exists("/etc/asterisk/extconfig_custom.conf")) {
			touch("/etc/asterisk/extconfig_custom.conf");
		}
		$current = $this->ConfigFile("extconfig.conf");
		$this->updateQueueSettings($current);
	}

	private function updateQueueSettings($current) {
		if ($this->getConfig("queuelog")) {
			$tmparr = $this->getConfig("queuelog-conf");
			$str = join(",", [ $tmparr['driver'], $tmparr['dbname'], $tmparr['table'] ]);
			if (isset($current->config->ProcessedConfig['settings']['queue_log'])) {
				unset($current->config->ProcessedConfig['settings']['queue_log']);
			}
			$current->addEntry('settings', [ 'queue_log' => $str ]);
		} else {
			if (!isset($current->config->ProcessedConfig['settings'])) {
				// No settings section
				return;
			}
			if (!isset($current->config->ProcessedConfig['settings']['queue_log'])) {
				// It's already not there, but something else IS. Don't touch it.
				return;
			}
			// Delete the queue_log setting
			$current->removeEntry('settings', 'queue_log');
		}
	}
}
