<?php

/*
 * (c)2016 Andrew Nagy / https://github.com/tm1000
 * (c)2012 Rob Janssen / https://github.com/RobThree
 *
 * Based on https://gist.github.com/Xeoncross/3515883
 */

namespace Media\Driver\Drivers;
use Symfony\Component\Process\Process;

class SangomaRingtone extends \Media\Driver\Driver {
	private $track;
	private $version;
	private $mime;
	private $extension;
	public $background = false;

	private static $HEADER_LENGTH = 512; // Header size is fixed

	public function __construct($filename,$extension,$mime,$samplerate=8000,$channels=1,$bitrate=16) {
		$this->loadTrack($filename);
		$this->version = $this->getVersion();
		$this->mime = $mime;
		$this->extension = $extension;
	}

	public static function supportedCodecs(&$formats) {
		$formats["out"]["sng"] = "sng";
		return $formats;
	}

	public static function isCodecSupported($codec,$direction) {
		return $direction == "out" && in_array($codec,array("sng"));
	}

	public static function installed() {
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
		return "1.0.0";
	}

	public function convert($newFilename,$extension,$mime) {
		self::AddHeaders($this->track, $newFilename, array("Version" => 1));
	}


	/**
	 * ReadFile reads the headers (and optionally the data) from a Sangoma ringtone file
	 *
	 * @param    string  $filename   The file to read the header from
	 * @param    bool    $readdata   (OPTIONAL) Pass TRUE to read the actual audio data from the file; defaults to FALSE
	 *
	 * @return   mixed               Information parsed from the file; FALSE when file doesn't exists or not readable
	 */
	private static function ReadFile($filename, $readdata = false) {
		//Make sure file is readable and exists
		if (is_readable($filename)) {
			$filesize = filesize($filename);

			//Make sure filesize is sane; e.g. at least the headers should be able to fit in it
			if ($filesize<self::$HEADER_LENGTH) {
				return false;
			}


			//Read the header and stuff all info in an array
			$handle = fopen($filename, 'rb');
			$bin = array(
				'header' => array(
					"FileLen" => self::readWord32($handle),
					"CheckSum" => self::readWord16($handle),
					"Version" => self::readWord32($handle),
					"Year" => self::readWord16($handle),
					"Month" => self::readChar($handle),
					"Day" => self::readChar($handle),
					"Hour" => self::readChar($handle),
					"Minute" => self::readChar($handle),
					"VersionName" => self::readString($handle, 16),
					"UnUsed" => self::readString($handle, 480)
				)
			);

			if ($readdata) {
				$bin['samples']['data'] = fread($handle, $filesize - self::$HEADER_LENGTH);
			}

			fclose($handle);
			return $bin;
		}

		//File is not readable or doesn't exist
		return false;
	}

	/**
	 * Create and add headers to a Sangoma ringtone file
	 * @param string $oldFilename The path to the old filename
	 * @param string $newFilename The path to the new filename
	 * @param array  $headers     Headers to add
	 */
	private static function AddHeaders($oldFilename, $newFilename, $headers = array()) {
		//Make sure file is readable and exists
		if (is_readable($oldFilename)) {
			$filesize = filesize($oldFilename);

			$fh = fopen($oldFilename, 'rb');
			$data = fread($fh, $filesize);
			fclose($fh);

			$str = call_user_func_array("pack", array(
				"NnNnCCCCa16a480a*",
				!empty($headers['FileLen']) ? $headers['FileLen'] : ceil(($filesize + self::$HEADER_LENGTH) / 2),
				!empty($headers['CheckSum']) ? $headers['CheckSum'] : '',
				!empty($headers['Version']) ? $headers['Version'] : '',
				!empty($headers['Year']) ? $headers['Year'] : '',
				!empty($headers['Month']) ? $headers['Month'] : '',
				!empty($headers['Day']) ? $headers['Day'] : '',
				!empty($headers['Hour']) ? $headers['Hour'] : '',
				!empty($headers['Minute']) ? $headers['Minute'] : '',
				!empty($headers['VersionName']) ? $headers['VersionName'] : '',
				!empty($headers['UnUsed']) ? $headers['UnUsed'] : '',
				$data
			));

			$fh = fopen($newFilename, 'wb');
			fwrite($fh, $str);
			fclose($fh);
		}
	}

	/**
	 * Modify headers of bin file
	 * @param string $oldFilename The path to the old filename
	 * @param string $newFilename The path to the new filename
	 * @param array  $headers     Headers to modify
	 */
	private static function ModifyHeaders($oldFilename, $newFilename, $headers = array()) {
		$data = self::ReadFile($oldFilename,true);
		$str = call_user_func_array("pack", array(
			"NnNnCCCCa16a480a*",
			!empty($headers['FileLen']) ? $headers['FileLen'] : $data['header']['FileLen'],
			!empty($headers['CheckSum']) ? $headers['CheckSum'] : $data['header']['CheckSum'],
			!empty($headers['Version']) ? $headers['Version'] : $data['header']['Version'],
			!empty($headers['Year']) ? $headers['Year'] : $data['header']['Year'],
			!empty($headers['Month']) ? $headers['Month'] : $data['header']['Month'],
			!empty($headers['Day']) ? $headers['Day'] : $data['header']['Day'],
			!empty($headers['Hour']) ? $headers['Hour'] : $data['header']['Hour'],
			!empty($headers['Minute']) ? $headers['Minute'] : $data['header']['Minute'],
			!empty($headers['VersionName']) ? $headers['VersionName'] : $data['header']['VersionName'],
			!empty($headers['UnUsed']) ? $headers['UnUsed'] : $data['header']['UnUsed'],
			$data['samples']['data']
		));
		$fh = fopen($newFilename, 'wb');
		fwrite($fh, $str);
		fclose($fh);
	}

	/**
	 * Reads a string from the specified file handle
	 *
	 * @param    int     $handle     The filehandle to read the string from
	 * @param    int     $length     The number of bytes to read
	 *
	 * @return   string              The string read from the file
	 */
	private static function readString($handle, $length) {
		return self::readUnpacked($handle, 'a*', $length);
	}

	/**
	 * Reads a 32bit unsigned integer from the specified file handle
	 *
	 * @param    int     $handle     The filehandle to read the 32bit unsigned integer from
	 *
	 * @return   int                 The 32bit unsigned integer read from the file
	 */
	private static function readWord32($handle) {
		return self::readUnpacked($handle, 'N', 4);
	}

	/**
	 * Reads a 16bit unsigned integer from the specified file handle
	 *
	 * @param    int     $handle     The filehandle to read the 16bit unsigned integer from
	 *
	 * @return   int                 The 16bit unsigned integer read from the file
	 */
	private static function readWord16($handle) {
		return self::readUnpacked($handle, 'n', 2);
	}

	/**
	 * Reads an unsigned char from the specified file handle
	 *
	 * @param    int     $handle     The filehandle to read the unsigned char from
	 *
	 * @return   int                 The unsigned char read from the file
	 */
	private static function readChar($handle) {
		return self::readUnpacked($handle, 'C', 1);
	}

	/**
	 * Reads the specified number of bytes from a specified file handle and unpacks it accoring to the specified type
	 *
	 * @param    int     $handle     The filehandle to read the data from
	 * @param    int     $type       The type of data being read (see PHP's Pack() documentation)
	 * @param    int     $length     The number of bytes to read
	 *
	 * @return   mixed               The unpacked data read from the file
	 */
	private static function readUnpacked($handle, $type, $length) {
		$r = unpack($type, fread($handle, $length));
		return array_pop($r);
	}
}
