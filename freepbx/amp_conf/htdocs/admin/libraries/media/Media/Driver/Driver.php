<?php
namespace Media\Driver;

abstract class Driver {
	/**
	 * Check if said binary is installed
	 * @return boolean If installed or not
	 */
	public static function installed() {
		return false;
	}

	/**
	 * Supported Codecs
	 * @param  array $formats Formats pass-in
	 * @return array          The formats
	 */
	public static function supportedCodecs(&$formats) {
		return $formats;
	}

	/**
	 * Is a single codec supported
	 * @param  string  $codec     The codec name
	 * @param  string  $direction Direction: in or out
	 * @return boolean            true if supported
	 */
	public static function isCodecSupported($codec,$direction) {
		return false;
	}

	/**
	 * Load track
	 * @param  [type] $track [description]
	 * @return [type]        [description]
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
	 * Get version of program
	 * @return string the version
	 */
	public function getVersion() {
		return "0";
	}

	/**
	 * Convert a file to another format
	 * @param  string $newFilename The full path to the new filename
	 * @param  string $extension   The extension to convert to
	 * @param  string $mime        The mime type to convert to
	 */
	public function convert($newFilename,$extension,$mime) {
	}
}
