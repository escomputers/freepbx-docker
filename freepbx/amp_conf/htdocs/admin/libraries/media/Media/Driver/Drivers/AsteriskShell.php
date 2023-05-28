<?php

namespace Media\Driver\Drivers;
use Symfony\Component\Process\Process;

class AsteriskShell extends \Media\Driver\Driver {
	private $track;
	private $version;
	private $mime;
	private $extension;
	public $background = false;
	static $supported;
	private $options = array(
		"samplerate" => 48000
	);
	private $binary = 'asterisk';

	public function __construct($filename,$extension,$mime,$samplerate=48000,$channels=1,$bitrate=16) {
		$this->loadTrack($filename);
		$this->mime = $mime;
		$this->extension = $extension;
		$this->options['samplerate'] = $samplerate;
		$loc = fpbx_which("asterisk");
		if(!empty($loc)) {
			$this->binary = $loc;
		}
		$this->version = $this->getVersion();
	}

	/**
	 * Check if Asterisk is installed
	 * @return string The version
	 */
	public static function installed() {
		$loc = fpbx_which("asterisk");
		$process = new Process($loc.' -V');
		$process->run();

		// executes after the command finishes
		if (!$process->isSuccessful()) {
			return false;
		}
		return true;
	}

	/**
	 * Query Asterisk for the supported formats
	 * @param  array $formats Previously supported formats
	 * @return array          Array of formats
	 */
	public static function supportedCodecs(&$formats) {
		if(!empty(self::$supported)) {
			return self::$supported;
		}
		$ver = \FreePBX::Config()->get("ASTVERSION");
		$loc = fpbx_which("asterisk");
		exec($loc." -rx 'core show file formats'",$lines,$ret);
		foreach($lines as $line) {
			if(preg_match('/([a-z0-9\|\-]*)$/i',$line,$matches)) {
				$l = trim($matches[1]);
				$codecs = explode("|",$matches[1]);
				foreach($codecs as $codec) {
					if(in_array($codec,array('Extensions','----------'))) {
						continue;
					}
					if(!in_array($codec,array('gsm', 'g722', 'alaw', 'ulaw', 'sln', 'wav16', 'WAV', 'sln12', 'sln16', 'sln24', 'sln32', 'sln44', 'sln48', 'sln96', 'sln192'))) {
						continue;
					}
					//Asterisk 11 should support sln48 but it doesnt, it says it does but then complains
					//It might be a bug, regardless this is fixed in 13 people should just use it
					if(version_compare_freepbx($ver,"13.0","lt") && $codec == 'sln48') {
						continue;
					}
					$formats["in"][$codec] = $codec;
					$formats["out"][$codec] = $codec;
				}
			}
		}
		$lines = null;
		exec($loc." -rx 'g729 show licenses'",$lines,$ret);
		foreach($lines as $line) {
			if(preg_match('/licensed channels are currently in use/',$line)) {
				$formats["in"]['g729'] = 'g729';
				$formats["out"]['g729'] = 'g729';
			}
		}
		$formats["out"]["wav"] = "wav";
		$formats["out"]["WAV"] = "WAV";
		$formats["in"]["wav"] = "wav";
		$formats["in"]["WAV"] = "WAV";

		if(isset($formats['out']['sln48']) && isset($formats['in']['sln48'])) {
			exec($loc." -rx 'core show translation paths slin 48000'",$lines,$ret);
			foreach($lines as $line) {
				$line = trim($line);
				if(preg_match('/slin:48000\s+To\s+slin:8000\s+:\s+(.*)/i',$line,$matches)) {
					if(strtolower($matches[1]) == "no translation path") {
						unset($formats['out']['sln48']);
						unset($formats['in']['sln48']);
					}
					break;
				}
			}
		}

		self::$supported = $formats;
		return self::$supported;
	}

	/**
	 * Check to see if a single format is supported by Asterisk
	 * @param  string  $codec     The codec
	 * @param  string  $direction The direction: in or out
	 * @return boolean            If it's supported or not
	 */
	public static function isCodecSupported($codec,$direction) {
		$formats = array();
		$formats = self::supportedCodecs($formats);
		return in_array($codec, $formats[$direction]);
	}

	/**
	 * Load path, make sure it's valid
	 * @param  string $track The full path to the file
	 */
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

	/**
	 * Get the version of Asterisk
	 * @return string The version
	 */
	public function getVersion() {
		$process = new Process($this->binary.' -V');
		$process->run();

		// executes after the command finishes
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getErrorOutput());
		}
		//sox: Asterisk 13.5.0
		if(preg_match("/Asterisk (.*)/",$process->getOutput(),$matches)) {
			return $matches[1];
		} else {
			throw new \Exception("Unable to parse version");
		}
	}

	/**
	 * Convert file to format using Asterisk
	 * @param  string $newFilename The full path to the new file
	 * @param  string $extension   The new extension
	 * @param  string $mime        Mime type
	 */
	public function convert($newFilename,$extension,$mime) {
		$process = new Process($this->binary." -rx ".escapeshellarg(escapeshellcmd('file convert "'.$this->track.'" "'.$newFilename.'"')));
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
