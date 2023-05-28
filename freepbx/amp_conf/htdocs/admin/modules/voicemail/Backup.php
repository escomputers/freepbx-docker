<?php
namespace FreePBX\modules\Voicemail;
use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
	public function runBackup($id,$transaction){
		$dirs = [];
		$vmx = [];
		$voiceMail = $this->FreePBX->Voicemail;
		$voicemailConf = $voiceMail->getVoicemail(false);
		$backupsettings =!empty($id) ? $this->FreePBX->Backup->getAll($id) : [];
		$mailboxData = $voiceMail->bulkhandlerExport('extensions');
		$vmboxes = $voiceMail->getBaseBackupSettings();
		//Exclude settings yes/no
		$backupsettings['voicemail_vmrecords'] = isset($backupsettings['voicemail_vmrecords'])?$backupsettings['voicemail_vmrecords']:'no';
		$backupsettings['voicemail_vmgreetings'] = isset($backupsettings['voicemail_vmgreetings'])?$backupsettings['voicemail_vmgreetings']:'no';
		$this->log('Exclude Greeeting ?'.$backupsettings['voicemail_vmgreetings']);
		$this->log('Exclude VMRecords ?'.$backupsettings['voicemail_vmrecords']);
		foreach($vmboxes as $exten){
				// take all messages 
				$fileDirList = $voiceMail->allFileList($exten['extension']);
				foreach ($fileDirList['files'] as $file) {
					if($file['basename'] === 'greet.wav' || $file['basename'] === 'temp.wav' || $file['basename'] === 'busy.wav' || $file['basename'] === 'unavail.wav'){
						continue;
					}
					if($backupsettings['voicemail_vmrecords'] == 'no' && !is_link($file['path'].'/'.$file['basename']) ){
						$this->addFile($file['basename'], $file['path'], $file['base'], $file['type']);
					}
				}
				if($backupsettings['voicemail_vmgreetings'] == 'no'){
					$greetings = $voiceMail->getGreetingsByExtension($exten['extension']);
					foreach($greetings as $greeting){
						$path = pathinfo($greeting,PATHINFO_DIRNAME);
						$dirs[] = $path;
							$this->addFile(basename($greeting), $path, 'ASTVARSPOOLDIR', "greeting");
					}
				}
				if($exten['rpassword'] && isset($mailboxData[$exten['extension']]['voicemail_vmpwd'])){
					$mailboxData[$exten['extension']]['voicemail_vmpwd'] = $exten['extension'];
				}
		}

		$configs = [
			'voicemailConf' => $voicemailConf,
			'mailboxData' => $mailboxData,
			'features' => $this->dumpFeatureCodes(),
			'settings' => $this->dumpAdvancedSettings(),
			'tables' => $this->dumpDBTables('voicemail_admin',false)
		];
		$file = $this->FreePBX->Config->get('ASTETCDIR')."/voicemail.conf";
		$path = pathinfo($file,PATHINFO_DIRNAME);
		$this->addFile(basename($file), $path, 'ASTETCDIR', "conf");
		$this->addDirectories($dirs);
		$this->addDependency('core');
		$this->addConfigs($configs);
	}
}
