<?php
namespace FreePBX\modules\Logfiles;

use FreePBX\modules\Backup as Base;

class Restore Extends Base\RestoreBase
{
    public function runRestore()
    {
        $configs = $this->getConfigs();
        if( ! empty($configs['tables']) && is_array($configs['tables']) )
        {
            $this->importTables($configs['tables']);
        }
    }
}