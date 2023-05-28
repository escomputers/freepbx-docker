<?php
namespace FreePBX\modules\Sipsettings;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
	public function runRestore() {
		$settings = $this->getConfigs();
		$backupinfo = $this->getBackupInfo();
		$skipoptions = $this->getCliarguments();
		$preserevdata = array();
		if ($backupinfo['warmspareenabled'] == 'yes' || $skipoptions['skipbindport'] || $skipoptions['skipremotenat']) {
			if ($backupinfo['warmspare_remotebind'] =='yes') {
				$skipoptions['skipbindport'] =1;
			}
			if ($backupinfo['warmspare_remotenat'] =='yes') {
				$skipoptions['skipremotenat'] =1;
			}
			$preserevdata = $this->get_sipsettings_data();
		}
		foreach ($settings['kvstore'] as $key => $value) {
			$this->FreePBX->Sipsettings->setMultiConfig($value, $key);
		}
		$this->FreePBX->Sipsettings->loadDbConfigs($settings['database']);
		if ($backupinfo['warmspareenabled'] == 'yes' || $skipoptions['skipbindport'] || $skipoptions['skipremotenat']) {
			$this->update_sipsettings_data($preserevdata,$skipoptions);
		}
	}
	
	
	public function processLegacy($pdo, $data, $tables, $unknownTables) {
		$skipoptions = $this->getCliarguments();
		if ($skipoptions['skipbindport'] || $skipoptions['skipremotenat']) {
			$preserevdata = $this->get_sipsettings_data();
		}
		$this->restoreLegacyDatabaseKvstore($pdo);
		if ($skipoptions['skipbindport'] || $skipoptions['skipremotenat']) {
			$this->update_sipsettings_data($preserevdata,$skipoptions);
		}

		if(version_compare($this->data["pbx_version"], "2.12", "<")){
			$this->log(_("Setting up chan_sip only."), "INFO");
			$this->FreePBX->Database->prepare("UPDATE freepbx_settings SET `value` = 'chan_sip' WHERE `keyword` = 'ASTSIPDRIVER'")->execute();
			$driver = $this->FreePBX->Config()->get_conf_setting('ASTSIPDRIVER');
			
			if(in_array("sipsettings", $tables) && $driver == "chan_sip"){
				$this->log(_("Updating bindport."), "INFO");
				if(!$this->fixSipSettingsTableLeacy()){
					$this->log(_("An error occurred fixing bindport. Please check Advanced SIP Settings modules."), "WARNING");
				}						
			}
			elseif(in_array("sipsettings", $tables) && $driver != "chan_sip") {
				$this->log(_("Chan_sip must be selected only. No update."), "WARNING");
			}
		}
	}

	/**
	 * fixSipSettingsTableLeacy
	 *
	 * @return bool
	 */
	public function fixSipSettingsTableLeacy(){
		$status = true;
		try {
			$query = "SELECT data AS port FROM `sipsettings` WHERE `keyword` = 'bindport'";
			$sipSet = $this->FreePBX->Database->query($query)->fetchall(\PDO::FETCH_ASSOC);

			$bindport = empty($sipSet) || $sipSet["port"] == "" ? "5060" : $sipSet["port"];
			$this->log(sprintf(_("Bindport set to %s."),$bindport), 'INFO');
			
			$query = "UPDATE `sipsettings` SET `data` = :port WHERE `keyword` = 'bindport'";
			$this->FreePBX->Database->prepare($query)->execute(array(":port" => $bindport));
		} catch(\Exception $e) {
			$this->log($e->getMessage(),'ERROR');
			$status = false;
		}
		
		return $status;
	}

	public function get_sipsettings_data() {
		$response = array();
		$stmt=$this->FreePBX->Database->prepare("select `key`, `val`,`type`,`id` from kvstore_Sipsettings where id=:id");
		$stmt->execute([':id' => 'noid']);
		$response['kvstore_sipsettings'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		$stmt=$this->FreePBX->Database->prepare("select `key`, `val`,`type`,`id` from kvstore_Sipsettings where `id`=:id and `key`=:key");
		$stmt->execute([':id' => 'noid',':key' => 'binds']);
		$response['binds'] = $stmt->fetch(\PDO::FETCH_ASSOC);
		$stmt = $this->FreePBX->Database->prepare("select `keyword`, `seq`,`type`,`data` from sipsettings");
		$stmt->execute();
		$response['sipsettings'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		return $response;
	}

	public function update_sipsettings_data($preservedata,$skipoptions) {
		if (is_array($preservedata)) {
			$skip_fields = array();
			$binds_dynamic_column = array();
			if ($skipoptions['skipbindport']) {
				$skip_fields = array('binds','bindaddr','bindport','tlsbindaddr','tlsbindport');
				if (is_array($preservedata['binds']) && isset($preservedata['binds']['val']) && $preservedata['binds']['val'] !='') {
					$binds = json_decode($preservedata['binds']['val'], true);
					if (is_array($binds) && count($binds) >0) {
						foreach ($binds as $jsonkey=>$jsonvalue) {
							if (is_array($jsonvalue) && count($jsonvalue) >0) {
								foreach ($jsonvalue as $key=>$val) {
									if (!in_array($key,$binds_dynamic_column)) {
										array_push($binds_dynamic_column,$key);
									}
								}
							}
						}
					}
				}
			}
			if ($skipoptions['skipremotenat']) {
				array_push($skip_fields,'localnets','externip');
			}
			$bindport_sipsettings_fields = array('bindaddr','tlsbindaddr','tlsbindport','bindport');
			$params = array();
			$params_sipsettings = array();
			if (is_array($preservedata['kvstore_sipsettings'])) {
				foreach ($preservedata['kvstore_sipsettings'] as $key=>$val) {
					if (in_array($val['key'],$skip_fields)) {
						$data = array();
						$data['val'] = $val['val'];
						$data['id'] = $val['id'];
						$data['type'] = $val['type'];
						$params[$val['key']] = $data;
					}
					foreach($binds_dynamic_column as $k=>$v) {
						if (strpos($val['key'],$v) !== false) {
							$data = array();
							$data['val'] = $val['val'];
							$data['id'] = $val['id'];
							$data['type'] = $val['type'];
							$params[$val['key']] = $data;
						}
					}
				}
			}

			if(is_array($preservedata['sipsettings']) && $skipoptions['skipbindport']) {
				foreach ($preservedata['sipsettings'] as $key=>$val) {
					if (in_array($val['keyword'],$bindport_sipsettings_fields)) {
						$data = array();
						$data['data'] = $val['data'];
						$data['seq'] = $val['seq'];
						$data['type'] = $val['type'];
						$params_sipsettings[$val['keyword']] = $data;
					}
				}
			}

			if(count($params) >0) {
				foreach($params as $key=>$val) {
					$this->log(sprintf(_("update preserved data in kvstore_Sipsettings table : %s"),$key),'INFO');
					$stmt=$this->FreePBX->Database->prepare("REPLACE INTO kvstore_Sipsettings (`key`,`val`,`id`,`type`) values (:key,:value,:id,:type)");
					$stmt->execute([':key' => $key,':value'=>$val['val'],':id'=>$val['id'],':type'=>$val['type']]);
				}
			}

			if(count($params_sipsettings) >0) {
				foreach($params_sipsettings as $key=>$val) {
					$this->log(sprintf(_("update preserved data in sipsettings table : %s"),$key),'INFO');
					$stmt=$this->FreePBX->Database->prepare("REPLACE INTO sipsettings (`keyword`,`data`,`seq`,`type`) values (:keyword,:data,:seq,:type)");
					$stmt->execute([':keyword' => $key,':data'=>$val['data'],':seq'=>$val['seq'],':type'=>$val['type']]);
				}
			}
		}
	}

	public function getResetInfo() {
		$skipoptions = $this->getCliarguments();
		$backupinfo = $this->getBackupInfo();
		$return = false;
		if ($backupinfo['warmspareenabled'] == 'yes' && $backupinfo['warmspare_remotebind'] == 'yes') {
			$this->log(_("warmspare remotebind option enabled"));
			$return = true;
		}
		if ($backupinfo['warmspareenabled'] == 'yes' && $backupinfo['warmspare_remotenat'] == 'yes') {
			$this->log(_("warmspare remotenat option enabled"));
			$return = true;
		}
		if ($skipoptions['skipbindport']) {
			$this->log(_("user passed option for skip bind address section"));
			$return = true;
		}
		if ($skipoptions['skipremotenat']) {
			$this->log(_("user passed option for skip NAT settings section"));
			$return = true;
		}
		return $return;
	}
}
