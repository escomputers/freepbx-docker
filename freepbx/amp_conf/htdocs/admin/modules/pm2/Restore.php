<?php
namespace FreePBX\modules\Pm2;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
	public function runRestore(){
		$configs = $this->getConfigs();
		$this->importAdvancedSettings($configs['settings']);
	}
	public function processLegacy($pdo, $data, $tablelist, $unknowntables){
		$this->restoreLegacyAdvancedSettings($pdo);
	}
}