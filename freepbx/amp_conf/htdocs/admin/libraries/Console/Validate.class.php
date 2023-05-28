<?php
namespace FreePBX\Console\Command;
//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class Validate extends Command {
	protected function configure(){
		$this->FreePBXConf = \FreePBX::Config();
		$this->setName('validate')
		->setDescription(_('Validate your PBX against potential hacks'))
		->setDefinition(array(
			new InputOption('clean', 'c', InputOption::VALUE_NONE, _('Purge and clean system')
		)));
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		if(file_exists("/tmp/validate.phar.gz.sha1")) {
			unlink("/tmp/validate.phar.gz.sha1");
		}
		if(file_exists("/tmp/validate.phar")) {
			unlink("/tmp/validate.phar");
		}
		$progress = new ProgressBar($output);
		$output->writeln("Downloading...");
		$progress->setFormat('%bar%');
		$this->download("validate.phar.gz.sha1",function($read,$total) use ($output, $progress){
			if($read == 0) {
				$progress->start($total);
			} elseif($read == $total) {
				$progress->finish();
			} else {
				$progress->setProgress($read);
			}
		});
		$output->writeln("");
		$this->download("validate.phar.gz",function($read,$total) use ($output,$progress){
			if($read == 0) {
				$progress->start($total);
			} elseif($read == $total) {
				$progress->finish();
			} else {
				$progress->setProgress($read);
			}
		});
		$output->writeln("");
		$sha1 = file_get_contents("/tmp/validate.phar.gz.sha1");
		if(sha1_file("/tmp/validate.phar.gz") != trim($sha1)) {
			$output->writeln("<error>Could not validate download!</error>");
			exit(-1);
		}

		$process = new Process("gzip -fd /tmp/validate.phar.gz");
		$process->mustRun();
		chmod("/tmp/validate.phar",0775);

		if($input->getOption('clean')) {
			$process = new Process("/tmp/validate.phar --clean");
		} else {
			$process = new Process("/tmp/validate.phar");
		}
		$process->setTty(true);
		$process->run();
	}

	private function download($filename,$progress_callback=null) {
		$mf = \module_functions::create();
		$urls = $mf->generate_remote_urls("/".$filename, true);
		foreach($urls['mirrors'] as $u) {
			$headers = get_headers_assoc($u.$urls['path']);
			if (!empty($headers)) {
				$url = $u.$urls['path'];
				break;
			}
		}
		$streamopts = array(
			'http' =>
				array(
					'method' => "POST",
					'content' => !empty($urls['query']) ? $urls['query'] : ''
				)
		);

		$download_chunk_size = 12*1024;
		$totalread = 0;
		$streamcontext = stream_context_create($streamopts);
		$filename = "/tmp/".$filename;
		if (!($fp = @fopen($filename,"w"))) {
			return array(sprintf(_("Error opening %s for writing"), $filename));
		}

		if ($amp_conf['MODULEADMINWGET'] || !$dp = @fopen($url,'r',false,$streamcontext)) {
			$p = (!empty($urls['query'])) ? "--post-data ".escapeshellarg($urls['query']) : "";
			\FreePBX::Curl()->setEnvVariables();
			exec("wget --tries=1 --timeout=600 $p -O ".escapeshellarg($filename)." ".escapeshellarg($url)." 2> /dev/null", $filedata, $retcode);
			usleep(5000); //wait for file to be placed
			if ($retcode != 0) {
				throw new \Exception(sprintf(_("Error opening %s for reading"), $url));
			} else {
				if (!$dp = @fopen($filename,'r')) {
					throw new \Exception(sprintf(_("Error opening %s for reading"), $url));
				}
			}
		}

		$filedata = '';
		$progress_callback(0,$headers['content-length']);
		while (!feof($dp)) {
			$data = fread($dp, $download_chunk_size);
			$filedata .= $data;
			$totalread += strlen($data);
			if (is_callable($progress_callback)) {
				$progress_callback($totalread,$headers['content-length']);
			}
		}
		$progress_callback($headers['content-length'],$headers['content-length']);
		fwrite($fp,$filedata);
		fclose($dp);
		fclose($fp);

		if (is_readable($filename) !== TRUE ) {
			throw new \Exception(sprintf(_('Unable to save %s'),$filename));
		}
	}
}
