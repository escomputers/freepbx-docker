<?php
namespace FreePBX\modules\Infoservices;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
	public function runRestore(){
		$configs = $this->getConfigs();
		$this->importFeatureCodes($configs['features']);
		$this->importAdvancedSettings($configs['settings']);
	}
	public function processLegacy($pdo, $data, $tables, $unknownTables){
		$this->restoreLegacyFeatureCodes($pdo);
	}
}
