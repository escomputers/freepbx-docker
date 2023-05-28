<?php
namespace FreePBX\modules\Callrecording;
use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
	public function runBackup($id,$transaction){
		//backup extension recording rules, which are in astdb
		$astDBRecordingRules = array();
		foreach($this->dumpAstDB('AMPUSER') as $line) {
			$sLine = serialize($line);
			if(strpos($sLine, 'recording') !== false){
				$astDBRecordingRules[] = $line;
			}
		}

		$this->addConfigs([
			'rules' => $this->FreePBX->Callrecording->listAll(),
			'modules' => $this->FreePBX->Callrecording->dumpExtensions(),
			'settings' => $this->dumpAdvancedSettings(),
			'astdb' => $astDBRecordingRules
		]);
	}
}
