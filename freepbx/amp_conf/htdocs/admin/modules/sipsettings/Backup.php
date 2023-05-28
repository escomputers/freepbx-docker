<?php
namespace FreePBX\modules\Sipsettings;
use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
	public function runBackup($id,$transaction){
		/** For stuff in the kvstore */
		$settings['kvstore'] = $this->dumpKVStore();
		/** Database stuff */
		$settings['database'] = $this->FreePBX->Sipsettings->dumpDbConfigs();
		$this->addDependency('core');
		$this->addConfigs($settings);
	}
}