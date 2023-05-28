<?php
/**
* This is the User Control Panel Object.
*
* Copyright (C) 2013 Schmooze Com, INC
* Copyright (C) 2013 Andrew Nagy <andrew.nagy@schmoozecom.com>
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as
* published by the Free Software Foundation, either version 3 of the
* License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* @package   FreePBX UCP BMO
* @author   Andrew Nagy <andrew.nagy@schmoozecom.com>
* @license   AGPL v3
*/
namespace UCP\Modules;
use \UCP\Modules as Modules;

class Cdr extends Modules{
	protected $module = 'Cdr';
	private $activeConferences = array();
	private $limit = 15;
	private $break = 5;

	function __construct($Modules) {
		$this->Modules = $Modules;
		$this->cdr = $this->UCP->FreePBX->Cdr;
		$this->user = $this->UCP->User->getUser();
		if($this->UCP->Session->isMobile || $this->UCP->Session->isTablet) {
			$this->limit = 7;
		}
	}

	public function getWidgetList() {
		$widgets = array();

		$user = $this->UCP->User->getUser();
		$enabled = $this->UCP->getCombinedSettingByID($user['id'],'Cdr','enable');
		if (!$enabled) {
			return array();
		}
		$extensions = $this->UCP->getCombinedSettingByID($user['id'],'Cdr','assigned');

		if (!empty($extensions)) {
			foreach($extensions as $extension) {
				$data = $this->UCP->FreePBX->Core->getDevice($extension);
				if(empty($data) || empty($data['description'])) {
					$data = $this->UCP->FreePBX->Core->getUser($extension);
					$name = $data['name'];
				} else {
					$name = $data['description'];
				}

				$widgets[$extension] = array(
					"display" => $name,
					"description" => sprintf(_("Call History for %s"),$name),
					"defaultsize" => array("height" => 7, "width" => 6),
					"minsize" => array("height" => 6, "width" => 3)
				);
			}
		}

		if (empty($widgets)) {
			return array();
		}

		return array(
			"rawname" => "cdr",
			"display" => _("Call History"),
			"icon" => "fa fa-hourglass-half",
			"list" => $widgets
		);
	}

	public function getStaticSettings() {
		$sf = $this->UCP->FreePBX->Media->getSupportedFormats();
		return array(
			"showPlayback" => $this->_checkPlayback() ? "1" : "0",
			"showDownload" => $this->_checkDownload() ? "1" : "0",
			"supportedHTML5" => implode(",",$this->UCP->FreePBX->Media->getSupportedHTML5Formats())
		);
	}

	public function getWidgetDisplay($id,$uuid) {
		if (!$this->_checkExtension($id)) {
			return array();
		}

		$displayvars = array(
			'ext' => $id,
			'activeList' => $view,
			'calls' => $this->postProcessCalls($this->cdr->getCalls($id, 1, 'desc', 'date', '', $this->limit), $id),
			"showPlayback" => $this->_checkPlayback(),
			"showDownload" => $this->_checkDownload(),
			"extension" => $id,
			"supportedHTML5" => implode(",",$this->UCP->FreePBX->Media->getSupportedHTML5Formats())
		);

		$html.= $this->load_view(__DIR__.'/views/widget.php',$displayvars);

		$display = array(
			'title' => _("Call History"),
			'html' => $html
		);

		return $display;
	}

	function poll($data) {
		return array('status' => false);
	}

	/**
	* Determine what commands are allowed
	*
	* Used by Ajax Class to determine what commands are allowed by this class
	*
	* @param string $command The command something is trying to perform
	* @param string $settings The Settings being passed through $_POST or $_PUT
	* @return bool True if pass
	*/
	function ajaxRequest($command, $settings) {
		switch($command) {
			case 'grid':
				return true;
			break;
			case 'download':
				return $this->_checkDownload($_REQUEST['ext']);
			break;
			case 'gethtml5':
			case 'playback':
				return $this->_checkPlayback($_REQUEST['ext']);
			break;
			default:
				return false;
			break;
		}
	}

