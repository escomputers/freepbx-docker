<?php
namespace FreePBX;
/**
 * Media Class for FreePBX
 * Deals with converting to various formats
 * Also deals with generating HTML5 formats
 */
use Media\Media as MM;
use Sinergi\BrowserDetector\Browser;
use Sinergi\BrowserDetector\Os;
class Media extends DB_Helper{
	private $file;
	private $path;
	private $html5Path;
	private $supported;
	// See comment on detectSupportedFormats as to why the order is important
	// here
	private $html5Formats = array('oga', 'm4a', 'mp3', 'wav');

	public function __construct($freepbx = null, $track = null) {
		if ($freepbx == null) {
			throw new Exception("Not given a FreePBX Object");
		}
		$this->FreePBX = $freepbx;

		$dir = $this->FreePBX->Config->get("AMPPLAYBACK");
		if(!file_exists($dir)) {
			mkdir($dir,0777,true);
			$ampowner = $this->FreePBX->Config->get('AMPASTERISKWEBUSER');
			$ampgroup =  $ampowner != $this->FreePBX->Config->get('AMPASTERISKUSER') ? $this->FreePBX->Config->get('AMPASTERISKGROUP') : $ampowner;
			chown($dir, $ampowner);
			chgrp($dir, $ampgroup);
		}
		$this->html5Path = $dir;
	}

	/**
	 * Return the array of all HTML5 formats. Formats are returned in order from
	 * most preferred to least preferred.
	 *
	 * @return array Array of HTML5 format strings
	 */
	public function getAllHTML5Formats() {
		return $this->html5Formats;
	}

	/**
	 * Get supported HTML5 formats. Formats are returned in order from most
	 * preferred to least preferred.
	 * @param boolean Return all supports formats or just the first one
	 * @param array $forceFormats If non-empty, the list of formats to use instead
	 *                            of determining formats based on user-agent header.
	 * @return array Return array of formats
	 */
	public function getSupportedHTML5Formats($returnAll=false, $forceFormats=array()) {
		if(!empty($forceFormats) && is_array($forceFormats)) {
			$browser = $forceFormats;
		} elseif (isset($_SERVER['HTTP_USER_AGENT'])) {
			$browser = $this->detectSupportedFormats();
		} else {
			// probably running from console
			$browser = $this->html5Formats;
		}
		$formats = $this->getSupportedFormats();
		$html5 = $this->html5Formats;
		$final = array();
		$missing = array();
		$unsupported = array();
		foreach($html5 as $i) {
			if(in_array($i,$browser) && in_array($i,$formats['out'])) {
				$final[] = $i;
			} elseif(in_array($i,$browser) && !in_array($i,$formats['out'])) {
				$missing[] = $i;
			} else {
				$unsupported[] = $i;
			}
		}

		$nt = notifications::create();
		$mmm = $this->getConfig('mediamissingmessage');
		if(!empty($missing) && empty($mmm)) {
			$brand = $this->FreePBX->Config->get("DASHBOARD_FREEPBX_BRAND");
			$nt->add_notice("framework", "missing_html5", _("Missing HTML5 format converters"), sprintf(_("You are missing support for the following HTML5 codecs: %s. To fully support HTML5 browser playback you will need to install programs that can not be distributed with %s. If you'd like to install the binaries needed for these conversions click 'Resolve' in the lower left corner of this message. You can also safely ignore this message but browser playback might not work in your browser."),implode(",",$missing),$brand), "http://wiki.freepbx.org/display/FOP/Installing+Media+Conversion+Libraries",true,true);
			$this->setConfig('mediamissingmessage',true);
		} elseif(empty($missing) && !empty($mmm)) {
			$nt->delete("framework", "missing_html5");
			$this->setConfig('mediamissingmessage', false);
		}
		if($returnAll) {
			return !empty($final) ? $final : array();
		} else {
			return !empty($final[0]) ? array($final[0]) : array();
		}
	}

	/**
	 * Get all supported formats
	 * @return array Array of all supported formats
	 */
	public function getSupportedFormats() {
		if(!empty($this->supported)) {
			return $this->supported;
		}
		$this->supported = MM::getSupportedFormats();
		return $this->supported;
	}

