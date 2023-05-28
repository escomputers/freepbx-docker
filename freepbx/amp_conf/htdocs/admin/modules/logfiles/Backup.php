<?php
namespace FreePBX\modules\Logfiles;

use FreePBX\modules\Backup as Base;

class Backup Extends Base\BackupBase
{
    public function runBackup($id, $transaction)
    {
		$tables = $this->dumpTables();
		$configs = [
			'tables' => $tables
		];
		$this->addConfigs($configs);
	}
}