	/**
	* The Handler for all ajax events releated to this class
	*
	* Used by Ajax Class to process commands
	*
	* @return mixed Output if success, otherwise false will generate a 500 error serverside
	*/
	function ajaxHandler() {
		$return = array("status" => false, "message" => "");
		switch($_REQUEST['command']) {
			case "grid":
				$limit = filter_var($_REQUEST['limit'], FILTER_SANITIZE_NUMBER_INT);
				$ext = $_REQUEST['extension'];
				$order = $_REQUEST['order'];
				$orderby = !empty($_REQUEST['sort']) ? $_REQUEST['sort'] : "date";
				$search = !empty($_REQUEST['search']) ? $_REQUEST['search'] : "";
				$pages = $this->cdr->getPages($ext,$search,$limit);
				$offset = filter_var($_REQUEST['offset'], FILTER_SANITIZE_NUMBER_INT);
				$page = ($offset / $limit) + 1;
				$total = $this->cdr->getTotalCalls($ext,$search);
				$data = $this->postProcessCalls($this->cdr->getCalls($ext,$page,$orderby,$order,$search,$limit),$ext);
				return array(
					"total" => $total,
					"rows" => $data
				);
			break;
			case 'gethtml5':
				$media = $this->UCP->FreePBX->Media();
				$record = $this->UCP->FreePBX->Cdr->getRecordByIDExtension($_REQUEST['id'],$_REQUEST['ext']);
				if(!file_exists($record['recordingfile'])) {
					return array("status" => false, "message" => _("File does not exist"));
				}
				$media->load($record['recordingfile']);
				$files = $media->generateHTML5();
				$final = array();
				foreach($files as $format => $name) {
					$final[$format] = "index.php?quietmode=1&module=cdr&command=playback&file=".$name."&ext=".$_REQUEST['ext'];
				}
				return array("status" => true, "files" => $final);
			break;
			default:
				return false;
			break;
		}
		return $return;
	}

	/**
	* The Handler for quiet events
	*
	* Used by Ajax Class to process commands in which custom processing is needed
	*
	* @return mixed Output if success, otherwise false will generate a 500 error serverside
	*/
	function ajaxCustomHandler() {
		switch($_REQUEST['command']) {
			case "download":
				$msgid = $_REQUEST['msgid'];
				$ext = $_REQUEST['ext'];
				$this->downloadFile($msgid,$ext);
				return true;
			case "playback":
				$media = $this->UCP->FreePBX->Media();
				$media->getHTML5File($_REQUEST['file']);
				return true;
			break;
			default:
				return false;
			break;
		}
		return false;
	}

