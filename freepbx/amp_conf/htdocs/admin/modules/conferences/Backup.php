<?php
namespace FreePBX\modules\Conferences;
use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
	public function runBackup($id,$transaction){
		$this->addDependency('recordings');
		$this->addConfigs([
			'data' => $this->FreePBX->Conferences->bulkhandlerExport('conferences'),
			'kvstore' => $this->dumpKVStore(),
			'features' => $this->dumpFeatureCodes(),
			'settings' => $this->dumpAdvancedSettings()
		]);
	}
}
