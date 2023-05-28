<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * This class will determine the codecs that are avalible for use in FreePBX
 * from Asterisk, it will first try to query Asterisk itself with a fallback
 * to our hard coded defaults
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
namespace FreePBX;
class Codecs {

	private $cache = array();

	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new Exception("Not given a FreePBX Object");
		}
		$this->freepbx = $freepbx;
		$this->astman = $this->freepbx->astman;
	}

	/**
	 * Get all Avalible Codecs
	 * @return array Array of usable Codecs
	 */
	public function getAll() {
		$codecs['audio'] = $this->getAudio();
		$codecs['video'] = $this->getVideo();
		$codecs['text'] = $this->getText();
		$codecs['image'] = $this->getImage();
		$codecs['all'] = array_merge($this->getAudio(),  $this->getVideo(),  $this->getText(), $this->getImage());
		return $codecs;
	}

	/**
	 * Get all usable Video Codecs
	 * @param {bool} $defaults = false Whether to define the initial default ordering
	 */
	public function getVideo($defaults = false) {
		if(empty($this->cache['video'])) {
			$codecs = (is_object($this->astman) && $this->astman->connected()) ? $this->astman->Codecs('video') : array();
			if(!empty($codecs)) {
				$ret = array();
				foreach($codecs as $codec) {
					$ret[$codec] = false;
				}
			} else {
				$ret = array(
					"h261" => false,
					"h263" => false,
					"h263p" => false,
					"h264" => false,
					"mpeg4" => false,
					"vp8" => false
				);
			}
			$this->cache['video'] = $ret;
		}

		$ret = $this->cache['video'];

		if ($defaults) {
			$ret['h264'] = "1";
			$ret['mpeg4'] = "2";
		}

		return $ret;
	}

	/**
	* Get all usable Audio Codecs
	* @param {bool} $defaults = false Whether to define the initial default ordering
	*/
	public function getAudio($defaults = false) {
		if(empty($this->cache['audio'])) {
			$codecs = ($this->astman->connected()) ? $this->astman->Codecs('audio') : array();
			if(!empty($codecs)) {
				$ret = array();
				foreach($codecs as $codec) {
					$ret[$codec] = false;
				}
			} else {
				$ret = array(
					"g722" => false,
					"ulaw" => false,
					"alaw" => false,
					"gsm" => false,
					"g729" => false,
					"g723" => false,
					"g726" => false,
					"adpcm" => false,
					"slin" => false,
					"lpc10" => false,
					"speex" => false,
					"speex16" => false,
					"ilbc" => false,
					"g726aal2" => false,
					"slin16" => false,
					"siren7" => false,
					"siren14" => false,
					"testlaw" => false,
					"g719" => false,
					"speex32" => false,
					"slin12" => false,
					"slin24" => false,
					"slin32" => false,
					"slin44" => false,
					"slin48" => false,
					"slin96" => false,
					"slin192" => false,
					"opus" => false
				);
			}
			$this->cache['audio'] = $ret;
		}

		$ret = $this->cache['audio'];

		if ($defaults) {
			$ret['ulaw'] = "1";
			$ret['alaw'] = "2";
			$ret['gsm'] = "3";
		}

		return $ret;
	}

	/**
	* Get all usable Text Codecs
	* @param {bool} $defaults = false Whether to define the initial default ordering
	*/
	public function getText($defaults = false) {
		if(empty($this->cache['text'])) {
			$codecs = ($this->astman->connected()) ? $this->astman->Codecs('text') : array();
			if(!empty($codecs)) {
				$ret = array();
				foreach($codecs as $codec) {
					$ret[$codec] = false;
				}
			} else {
				$ret = array(
					"red" => false,
					"t140" => false
				);
			}
			$this->cache['text'] = $ret;
		}

		$ret = $this->cache['text'];

		if ($defaults) {

		}

		return $ret;
	}

	/**
	* Get all usable Image Codecs
	* @param {bool} $defaults = false Whether to define the initial default ordering
	*/
	public function getImage($defaults = false) {
		if(empty($this->cache['image'])) {
			$codecs = ($this->astman->connected()) ? $this->astman->Codecs('image') : array();
			if(!empty($codecs)) {
				$ret = array();
				foreach($codecs as $codec) {
					$ret[$codec] = false;
				}
			} else {
				$ret = array(
					"jpeg" => false,
					"png" => false
				);
			}
			$this->cache['image'] = $ret;
		}

		$ret = $this->cache['image'];

		if ($defaults) {

		}

		return $ret;
	}
}