	private function postProcessCalls($calls,$self) {
		foreach($calls as &$call) {
			$app = strtolower($call['lastapp']);
			switch($app) {
				case 'dial':
					switch($call['disposition']) {
						case 'ANSWERED':
							if($call['src'] == $self || strpos($call['channel'], "/".$self."-") !== false) {
								$call['icons'][] = 'fa-arrow-right out';
								$device = $this->UCP->FreePBX->Core->getDevice($call['dst']);
								$call['text'] = !empty($device['description']) ? htmlentities('"'.$device['description'].'"' . " <".$call['dst'].">",ENT_COMPAT | ENT_HTML401, "UTF-8") : $call['dst'];
							} elseif($call['dst'] == $self || strpos($call['dstchannel'], "/".$self."-") !== false) {
								$call['icons'][] = 'fa-arrow-left in';
								$call['text'] = htmlentities($call['clid'],ENT_COMPAT | ENT_HTML401, "UTF-8");
							} elseif($call['cnum'] == $self) {
								$call['icons'][] = 'fa-arrow-right out';
								$call['text'] = htmlentities($call['dst'],ENT_COMPAT | ENT_HTML401, "UTF-8");
							} else {
								$call['icons'][] = 'fa-arrow-left in';
								$call['text'] = htmlentities($call['src'],ENT_COMPAT | ENT_HTML401, "UTF-8");
							}
						break;
						case 'NO ANSWER':
							//Remove the recording reference as these are almost always errors (from what I've seen)
							$call['recordingfile'] = '';
							if($call['src'] == $self || strpos($call['channel'], "/".$self."-") !== false) {
								$device = $this->UCP->FreePBX->Core->getDevice($call['dst']);
								$call['icons'][] = 'fa-arrow-right out';
								$call['icons'][] = 'fa-ban';
								$call['text'] = !empty($device['description']) ? htmlentities('"'.$device['description'].'"' . " <".$call['dst'].">",ENT_COMPAT | ENT_HTML401, "UTF-8") : $call['dst'];
							} elseif($call['dst'] == $self || strpos($call['dstchannel'], "/".$self."-") !== false) {
								$call['icons'][] = 'fa-ban';
								$call['icons'][] = 'fa-arrow-left in';
								$call['text'] = htmlentities($call['clid'],ENT_COMPAT | ENT_HTML401, "UTF-8");
							} elseif($call['cnum'] == $self) {
								$call['icons'][] = 'fa-arrow-right out';
								$call['text'] = htmlentities($call['dst'],ENT_COMPAT | ENT_HTML401, "UTF-8");
							} else {
								$call['text'] = htmlentities($call['src'],ENT_COMPAT | ENT_HTML401, "UTF-8");
							}
						break;
						case 'BUSY':
							if($call['src'] == $self || strpos($call['channel'], "/".$self."-") !== false) {
								$device = $this->UCP->FreePBX->Core->getDevice($call['dst']);
								$call['icons'][] = 'fa-arrow-right out';
								$call['icons'][] = 'fa-clock-o';
								$call['text'] = !empty($device['description']) ? htmlentities('"'.$device['description'].'"' . " <".$call['dst'].">",ENT_COMPAT | ENT_HTML401, "UTF-8") : $call['dst'];
							} elseif($call['dst'] == $self || strpos($call['dstchannel'], "/".$self."-") !== false) {
								$call['icons'][] = 'fa-ban';
								$call['icons'][] = 'fa-clock-o';
								$call['text'] = $call['clid'];
							} elseif($call['cnum'] == $self) {
								$call['icons'][] = 'fa-arrow-right out';
								$call['text'] = htmlentities($call['dst'],ENT_COMPAT | ENT_HTML401, "UTF-8");
							} else {
								$call['text'] = htmlentities($call['src'],ENT_COMPAT | ENT_HTML401, "UTF-8");
							}
						break;
					}
					if(!empty($call['text']) && preg_match('/LC\-(\d*)/i',$call['text'],$matches)) {
						$device = $this->UCP->FreePBX->Core->getDevice($matches[1]);
						$call['text'] = !empty($device['description']) ? htmlentities('"'.$device['description'].'"' . " <".$matches[1].">",ENT_COMPAT | ENT_HTML401, "UTF-8") : $matches[1];
					}
				break;
				case 'voicemail':
					if($call['src'] == $self) {
						$call['icons'][] = 'fa-arrow-right out';
						$call['icons'][] = 'fa-envelope';
						$call['text'] = htmlentities($call['dst'],ENT_COMPAT | ENT_HTML401, "UTF-8");
					} elseif($call['dst'] == $self) {
						$call['icons'][] = 'fa-envelope';
						$call['icons'][] = 'fa-arrow-left in';
						$call['text'] = htmlentities($call['clid'],ENT_COMPAT | ENT_HTML401, "UTF-8");
					} else {
						$call['icons'][] = 'fa-envelope';
						$call['text'] = htmlentities($call['src'],ENT_COMPAT | ENT_HTML401, "UTF-8");
					}
					if(preg_match('/^vmu(\d*)/i',$call['text'],$matches)) {
						$device = $this->UCP->FreePBX->Core->getDevice($matches[1]);
						$desc = !empty($device['description']) ? htmlentities('"'.$device['description'].'"' . " <".$matches[1].">",ENT_COMPAT | ENT_HTML401, "UTF-8") : $matches[1];
						$call['text'] = $desc . ' ' . _('Voicemail');
					} else {
						$id = trim($call['text']);
						$device = $this->UCP->FreePBX->Core->getDevice($id);
						$desc = !empty($device['description']) ? htmlentities('"'.$device['description'].'"' . " <".$id.">",ENT_COMPAT | ENT_HTML401, "UTF-8") : $id;
						$call['text'] = $desc . ' ' . _('Voicemail');
					}
				break;
				case 'confbridge':
				case 'meetme':
					if($call['src'] == $self) {
						$call['icons'][] = 'fa-arrow-right out';
						$call['icons'][] = 'fa-users';
						$conference = $this->UCP->FreePBX->Conferences->getConference($call['dst']);
						$call['text'] = _('Conference') . ' ' . (!empty($conference['description']) ? htmlentities('"'.$conference['description'].'"' . " <".$call['dst'].">",ENT_COMPAT | ENT_HTML401, "UTF-8") : $call['dst']);
					} elseif($call['dst'] == $self) {
						$call['icons'][] = 'fa-users';
						$call['icons'][] = 'fa-arrow-left in';
						$call['text'] = _('Conference') . ' ' . htmlentities($call['clid'],ENT_COMPAT | ENT_HTML401, "UTF-8");
					} else {
						$call['icons'][] = 'fa-users';
						$call['text'] = $call['src'];
					}
				break;
				case 'hangup':
					switch($call['dst']) {
						case 'STARTMEETME':
							$call['icons'][] = 'fa-users';
							$call['text'] = $call['src'] . ' ' . _('kicked from conference');
						break;
						case 'denied':
							$call['icons'][] = 'fa-ban';
							$call['text'] = $call['src'] . ' ' . _('denied by COS');
						break;
						default:
							if($call['src'] == $self) {
								$call['icons'][] = 'fa-arrow-right out';
								$call['icons'][] = 'fa-ban';
								$call['text'] = htmlentities($call['clid'],ENT_COMPAT | ENT_HTML401, "UTF-8");
							} elseif($call['dst'] == $self) {
								$call['icons'][] = 'fa-ban';
								$call['icons'][] = 'fa-arrow-left in';
								$call['text'] = htmlentities($call['clid'],ENT_COMPAT | ENT_HTML401, "UTF-8");
							} else {
								$call['text'] = _('Unknown') . ' (' . $call['uniqueid'] . ')';
							}
						break;
					}
				break;
				case 'playback':
					if($call['src'] == $self) {
						$call['icons'][] = 'fa-arrow-right out';
						$call['text'] = htmlentities($call['dst'],ENT_COMPAT | ENT_HTML401, "UTF-8");
					} else {
						$call['text'] = htmlentities($call['src'],ENT_COMPAT | ENT_HTML401, "UTF-8");
					}
				break;
				default:
					if($call['src'] == $self) {
						$call['icons'][] = 'fa-arrow-right out';
						$call['text'] = htmlentities($call['dst'],ENT_COMPAT | ENT_HTML401, "UTF-8");
					} elseif($call['dst'] == $self) {
						$call['icons'][] = 'fa-arrow-left in';
						$call['text'] = htmlentities($call['src'],ENT_COMPAT | ENT_HTML401, "UTF-8");
					} else {
						$call['text'] = _('Unknown') . ' (' . $call['uniqueid'] . ')';
					}
			}
			if(empty($call['text'])) {
				$call['text'] = _('Unknown') . ' (' . $call['uniqueid'] . ')';
			} else {
				$call['text'] = $this->cleanUTF8($call['text']);
				$call['text'] = preg_replace("/&lt;(.*)&gt;/i","&lt;<span class='clickable' data-type='number' data-primary='phone'>$1</span>&gt;",$call['text']);
			}
			$call['formattedTime'] = $this->UCP->View->getDateTime($call['timestamp']);
		}
		return $calls;
	}