	/**
	 * Load file
	 * @param  string $filename Full path to audio file
	 */
	public function load($filename) {
		if(!file_exists($filename)) {
			throw new \Exception(sprintf(_("File '%s' does not exist"), $filename));
		}
		$this->path = $filename;
		$this->file = new MM($filename);
	}

	/**
	 * Generate an image from this audio file
	 * @param  string $image Full path to image
	 */
	public function generateImage($image) {
		if(!isset($this->file)) {
			throw new \Exception("You must first load an audio file");
		}
		$this->file->image = $image;
	}

	/**
	 * Convert a file to another format
	 * @param  string $newFilename The full path to the new file
	 */
	public function convert($newFilename) {
		session_write_close();
		$this->file->convert($newFilename);
	}

	/**
	 * Convert one file into multiple formats
	 * @param  string $newFilename The new file name (extension will be replaced)
	 * @param  array  $formats      Array of supported formats
	 */
	public function convertMultiple($newFilename,$formats=array()) {
		session_write_close();
		$this->file->convertMultiple($newFilename,$formats);
	}

	/**
	 * Forcefully generate formats supports by the system to later use
	 *
	 * @param array $forceFormats The formats to support
	 * @return array Array of converted formats
	 */
	public function generateHTML5Formats($forceFormats) {
		return $this->generateHTML5('',true, $forceFormats);
	}

	/**
	 * Generate HTML5 formats
	 * @param  string $dir Directory to output to, if not set will use default
	 * @param  boolean $multiple Generate multiple files
	 * @return array      Array of converted files
	 */
	public function generateHTML5($dir='',$multiple=false, $forceFormats=array()) {
		session_write_close();
		$dir = !empty($dir) ? $dir : $this->html5Path;
		if(!is_writable($dir)) {
			throw new \Exception("Path $dir is not writable");
		}
		$md5 = md5_file($this->path);
		$path_parts = pathinfo(basename($this->path));
		$name = $path_parts['filename'];
		$supportedFormats = $this->getSupportedHTML5Formats($multiple, $forceFormats);
		//because ogg and oga are interchangeable
		if(in_array('oga',$supportedFormats)) {
			$k = array_search("oga",$supportedFormats);
			$supportedFormats[$k] = "ogg";
		}
		$formats = $f = array("mp3" => "mp3","wav" => "wav","ogg" => "ogg","m4a" => "m4a");
		$file = $dir."/".$name."-".$md5;
		$file = str_replace(".","_",$file);
		$converted = array();
		foreach($f as $format) {
			if(in_array($format,$supportedFormats)) {
				if(file_exists($file.".".$format)) {
					unset($formats[$format]);
				}
				//FREEPBX-11538: url encode
				$converted[$format] = urlencode(basename($file.".".$format));
			}
		}
		//because ogg and oga are interchangeable
		if(isset($converted['ogg'])) {
			$converted['oga'] = $converted['ogg'];
			unset($converted['ogg']);
		}

		/** This is broken for some stupid reason **/
		//$this->generateImage($dir."/".$name."-".$md5.".png");
		$convert = array_intersect($formats,$supportedFormats);
		if(!empty($convert)) {
			$this->convertMultiple($file,$convert);
		}
		return $converted;
	}

