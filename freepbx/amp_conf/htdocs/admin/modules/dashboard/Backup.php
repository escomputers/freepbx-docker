<?php
namespace FreePBX\modules\Dashboard;
use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
	public function runBackup($id,$transaction){
		$this->addConfigs([
			'settings' => $this->dumpAdvancedSettings()
		]);
	}
}