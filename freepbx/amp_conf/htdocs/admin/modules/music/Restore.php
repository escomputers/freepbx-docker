<?php
namespace FreePBX\modules\Music;
use FreePBX\modules\Backup as Base;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Finder\Finder;
class Restore Extends Base\RestoreBase{
	public function runRestore(){
		$configs = $this->getConfigs();
		$files = $this->getFiles();
		$this->FreePBX->Database->query("TRUNCATE TABLE music");
		$mohdir = $this->FreePBX->Config->get('ASTVARLIBDIR').'/'.$this->FreePBX->Config->get('MOHDIR');
		shell_exec("rm -rf $mohdir 2>&1");
		//recreate the moh folder we just deleted
		shell_exec("mkdir $mohdir 2>&1");

		foreach ($configs['data'] as $category) {
			$this->FreePBX->Music->addCategoryById($category['id'], $category['category'], $category['type']);
			$this->FreePBX->Music->updateCategoryById($category['id'], $category['type'], $category['random'], $category['application'], $category['format']);
		}
		$this->importAdvancedSettings($configs['settings']);

		foreach ($files as $file) {
			$filename = $file->getPathTo().'/'.$file->getFilename();
			$source = $this->tmpdir.'/files'.$file->getPathTo().'/'.$file->getFilename();
			$dest = $filename;
			if(file_exists($source)){
				@mkdir($file->getPathTo(),0755,true);
				copy($source, $dest);
			}

		}
	}

	public function processLegacy($pdo, $data, $tables, $unknownTables){
		$this->restoreLegacyAdvancedSettings($pdo);
		$mohdir = $this->FreePBX->Config->get('ASTVARLIBDIR').'/'.$this->FreePBX->Config->get('MOHDIR');
		if(version_compare_freepbx($this->getVersion(),"13","ge")) {
			$this->restoreLegacyDatabase($pdo);
		}
		else{
			if(file_exists($this->tmpdir.'/etc/asterisk/musiconhold_additional.conf')){
				$conf_array = parse_ini_file($this->tmpdir.'/etc/asterisk/musiconhold_additional.conf', true);
			}
			elseif(file_exists($this->tmpdir.'/files'.'/etc/asterisk/musiconhold_additional.conf')){
				$conf_array = parse_ini_file($this->tmpdir.'/files'.'/etc/asterisk/musiconhold_additional.conf', true);
			}

			if(!empty($conf_array) && is_array($conf_array)){
				$this->FreePBX->Database->query("TRUNCATE TABLE music");
				shell_exec("rm -rf $mohdir 2>&1");
				shell_exec("mkdir $mohdir 2>&1");
				foreach($conf_array as $cat => $values){
					if(!empty($cat) && ($cat != "none") && !empty($values["mode"])){
						$sql 	= "INSERT INTO music (category ,type, random, application, format) VALUES (:category , :type, :random, :application, :format) ";
						$data 	= array(	":category" 	=> $cat,
											":type"			=> $values["mode"],
											":random" 		=> empty($values["random"])		? "0": $values["random"] ,
											":application" 	=> empty($values["application"])? "" : $values["application"] ,
											":format" 		=> empty($values["format"])		? "" : $values["format"]
										);	
						$this->FreePBX->Database->prepare($sql)->execute($data);			
					}
				}				
			}
		}

		if(!file_exists($this->tmpdir.'/var/lib/asterisk/moh')) {
			return;
		}

		$finder = new Finder();
		$fileSystem = new Filesystem();
		foreach ($finder->in($this->tmpdir.'/var/lib/asterisk/moh') as $item) {
			if($item->isDir()) {
				$fileSystem->mkdir($mohdir.'/'.$item->getRelativePathname());
				continue;
			}
			$fileSystem->copy($item->getPathname(), $mohdir.'/'.$item->getRelativePathname(), true);
		}
	}
}
