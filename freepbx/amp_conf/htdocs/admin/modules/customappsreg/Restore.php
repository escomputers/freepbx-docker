<?php
namespace FreePBX\modules\Customappsreg;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
	public function runRestore(){
		$configs = $this->getConfigs();
		$this->importKVStore($configs['kvstore']);
		foreach($configs['extensions'] as $extension){
			$this->FreePBX->Customappsreg->editCustomExten($extension['custom_exten'], $extension['custom_exten'], $extension['description'], $extension['notes']);
		}
	}

	public function processLegacy($pdo, $data, $tables, $unknownTables){
		$this->restoreLegacyDatabaseKvstore($pdo);
	}
}
