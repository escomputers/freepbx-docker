<?php
//Namespace should be FreePBX\Console\Command
namespace FreePBX\Console\Command;

//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
//la mesa
use Symfony\Component\Console\Helper\Table;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use Symfony\Component\Console\Command\HelpCommand;

use Sepia\PoParser\SourceHandler\FileSystem as POFS;
use Sepia\PoParser\Parser as POP;

use Carbon\Carbon;
class Localization extends Command {

	const PROJECT_LIST = ['freepbx','fpbxc'];
	const BASE_URL = 'https://weblate.sangoma.com';
	const BASE_API_URL = '/api';
	const COMPONENTS_URL = '/projects/%project%/components/';
	const TRANSLATIONS_URL = '/components/%project%/%component%/translations/';
	const TRANSLATION_FILE_URL = '/translations/%project%/%component%/%language%/file';

	private $requests;
	private $headers = [];

	protected function configure(){
		$this->setName('localization')
			->setDescription(_('Localization Utilities'))
			->setDefinition(array(
				new InputOption('authorization', null, InputOption::VALUE_REQUIRED, sprintf(_('Authorization Token. Requests are limited to 100/day, without setting this. See %s for more information'),self::BASE_URL.'/accounts/profile/#api')),
				new InputOption('list', null, InputOption::VALUE_NONE, _('List Modules with localizations')),
				new InputOption('update', null, InputOption::VALUE_NONE, _('Update localizations, optionally provide --module')),
				new InputOption('module', null, InputOption::VALUE_REQUIRED, _('Module to work against, if not provided all modules are assumed')),
				new InputOption('language', null, InputOption::VALUE_REQUIRED, _('The language code to work against, if not provided all languages are assumed')),
				new InputOption('ignorechange', null, InputOption::VALUE_NONE, _('Ignore last change date, process regardless')),
			));
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		if(!empty($input->getOption('authorization'))) {
			$this->setAuth($input->getOption('authorization'));
		}
		if($input->getOption('list')) {
			$this->getList($input,$output);
			return;
		}
		if($input->getOption('update')) {
			$this->update($input,$output);
			return;
		}
		$this->outputHelp($input,$output);
	}

	private function setAuth($token) {
		$this->headers['Authorization'] = 'Token '.$token;
	}

	private function update(InputInterface $input, OutputInterface $output) {
		$module = $input->getOption('module');
		$language = $input->getOption('language');
		$ignorechange = !empty($input->getOption('ignorechange'));
		if(empty($module)) {
			foreach($this->getModuleLanuageList() as $module) {
				if(!$module['hasLangs']) {
					$output->writeln("<error>".$module['rawname']." has no languages</error>");
					continue;
				}
				try {
					$this->updateModulei18n($output, $module['rawname'], $ignorechange, $language);
				} catch(\Exception $e) {
					$output->writeln("<error>".$e->getMessage()."</error>");
					if($e->getCode() === 403) {
						exit(-1);
					}
				}
			}
		} else {
			$translations = $this->updateModulei18n($output, $input->getOption('module'), $ignorechange, $language);
		}
	}

	private function getList(InputInterface $input, OutputInterface $output) {
		$modules = $this->getModuleLanuageList();
		$rows = array_map(function($value){
			return [
				$value['name'],
				$value['rawname'],
				implode(",",$value['langs'])
			];
		},$modules);

		$table = new Table($output);
		$table->setHeaders(array(_('Module'),_('Rawname'),_('Languages')));
		$table->setRows($rows);
		$table->render($output);
	}

	private function updateModulei18n(OutputInterface $output, $module,$ignorechange=false,$language=null) {
		$path = \FreePBX::Config()->get('AMPWEBROOT');
		$translations = $this->getTranslations($module);
		$output->writeln("Processing $module");
		foreach($translations['results'] as $translation) {
			if(!empty($language) && $translation['language_code'] !== $language) {
				//$output->writeln($module." '".$translation['language_code']."' is not '".$language."' skipping");
				continue;
			}
			$mo = $module;
			$filename = $path."/admin/modules/".$module."/i18n/".$translation['language_code']."/LC_MESSAGES/".$module.".po";
			if($module === 'framework') {
				$mo = 'amp';
				$filename = $path."/admin/i18n/".$translation['language_code']."/LC_MESSAGES/amp.po";
			}

			if(file_exists($filename)) {

				if(empty($translation['last_change'])) {
					if(!$ignorechange) {
						$output->writeln("\t".$module."[".$translation['language_code']."] last change date unknown unable to determine last change. Skipping (Use --ignorechange to force)");
						continue;
					} else {
						$output->writeln("\t".$module."[".$translation['language_code']."] has never been modified on weblate, forcing last change date to now");
						$now = new \DateTime("now", new \DateTimeZone("UTC"));
						$translation['last_change'] = $now->format('Y-m-d\TH:i:s\Z');
					}
				}

				$fileHandler = new POFS($filename);
				$poParser = new POP($fileHandler);
				$catalog  = $poParser->parse();
				$then = new \DateTime("@0", new \DateTimeZone("UTC"));
				$revision = $then->format('Y-m-d\TH:i:s\Z');
				foreach($catalog->getHeaders() as $header) {
					if(preg_match('/^PO-Revision-Date/i',$header)) {
						$revision = trim(explode(":",$header,2)[1]);
					}
				}

				//seconds are not inputed into the po files so remove them for comparison
				//Set to :00 because we don't actually know the seconds
				$translation['last_change'] = preg_replace("/:\d{2}\.\d*/", ":00", $translation['last_change']);

				$previous = Carbon::parse($revision);
				$previous->setTime($previous->format("H"), $previous->format("i"), 0); //set seconds to 0 because we dont know them accurately
				$last = Carbon::parse($translation['last_change']);
				$last->setTime($previous->format("H"), $previous->format("i"), 0); //set seconds to 0 because we dont know them accurately
				if($previous->getTimestamp() >= $last->getTimestamp()) {
					$output->writeln("\t".$module."[".$translation['language_code']."] is already up to date");
					continue;
				}
				$output->writeln("\t".$module."[".$translation['language_code']."] will be updated (".$previous->format('Y-m-d\TH:i:s\Z')." < ".$last->format('Y-m-d\TH:i:s\Z').")");
			} else {
				$output->writeln("\t".$module."[".$translation['language_code']."] will be added");
				mkdir(dirname($filename),0777,true);
				touch($filename);
			}
			try {
				$filedata = $this->getRequest()->get($translation['file_url'],$this->headers);
			} catch(\Exception $e) {
				$output->writeln("\t"."<error>Failed to download: ".$translation['file_url']."</error>");
				continue;
			}
			if($filedata->status_code !== 200) {
				$output->writeln("\t"."<error>Failed to download: ".$translation['file_url']."</error>");
				continue;
			}
			file_put_contents($filename, $filedata->body);
			$process = new Process('msgfmt '.$filename.' -o '.dirname($filename).'/'.$mo.'.mo');
			$process->mustRun();
		}
		$output->writeln("Finished Processing $module");
	}