	/**
	 * If you have come across the cursed ‘Invalid Character‘ error while
	 * using PHP’s XML or JSON parser then you may be interested in this.
	 *
	 * Unfortunately, PHP’s XML and JSON parsers do not ignore non-UTF8
	 * characters, but rather they stop and throw a rather unhelpful error.
	 * I found the below code form net and work excellently for me.
	 *
	 * http://stackoverflow.com/a/37438731
	 *
	 * @param  string $string String to check
	 * @return string         Fixed characters
	 */
	private function cleanUTF8($string) {
		//reject overly long 2 byte sequences, as well as characters above U+10000 and replace with ?
		$string = preg_replace('/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]'.
		 '|[\x00-\x7F][\x80-\xBF]+'.
		 '|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*'.
		 '|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})'.
		 '|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S',
		 '?', $string );

		//reject overly long 3 byte sequences and UTF-16 surrogates and replace with ?
		$string = preg_replace('/\xE0[\x80-\x9F][\x80-\xBF]'.
		 '|\xED[\xA0-\xBF][\x80-\xBF]/S','?', $string );

		 return $string;
	}

	/**
	 * Download a file to listen to on your desktop
	 * @param  string $msgid The message id
	 * @param  int $ext   Extension wanting to listen to
	 */
	private function downloadFile($msgid,$ext) {
		if(!$this->_checkExtension($ext)) {
			header("HTTP/1.0 403 Forbidden");
			echo _("Forbidden");
			exit;
		}
		$record = $this->UCP->FreePBX->Cdr->getRecordByIDExtension($msgid,$ext);
		if(!file_exists($record['recordingfile'])) {
			header("HTTP/1.0 404 Not Found");
			echo _("Not Found");
			exit;
		}
		$media = $this->UCP->FreePBX->Media;
		$mimetype = $media->getMIMEtype($record['recordingfile']);
		header("Content-length: " . filesize($record['recordingfile']));
		header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
		header('Content-Disposition: attachment;filename="' . basename($record['recordingfile']).'"');
		header('Content-type: ' . $mimetype);
		readfile($record['recordingfile']);
	}

