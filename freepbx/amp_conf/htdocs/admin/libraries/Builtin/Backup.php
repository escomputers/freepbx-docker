<?php
namespace FreePBX\Builtin;
use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
	public function runBackup($id,$transaction){
		$sql = "SELECT `keyword`, `value` FROM freepbx_settings WHERE module = ''";
		$sth = $this->FreePBX->Database->prepare($sql);
		$sth->execute([":name" => $module]);
		$this->addConfigs([
			'settings' => $sth->fetchAll(\PDO::FETCH_KEY_PAIR),
			'realtime' => [
				'kvstore' => $this->FreePBX->Realtime->getAll()
			]
		]);
	}
}