	private function getModuleLanuageList() {
		$path = \FreePBX::Config()->get('AMPWEBROOT');
		$modules = \FreePBX::Modules()->getInfo();
		$modules = array_filter($modules, function($value) {
			return !($value['status'] === MODULE_STATUS_BROKEN);
		});

		$langs = [];
		if(file_exists($path."/admin/i18n")) {
			$langs = glob($path."/admin/i18n/*",GLOB_ONLYDIR);
			array_walk($langs, function(&$v, $k) {
				$v = basename($v);
			});
		}

		$final = [];
		$final['framework'] = [
			'name' => 'Framework',
			'rawname' => 'framework',
			'langs' => $langs,
			'hasLangs' => !empty($langs)
		];

		foreach($modules as $value) {
			if($value['rawname'] === 'framework') {
				continue;
			}
			$langs = [];
			if(file_exists($path."/admin/modules/".$value['rawname']."/i18n")) {
				$langs = glob($path."/admin/modules/".$value['rawname']."/i18n/*",GLOB_ONLYDIR);
				array_walk($langs, function(&$v, $k) {
					$v = basename($v);
				});
			}
			$final[$value['rawname']] = [
				'name' => $value['name'],
				'rawname' => $value['rawname'],
				'langs' => $langs,
				'hasLangs' => !empty($langs)
			];
		}
		return $final;
	}

	private function getTranslations($component, $project=null) {
		if(empty($project)) {
			foreach($this->getComponents() as $p => $components) {
				if(isset($components[$component])) {
					$project = $p;
					break;
				}
			}
		}
		if(empty($project)) {
			throw new \Exception("Unable to find $component on weblate");
		}
		$translations = $this->getRequest()->get(str_replace(['%project%','%component%'],[$project,$component],self::BASE_API_URL.self::TRANSLATIONS_URL).'?limit=200',$this->headers);
		if($translations->status_code === 200) {
			return json_decode($translations->body,true);
		}
		if($translations->status_code === 429) {
			throw new \Exception("Too many requests to weblate. Consider using an Authorization Token. See --authorization for more information", 403);
		}
		throw new \Exception("Unable to find $component on weblate");
	}

	private function getComponents() {
		$data = $this->getData('components');
		if(!empty($data)) {
			$this->components = $data;
			return $this->components;
		}
		$this->components = [];
		foreach(self::PROJECT_LIST as $project) {
			$components = $this->getRequest()->get(str_replace(['%project%'],[$project],self::BASE_API_URL.self::COMPONENTS_URL).'?limit=200',$this->headers);
			if($components->status_code === 200) {
				$tmp = json_decode($components->body,true)['results'];
				foreach($tmp as $t) {
					$this->components[$project][$t['name']] = $t;
				}
			}
			if($components->status_code === 429) {
				throw new \Exception("Consider using an Authorization Token. See --authorization for more information");
			}
		}
		$this->setData('components', $this->components, time() + 3600);
		return $this->components;
	}

	private function getData($key) {
		$sql = "SELECT `data`, `time` FROM `module_xml` WHERE `id` = :id";
		$sth = \FreePBX::Database()->prepare($sql);
		$sth->execute(array(":id" => 'loc_'.$key));
		$res = $sth->fetch(\PDO::FETCH_ASSOC);
		if(isset($res['data'])) {
			if(!empty($res['expires']) && $res['expires'] < time()) {
				return false;
			}
			return json_decode($res['data'],true);
		}
		return false;
	}

	private function setData($key, $data, $expires=0) {
		$sql = "REPLACE INTO `module_xml` (`id`,`time`,`data`) VALUES (:id,:time,:data)";
		$sth = \FreePBX::Database()->prepare($sql);
		$sth->execute(array(":id" => 'loc_'.$key,":time" => $expires, ":data" => json_encode($data)));
	}

	private function getRequest() {
		if(!empty($this->requests)) {
			return $this->requests;
		}
		$this->requests = \FreePBX::Curl()->requests(self::BASE_URL);
		return $this->requests;
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 * @throws \Symfony\Component\Console\Exception\ExceptionInterface
	 */
	protected function outputHelp(InputInterface $input, OutputInterface $output)	 {
		$help = new HelpCommand();
		$help->setCommand($this);
		return $help->run($input, $output);
	}
}