	/**
	 * Stream HTML5 compatible file
	 * @param  string $filename The file name (relative)
	 * @param  boolean $download Whether to stream or download
	 */
	public function getHTML5File($filename, $download=false) {
		$filename = basename($filename);
		//Session write close because Safari slams us with requests
		//asking for 2 bytes before proceeding to then request the full file.
		//As is the case with PHP sessions are locked until the previous session
		//has completed. When the server is slammed multiple requests are
		//blocked, therefore we always close the session before streaming the file
		//http://konrness.com/php5/how-to-prevent-blocking-php-requests/
		session_write_close();
		$filename = $this->html5Path ."/".$filename;
		$format = pathinfo($filename, PATHINFO_EXTENSION);
		if (is_file($filename)){
			switch($format) {
				case "mp3":
					$ct = "audio/mpeg";
				break;
				case "m4a":
					$ct = "audio/mp4";
				break;
				case "wav":
					$ct = "audio/wav";
				break;
				case "oga":
				case "ogg":
					$ct = "audio/ogg";
					$format = "oga";
				break;
				default:
					throw new \Exception("I have no idea was this file is: $filename");
				break;
			}
			header("Content-type: ".$ct); // change mimetype
			if (!$download && isset($_SERVER['HTTP_RANGE'])){ // do it for any device that supports byte-ranges not only iPhone
				$size   = filesize($filename); // File size
				$length = $size;           // Content length
				$start  = 0;               // Start byte
				$end    = $size - 1;       // End byte

				// Now that we've gotten so far without errors we send the accept range header
				/* At the moment we only support single ranges.
				* Multiple ranges requires some more work to ensure it works correctly
				* and comply with the specifications: http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2
				*
				* Multirange support annouces itself with:
				* header('Accept-Ranges: bytes');
				*
				* Multirange content must be sent with multipart/byteranges mediatype,
				* (mediatype = mimetype)
				* as well as a boundry header to indicate the various chunks of data.
				*/
				header("Accept-Ranges: 0-$length");
				// header('Accept-Ranges: bytes');
				// multipart/byteranges
				// http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2
				if (isset($_SERVER['HTTP_RANGE'])){
					$c_start = $start;
					$c_end   = $end;

					// Extract the range string
					list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
					// Make sure the client hasn't sent us a multibyte range
					if (strpos($range, ',') !== false){
						header('HTTP/1.1 416 Requested Range Not Satisfiable');
						header("Content-Range: bytes $start-$end/$size");
						exit;
					}
					// If the range starts with an '-' we start from the beginning
					// If not, we forward the file pointer
					// And make sure to get the end byte if specified
					if ($range[0] == '-'){
						// The n-number of the last bytes is requested
						$c_start = $size - substr($range, 1);
					} else {
						$range  = explode('-', $range);
						$c_start = $range[0];
						$c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
					}
					/* Check the range and make sure it's treated according to the specs.
					* http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
					*/
					// End bytes can not be larger than $end.
					$c_end = ($c_end > $end) ? $end : $c_end;
					// Validate the requested range and return an error if it's not correct.
					if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size){
						header('HTTP/1.1 416 Requested Range Not Satisfiable');
						header("Content-Range: bytes $start-$end/$size");
						// (?) Echo some info to the client?
						exit;
					}

					$start  = $c_start;
					$end    = $c_end;
					$length = $end - $start + 1; // Calculate new content length
					header('HTTP/1.1 206 Partial Content');
				}

				// Notify the client the byte range we'll be outputting
				header("Content-Range: bytes $start-$end/$size");
				header("Content-Length: $length");
				header('Content-Disposition: attachment;filename="' . basename($filename).'"');

				$buffer = 1024 * 8;
				ob_end_clean();
				ob_start();
				set_time_limit(0);
				while(true) {
					$fp = fopen($filename, "rb");
					fseek($fp, $start);
					if(!feof($fp) && ($p = ftell($fp)) <= $end) {
						if ($p + $buffer > $end) {
							$buffer = $end - $p + 1;
						}
						$contents = fread($fp, $buffer);
						$start = $start + $buffer;
						fclose($fp);
						echo $contents;
						ob_flush();
						flush();
					} else {
						fclose($fp);
						break;
					}
				}
			} else {
				header("Content-length: " . filesize($filename));
				header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
				header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
				header('Content-Disposition: attachment;filename="' . basename($filename).'"');
				set_time_limit(0);
				$fp = fopen($filename, "rb");
				fpassthru($fp);
				ob_flush();
				flush();
				fclose($fp);
			}
		}
	}

	/**
	 * Detect what this browser supports to minimize processing
	 * Sort by priority of codec to use
	 * -Patent Unencumbered first
	 * -Patent encumbered in the middle, first m4a as it's not as bad as mp3
	 * -Wav always last (as it's filesize is HUGE)
	 * Used: html5test.com/results/search.html
	 * @return array array of browser supported formats
	 */
	private function detectSupportedFormats() {
		$browser = new Browser();
		$formats = array();
		switch($browser->getName()) {
			case Browser::OPERA:
			case Browser::KONQUEROR:
			case Browser::FIREBIRD:
			case Browser::FIREFOX:
			case Browser::SEAMONKEY:
			case Browser::ICEWEASEL:
			case Browser::MOZILLA:
			case Browser::CHROME:
			case Browser::BLACKBERRY:
				$formats = $this->html5Formats;
			break;
			case Browser::ICAB:
			case Browser::NOKIA_S60:
			case Browser::NOKIA:
			case Browser::EDGE:
			case Browser::YANDEX:
			case Browser::PHOENIX:
			case Browser::SAFARI:
				$formats = array("m4a","mp3","wav");
			break;
			case Browser::VIVALDI:
			case Browser::SHIRETOKO:
			case Browser::ICECAT:
				$formats = array("oga","wav");
			break;
			case Browser::IE:
			case Browser::POCKET_IE:
				$formats = array("m4a","mp3");
			break;
			case Browser::OMNIWEB:
				$formats = array("mp3","wav");
			break;
			case Browser::WEBTV:
			case Browser::OPERA_MINI:
			case Browser::AMAYA:
			case Browser::LYNX:
			case Browser::NAVIGATOR:
			case Browser::NETSCAPE_NAVIGATOR:
			case Browser::GOOGLEBOT:
			case Browser::SLURP:
			case Browser::W3CVALIDATOR:
			case Browser::MSNBOT:
			case Browser::GALEON:
			case Browser::MSN:
			case Browser::NETPOSITIVE:
			case Browser::GSA:
				$formats = array();
			break;
			default: //not sure of the browser type so check OS
				$os = new Os();
				switch(true) {
					case preg_match('/ios/', $_SERVER['HTTP_USER_AGENT']):
					case $os->getName() === Os::IOS:
						$formats = array("m4a");
					break;
					case preg_match('/android/', $_SERVER['HTTP_USER_AGENT']):
					case $os->getName() === Os::ANDROID:
						$formats = array("oga");
					break;
					default: //not sure of the browser or os type so just do them all
						$formats = $this->html5Formats;
					break;
				}

			break;
		}
		return $formats;
	}
	public function getMIMEtype($file){
		$mimetype = 'application/octet-stream';
		$fileExt = pathinfo($file, PATHINFO_EXTENSION);
		$astMIME = array(
		  'mp3' => 'audio/mpeg',
		  'g723' => 'audio/G723',
		  'g723sf' => 'audio/G723',
		  'gsm' => 'audio/GSM',
		  'sln192' => 'audio/sln-192',
		  'sln96' => 'audio/sln-96',
		  'sln48' => 'audio/sln-48',
		  'sln44' => 'audio/sln-44',
		  'sln32' => 'audio/sln-32',
		  'sln24' => 'audio/sln-24',
		  'sln16' => 'audio/sln-16',
		  'sln12' => 'audio/sln-12',
		  'sln' => 'audio/sln',
		  'raw' => 'audio/x-raw',
		  'sirin14' => 'audio/siren7',
		  'sirin14' => 'audio/siren14',
		  'WAV' => 'audio/L16',
		  'wav49' => 'audio/GSM',
		  'wav16' => 'audio/L16',
		  'wav' => 'audio/x-wav',
		  'g719' => 'audio/G719',
		  'g726-16' => 'audio/G726-16',
		  'g726-24' => 'audio/G726-24',
		  'g726-32' => 'audio/G726-32',
		  'g726-40' => 'audio/G726-40',
		  'g722' => 'audio/G722',
		  'au' => 'audio/basic',
		  'alaw' => 'audio/x-alaw-basic',
		  'al' => 'audio/x-alaw-basic',
		  'alw' => 'audio/x-alaw-basic',
		  'pcm' => 'audio/basic',
		  'ulaw' => 'audio/basic',
		  'ul' => 'audio/basic',
		  'mu' => 'audio/basic',
		  'ulw' => 'audio/basic',
		  'ogg' => 'audio/ogg',
		);
		if (array_key_exists($fileExt, $astMIME)) {
    	$mimetype = $astMIME[$fileExt];
		}
		return $mimetype;
	}
}
