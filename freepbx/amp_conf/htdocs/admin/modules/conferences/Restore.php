<?php
namespace FreePBX\modules\Conferences;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
	public function runRestore(){
			$configs = $this->getConfigs();
			$this->FreePBX->Conferences->bulkhandlerImport('conferences', $configs['data']);
			$this->importKVStore($configs['kvstore']);
			$this->importFeatureCodes($configs['features']);
			$this->importAdvancedSettings($configs['settings']);
		}
		public function processLegacy($pdo, $data, $tables, $unknownTables){
			$this->restoreLegacyAll($pdo);
		}
}
