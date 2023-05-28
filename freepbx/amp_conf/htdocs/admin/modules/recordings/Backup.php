<?php
namespace FreePBX\modules\Recordings;
use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
	public function runBackup($id,$transaction){
		$dirs = [];
		$base = $this->FreePBX->Config->get('ASTVARLIBDIR');
		$recs = $this->FreePBX->Recordings->getAll();
		foreach($recs as $rec){
			foreach($rec['files'] as $file){
				foreach($file as $key => $value){
					$basefilepath = $base.'/sounds/'.$key.'/';
					foreach($value as $recordingfile){
						if(!file_exists($basefilepath.$recordingfile)){
							continue;
						}
						$dirpaths = pathinfo($basefilepath.$recordingfile);
						$path = $dirpaths['dirname'];
						$dirs[$path] = $path;
						$this->addFile(basename($recordingfile),$path,'ASTVARLIBDIR',"recording");
					}
				}
			}
		}
		$this->addDirectories($dirs);
		$this->addDependency('soundlang');
		$this->addConfigs([
			'data' => $this->FreePBX->Recordings->getAll(),
			'features' => $this->dumpFeatureCodes()
		]);
	}
}
