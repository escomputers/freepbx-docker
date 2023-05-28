<?php

namespace Media\Driver\Drivers;
use Symfony\Component\Process\Process;

class Mpg123Shell extends \Media\Driver\Driver {
	private $track;
	private $version;
	private $mime;
	private $extension;
	private $options = array(
		"samplerate" => 48000, //-r
		"channels" => 2 //--mono --stereo
	);
	private $binary = 'mpg123';
	public $background = false;

	public function __construct($filename,$extension,$mime,$samplerate=48000,$channels=1,$bitrate=16) {
		$this->loadTrack($filename);
		$this->version = $this->getVersion();
		$this->mime = $mime;
		$this->extension = $extension;
		$this->options['samplerate'] = $samplerate;
		$this->options['channels'] = $channels;
		$loc = fpbx_which("mpg123");
		if(!empty($loc)) {
			$this->binary = $loc;
		}
	}

	public static function supportedCodecs(&$formats) {
		$formats["in"]["mp3"] = "mp3";
		return $formats;
	}

	public static function isCodecSupported($codec,$direction) {
		return ($direction == "in") && in_array($codec,array("mp3"));
	}

	public static function installed() {
		$loc = fpbx_which("mpg123");
		$process = new Process($loc.' --version');
		$process->run();

		// executes after the command finishes
		if (!$process->isSuccessful()) {
			return false;
		}
		return true;
	}

	public function loadTrack($track) {
		if(empty($track)) {
			throw new \Exception("A track must be supplied");
		}
		if(!file_exists($track)) {
			throw new \Exception("Track [$track] not found");
		}
		if(!is_readable($track)) {
			throw new \Exception("Track [$track] not readable");
		}
		$this->track = $track;
	}

	public function getVersion() {
		$process = new Process($this->binary.' --version');
		$process->run();

		// executes after the command finishes
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getErrorOutput());
		}
		//mpg123 1.13.6
		if(preg_match("/mpg[123]{3}.*(\d+.\d+.\d+).*/",$process->getOutput(),$matches)) {
			return $matches[1];
		} else {
			throw new \Exception("Unable to parse version");
		}
	}

	public function convert($newFilename,$extension,$mime) {
		switch($extension) {
			case "wav":
				$process = new Process($this->binary.' -r '.escapeshellarg($this->options['samplerate']).' -m -w '.escapeshellarg($newFilename).' '.escapeshellarg($this->track));
			break;
			default:
				throw new \Exception("Invalid type of $extension sent to MPG123");
			break;
		}
		if(!$this->background) {
			$process->run();
			if (!$process->isSuccessful()) {
				throw new \RuntimeException($process->getErrorOutput());
			}
			if(!file_exists($newFilename)) {
				$o = $process->getOutput();
				throw new \RuntimeException($o);
			}
		} else {
			$process->start();
			if (!$process->isRunning()) {
				throw new \RuntimeException($process->getErrorOutput());
			}
		}
	}
}
