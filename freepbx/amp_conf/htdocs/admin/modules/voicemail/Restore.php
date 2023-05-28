<?php
namespace FreePBX\modules\Voicemail;
use FreePBX\modules\Backup as Base;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Finder\Finder;
class Restore Extends Base\RestoreBase{
	public function runRestore(){
		$configs = $this->getConfigs();
		$files = $this->getFiles();
		$nfiles = 0;
		foreach($files as $file){
			if($file->getType() == 'voicemail' || $file->getType() == 'greeting'){
				$filename = $file->getPathTo().'/'.$file->getFilename();
				$source = $this->tmpdir.'/files'.$file->getPathTo().'/'.$file->getFilename();
				$dest = $filename;
				if(file_exists($source)){
					@mkdir($file->getPathTo(),0755,true);
					copy($source, $dest);
					$nfiles++;
				}
			}
			if($file->getType() == 'conf') {
				$filename = $file->getPathTo().'/'.$file->getFilename();
				$source = $this->tmpdir.'/files'.$file->getPathTo().'/'.$file->getFilename();
				$dest = $filename;
				if(file_exists($source)){
					copy($source, $dest);
				}
			}
		}
		$this->log(sprintf(_("%s Files Restored"), $nfiles++),'INFO');
		$this->importTables($configs['tables']);
	}

	public function processLegacy($pdo, $data, $tables, $unknownTables) {
		$this->restoreLegacyAll($pdo);
		$finder = new Finder();
		$fileSystem = new Filesystem();
		$confdir = $this->FreePBX->Config->get_conf_setting('ASTETCDIR');
		if(file_exists($this->tmpdir.'/etc/asterisk/voicemail.conf')) {
			$fileSystem->copy($this->tmpdir.'/etc/asterisk/voicemail.conf', $confdir.'/voicemail.conf', true);
		}
		if(!file_exists($this->tmpdir.'/var/spool/asterisk/voicemail')) {
			return;
		}
		$vmdir = $this->FreePBX->Config->get_conf_setting('ASTSPOOLDIR') . "/voicemail";
		exec("rm -Rf ".$vmdir);
		foreach ($finder->in($this->tmpdir.'/var/spool/asterisk/voicemail') as $item) {
			if($item->isDir()) {
				$fileSystem->mkdir($vmdir.'/'.$item->getRelativePathname());
				continue;
			}
			$fileSystem->copy($item->getPathname(), $vmdir.'/'.$item->getRelativePathname(), true);
		}

	}

}