	private function _checkExtension($extension) {
		$enabled = $this->UCP->getCombinedSettingByID($this->user['id'],'Cdr','enable');
		if(!$enabled) {
			return false;
		}
		$extensions = $this->UCP->getCombinedSettingByID($this->user['id'],'Cdr','assigned');
		return in_array($extension,$extensions);
	}

	private function _checkDownload($extension=null) {
		$enabled = $this->UCP->getCombinedSettingByID($this->user['id'],'Cdr','enable');
		if(!$enabled) {
			return false;
		}
		if(!is_null($extension) && $this->_checkExtension($extension)) {
			$dl = $this->UCP->getCombinedSettingByID($this->user['id'],'Cdr','download');
			return is_null($dl) ? true : $dl;
		} elseif(is_null($extension)) {
			return true;
		}
		return false;
	}

	private function _checkPlayback($extension=null) {
		$enabled = $this->UCP->getCombinedSettingByID($this->user['id'],'Cdr','enable');
		if(!$enabled) {
			return false;
		}
		if(!is_null($extension) && $this->_checkExtension($extension)) {
			$pb = $this->UCP->getCombinedSettingByID($this->user['id'],'Cdr','playback');
			return is_null($pb) ? true : $pb;
		} elseif(is_null($extension)) {
			return true;
		}
		return false;
	}
}
