<?php
// vim: set ai ts=4 sw=4 ft=php:
//
// This is the main interface to the CDR Database.
//
// It is used by multiple modules. Please don't alter it without
// running complete unit tests!
//
// The License for this FreePBX module can be found in the license file inside the
// module directory
//
// Copyright 2015, 2016 Sangoma Technologies Corporation

namespace FreePBX\modules;

class Cdr implements \BMO {

	/** Public variable for access to the raw PDO handle */
	public $cdrdb;

	/** Cache of the FreePBX BMO Object */
	private $FreePBX;

	/** CDR Table name, set in __construct */
	private $db_table;

	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception("Not given a FreePBX Object");
		}

		$this->FreePBX = $freepbx;

		// Variables to try. If the key is blank/unset, use the value instead.
		$vars = array(
			"CDRDBHOST" => "AMPDBHOST",
			"CDRDBPORT" => "AMPDBPORT",
			"CDRDBUSER" => "AMPDBUSER",
			"CDRDBPASS" => "AMPDBPASS",
			"CDRDBTYPE" => "AMPDBTYPE",
			// This is removed if unset
			"CDRDBSOCK" => "AMPDBSOCK",
			// Note - no default, we check later.
			"CDRDBNAME" => "CDRDBNAME",
			"CDRDBTABLENAME" => "CDRDBTABLENAME",
			"CDRUSEGMT" => "CDRUSEGMT",
		);

		$cdr = array();
		foreach ($vars as $conf => $default) {
			$tmp = \FreePBX::Config()->get($conf);
			// Is our config blank for this setting?
			if (!$tmp) {
				// How about the default?
				$defvalue = \FreePBX::Config()->get($default);
				if ($defvalue) {
					$cdr[$conf] = $defalue;
				} else {
					// Well that's blank. Is it part of FreePBX::$conf? (That's the parsed output of /etc/freepbx.conf)
					if (empty(\FreePBX::$conf[$default])) {
						// No. Set it to blank.
						$cdr[$conf] = "";
					} else {
						$cdr[$conf] = \FreePBX::$conf[$default];
					}
				}
			} else {
				// We have a setting
				$cdr[$conf] = $tmp;
			}
		}

		// If CDRDBNAME is blank, set it to asteriskcdrdb
		if (!$cdr['CDRDBNAME']) {
			$dsnarray = array("dbname" => "asteriskcdrdb");
		} else {
			$dsnarray = array("dbname" => $cdr['CDRDBNAME']);
        }

		// If we don't have a type (bogus install, possibly?), assume mysql
		if (!$cdr['CDRDBTYPE']) {
			$engine = "mysql";
		} else {
			// The db 'type' name can be wrong. Remap it to the correct one if it is
			if ($cdr['CDRDBTYPE'] == "postgres") {
				$engine = "pgsql";
			} else {
				$engine = $cdr['CDRDBTYPE'];
			}
		}

		// If we have a socket, we don't want host and port.
		if ($cdr['CDRDBSOCK']) {
			$dsnarray['unix_socket'] = $cdr['CDRDBSOCK'];
		} else {
			$dsnarray['host'] = $cdr['CDRDBHOST'];
			// Do we have a port?
			if ($cdr['CDRDBPORT']) {
				$dsnarray['port'] = $cdr['CDRDBPORT'];
			}
		}

		// If there's no cdrdbtablename, set it to cdr
		if (!$cdr['CDRDBTABLENAME']) {
			$this->db_table = "cdr";
		} else {
			$this->db_table = $cdr['CDRDBTABLENAME'];
		}

		// If this is sqlite, ignore everything we've just done.
		if (strpos($engine, "sqlite") === 0) {
			// This is our raw parsed variables from /etc/freepbx.conf
			$ampconf = \FreePBX::$amp_conf;
			if (isset($amp_conf['cdrdatasource'])) {
				$dsn = "$engine:".$amp_conf['cdrdatasource'];
			} elseif (!empty($amp_conf['datasource'])) {
				$dsn = "$engine:".$amp_conf['datasource'];
			} else {
				throw new \Exception("Datasource set to sqlite, but no cdrdatasource or datasource provided");
			}
			$user = "";
			$pass = "";
		} else {
			// Not SQLite.
			$user = $cdr["CDRDBUSER"];
			$pass = $cdr["CDRDBPASS"];

			// Note - http_build_query() is a simple shortcut to change a key=>value array
			// to a string.
			$dsn = "$engine:".http_build_query($dsnarray, '', ';');
		}
		// Now try to get a DB handle using our DSN
		try {
			$this->cdrdb = new \Database($dsn, $user, $pass);
		} catch(\Exception $e) {
			die("Unable to connect to CDR Database using dsn '$dsn' with user '$user' and password '$pass' - ".$e->getMessage());
		}
		//Set the CDR session timezone to GMT if CDRUSEGMT is true
		if (isset($cdr["CDRUSEGMT"]) && $cdr["CDRUSEGMT"]) {
			$sql = "SET time_zone = '+00:00'";
			$sth = $this->cdrdb->prepare($sql);
			$sth->execute();
		}
	}

	public function getCdrDbHandle() {
		// Simply returns the DB Handle created in __construct
		return $this->cdrdb;
	}

	public function ucpDelGroup($id,$display,$data) {
	}

	/* UCP template to get the user assigned vm extension details
	* @defaultexten is the default_extensionof the userman userid
	* @userid is userman user id
	* @widget is an array we need to replace few item based on the userid
	*/
	public function getWidgetListByModule($defaultexten, $userid,$widget) {
		// if the widget_type_id is not defaultextension and widget_type_id is not in extensions
		// then return only the defaultexten details
		$widgets = array();
		$widget_type_id = $widget['widget_type_id'];// this will be an extension number
		$enabled = $this->FreePBX->Ucp->getCombinedSettingByID($userid,'Cdr','enable');
		if (!$enabled) {
			return false;
		}
		$extensions = $this->FreePBX->Ucp->getCombinedSettingByID($userid,'Cdr','assigned');
		$extensions = is_array($extensions)?$extensions:[];
		if(in_array($widget_type_id,$extensions)){
			// nothing to do return the same widget
			return $widget;
		}else {// sent the default extension
			$data = $this->FreePBX->Core->getDevice($defaultexten);
			if(empty($data) || empty($data['description'])) {
				$data = $this->FreePBX->Core->getUser($defaultexten);
				$name = $data['name'];
			} else {
				$name = $data['description'];
			}
			$widget['widget_type_id'] = $defaultexten;
			$widget['name'] = $name;
			return $widget;
		}
	return false;
	}


	public function ucpAddGroup($id, $display, $data) {
		$this->ucpUpdateGroup($id,$display,$data);
	}

	public function ucpUpdateGroup($id,$display,$data) {
		if($display == 'userman' && isset($_POST['type']) && $_POST['type'] == 'group') {
			if(!empty($_POST['cdr_enable']) && $_POST['cdr_enable'] == "yes") {
				$this->FreePBX->Ucp->setSettingByGID($id,'Cdr','enable',true);
			} else {
				$this->FreePBX->Ucp->setSettingByGID($id,'Cdr','enable',false);
			}
			if(!empty($_POST['ucp_cdr'])) {
				$this->FreePBX->Ucp->setSettingByGID($id,'Cdr','assigned',$_POST['ucp_cdr']);
			} else {
				$this->FreePBX->Ucp->setSettingByGID($id,'Cdr','assigned',array('self'));
			}
			if(!empty($_REQUEST['cdr_download']) && $_REQUEST['cdr_download'] == 'yes') {
				$this->FreePBX->Ucp->setSettingByGID($id,'Cdr','download',true);
			} else {
				$this->FreePBX->Ucp->setSettingByGID($id,'Cdr','download',false);
			}
			if(!empty($_REQUEST['cdr_playback']) && $_REQUEST['cdr_playback'] == 'yes') {
				$this->FreePBX->Ucp->setSettingByGID($id,'Cdr','playback',true);
			} else {
				$this->FreePBX->Ucp->setSettingByGID($id,'Cdr','playback',false);
			}
		}
	}

	/**
	* Hook functionality from userman when a user is deleted
	* @param {int} $id      The userman user id
	* @param {string} $display The display page name where this was executed
	* @param {array} $data    Array of data to be able to use
	*/
	public function ucpDelUser($id, $display, $ucpStatus, $data) {

	}

	/**
	* Hook functionality from userman when a user is added
	* @param {int} $id      The userman user id
	* @param {string} $display The display page name where this was executed
	* @param {array} $data    Array of data to be able to use
	*/
	public function ucpAddUser($id, $display, $ucpStatus, $data) {
		$this->ucpUpdateUser($id, $display, $ucpStatus, $data);
	}

	/**
	* Hook functionality from userman when a user is updated
	* @param {int} $id      The userman user id
	* @param {string} $display The display page name where this was executed
	* @param {array} $data    Array of data to be able to use
	*/
	public function ucpUpdateUser($id, $display, $ucpStatus, $data) {
		if($display == 'userman' && isset($_POST['type']) && $_POST['type'] == 'user') {
			if(!empty($_POST['cdr_enable']) && $_POST['cdr_enable'] == "yes") {
				$this->FreePBX->Ucp->setSettingByID($id,'Cdr','enable',true);
			} elseif(!empty($_POST['cdr_enable']) && $_POST['cdr_enable'] == "no") {
				$this->FreePBX->Ucp->setSettingByID($id,'Cdr','enable',false);
			} elseif(!empty($_POST['cdr_enable']) && $_POST['cdr_enable'] == "inherit") {
				$this->FreePBX->Ucp->setSettingByID($id,'Cdr','enable',null);
			}
			if(!empty($_POST['ucp_cdr'])) {
				$this->FreePBX->Ucp->setSettingByID($id,'Cdr','assigned',$_POST['ucp_cdr']);
			} else {
				$this->FreePBX->Ucp->setSettingByID($id,'Cdr','assigned',null);
			}
			if(!empty($_REQUEST['cdr_download']) && $_REQUEST['cdr_download'] == 'yes') {
				$this->FreePBX->Ucp->setSettingByID($id,'Cdr','download',true);
			} elseif(!empty($_POST['cdr_download']) && $_POST['cdr_download'] == "no") {
				$this->FreePBX->Ucp->setSettingByID($id,'Cdr','download',false);
			} elseif(!empty($_POST['cdr_download']) && $_POST['cdr_download'] == "inherit") {
				$this->FreePBX->Ucp->setSettingByID($id,'Cdr','download',null);
			}
			if(!empty($_REQUEST['cdr_playback']) && $_REQUEST['cdr_playback'] == 'yes') {
				$this->FreePBX->Ucp->setSettingByID($id,'Cdr','playback',true);
			} elseif(!empty($_POST['cdr_playback']) && $_POST['cdr_playback'] == "no") {
				$this->FreePBX->Ucp->setSettingByID($id,'Cdr','playback',false);
			} elseif(!empty($_POST['cdr_playback']) && $_POST['cdr_playback'] == "inherit") {
				$this->FreePBX->Ucp->setSettingByID($id,'Cdr','playback',null);
			}
		}
	}

	public function ucpConfigPage($mode, $user, $action) {
		if(empty($user)) {
			$enable = ($mode == 'group') ? true : null;
			$download = ($mode == 'group') ? true : null;
			$playback = ($mode == 'group') ? true : null;
		} else {
			if($mode == 'group') {
				$enable = $this->FreePBX->Ucp->getSettingByGID($user['id'],'Cdr','enable');
				$download = $this->FreePBX->Ucp->getSettingByGID($user['id'],'Cdr','download');
				$playback = $this->FreePBX->Ucp->getSettingByGID($user['id'],'Cdr','playback');
				$cdrassigned = $this->FreePBX->Ucp->getSettingByGID($user['id'],'Cdr','assigned');
			} else {
				$enable = $this->FreePBX->Ucp->getSettingByID($user['id'],'Cdr','enable');
				$download = $this->FreePBX->Ucp->getSettingByID($user['id'],'Cdr','download');
				$playback = $this->FreePBX->Ucp->getSettingByID($user['id'],'Cdr','playback');
				$cdrassigned = $this->FreePBX->Ucp->getSettingByID($user['id'],'Cdr','assigned');
			}
		}

		$cdrassigned = !empty($cdrassigned) ? $cdrassigned : array();

		$ausers = array();
		if($action == "showgroup" || $action == "addgroup") {
			$ausers['self'] = _("User Primary Extension");
		}
		if($action == "addgroup") {
			$cdrassigned = array('self');
		}
		foreach(core_users_list() as $list) {
			$ausers[$list[0]] = sprintf("%s <%s>", $list[1], $list[0]);
		}
		$html[0] = array(
			"title" => _("Call History"),
			"rawname" => "cdrreports",
			"content" => load_view(dirname(__FILE__)."/views/ucp_config.php",array("mode"  => $mode, "enable" => $enable, "cdrassigned" => $cdrassigned, "ausers" => $ausers, "playback" => $playback,"download" => $download))
		);
		return $html;
	}

	public function doConfigPageInit($page) {
	}

	public function install() {

	}
	public function uninstall() {

	}
	public function backup(){

	}
	public function restore($backup){

	}
	public function genConfig() {

	}

	public function getDbTable() {
		return $this->db_table;
	}

	public function ajaxRequest($req, &$setting) {
		$setting['authenticate'] = true;
		$setting['allowremote'] = false;
		switch($req) {
			case "gethtml5":
			case "playback":
			case "download":
				return true;
			break;
		}
		return false;
	}

	public function ajaxCustomHandler() {
		switch($_REQUEST['command']) {
			case "playback":
			case "download":
				$media = $this->FreePBX->Media();
				$media->getHTML5File($_REQUEST['file']);
			break;
		}
	}

	public function ajaxHandler() {
		switch($_REQUEST['command']) {
			case "gethtml5":
				$media = $this->FreePBX->Media();
				$info = $this->getRecordByID($_POST['uid']);
				if(!empty($info['recordingfile'])) {
					$media->load($info['recordingfile']);
					$files = $media->generateHTML5();
					$final = array();
					foreach($files as $format => $name) {
						$final[$format] = "ajax.php?module=cdr&command=playback&file=".$name;
					}
					return array("status" => true, "files" => $final);
				}
				return array("status" => false);
			break;
		}
	}

	public function getRecordByID($rid) {
		$sql = "SELECT * FROM ".$this->db_table." WHERE NOT(recordingfile = '') AND uniqueid = :uid";
		$sth = $this->cdrdb->prepare($sql);
		try {
			$sth->execute(array("uid" => str_replace("_",".",$rid)));
			$recording = $sth->fetch(\PDO::FETCH_ASSOC);
		} catch(\Exception $e) {
			return array();
		}
		$recording['recordingfile'] = $this->processPath($recording['recordingfile']);
		return $recording;
	}

	/**
	 * Get CDR record by record ID and extension
	 * @param int $rid           The record ID
	 * @param string $ext           The extension
	 * @param bool $generateMedia Whether to generate HTML assets or not
	 */
	public function getRecordByIDExtension($rid,$ext) {
		$sql = "SELECT * FROM ".$this->db_table." WHERE NOT(recordingfile = '') AND uniqueid = :uid AND (src = :ext OR dst = :ext OR src = :vmext OR dst = :vmext OR cnum = :ext OR cnum = :vmext OR dstchannel LIKE :chan OR channel LIKE :chan)";
		$sth = $this->cdrdb->prepare($sql);
		try {
			$sth->execute(array("uid" => str_replace("_",".",$rid), "ext" => $ext, "vmext" => "vmu".$ext, ':chan' => '%/'.$ext.'-%'));
			$recording = $sth->fetch(\PDO::FETCH_ASSOC);
		} catch(\Exception $e) {
			return false;
		}
		$recording['recordingfile'] = $this->processPath($recording['recordingfile']);
		return $recording;
	}

	public function getAllCalls($page=1,$orderby='date',$order='desc',$search='',$limit=100) {
		$start = ($limit * ($page - 1));
		$end = $limit;
		switch($orderby) {
			case 'description':
				$orderby = 'clid';
			break;
			case 'duration':
				$orderby = 'duration';
			break;
			case 'date':
			default:
				$orderby = 'timestamp';
			break;
		}
		$order = ($order == 'desc') ? 'desc' : 'asc';
		if(!empty($search)) {
			$sql = "SELECT *, UNIX_TIMESTAMP(calldate) As timestamp FROM ".$this->db_table." WHERE (clid LIKE :search OR src LIKE :search OR dst LIKE :search) ORDER by $orderby $order LIMIT $start,$end";
			$sth = $this->cdrdb->prepare($sql);
			$sth->execute(array(':search' => '%'.$search.'%'));
		} else {
			$sql = "SELECT *, UNIX_TIMESTAMP(calldate) As timestamp FROM ".$this->db_table." ORDER by $orderby $order LIMIT $start,$end";
			$sth = $this->cdrdb->prepare($sql);
			$sth->execute();
		}
		$calls = $sth->fetchAll(\PDO::FETCH_ASSOC);
		return $calls;
	}

	/**
	 * Get all CDR call records
	 * @param int  $extension 		The extension
	 * @param integer $page      	The page number to start at
	 * @param string  $orderby   	Order the results by
	 * @param string  $order    	Order ASC or DESC
	 * @param string  $search   	The search string to use
	 * @param integer $limit    	The number of results to return
	 * @param bool    $fromAPI  	Uses the replicate_cdr table instead, which only stores last two months of CDR data.  Used when queries to regular cdr table take too long because the table has too much data.
	 * @param string  $webrtcPrefix 	
	 */
	public function getCalls($extension, $page = 1, $orderby = 'date', $order = 'desc', $search = '', $limit = 100, $fromAPI = false, $webrtcPrefix = '') {
		if($fromAPI) {
			//set the $db_table variable to 'replicate_cdr' if cdrTrigger is created
			$this->checkCdrTrigger();
		}
		$defaultExtension = $extension;
		if (!empty($webrtcPrefix)) {
			$extension = $webrtcPrefix . $extension;
		}
		$start = ($limit * ($page - 1));
		$end = $limit;
		switch($orderby) {
			case 'description':
				$orderby = 'clid';
			break;
			case 'duration':
				$orderby = 'duration';
			break;
			case 'date':
			default:
				$orderby = 'timestamp';
			break;
		}
		$order = ($order == 'desc') ? 'desc' : 'asc';
		if(!empty($search)) {
			$sql = "SELECT *, UNIX_TIMESTAMP(calldate) As timestamp FROM ".$this->db_table." WHERE (dstchannel LIKE :chan OR dstchannel LIKE :dst_channel OR channel LIKE :chan OR src = :extension OR dst = :extension OR src = :extensionv OR dst = :extensionv OR cnum = :extension OR cnum = :extensionv) AND (clid LIKE :search OR src LIKE :search OR dst LIKE :search) ORDER by $orderby $order LIMIT $start,$end";
			$sth = $this->cdrdb->prepare($sql);
			$sth->execute(array(':chan' => '%/'.$extension.'-%', ':dst_channel' => '%-'.$defaultExtension.'@%', ':extension' => $extension, ':search' => '%'.$search.'%', ':extensionv' => 'vmu'.$extension));
		} else {
			$sql = "SELECT *, UNIX_TIMESTAMP(calldate) As timestamp FROM ".$this->db_table." WHERE (dstchannel LIKE :chan OR dstchannel LIKE :dst_channel OR channel LIKE :chan OR src = :extension OR dst = :extension OR src = :extensionv OR dst = :extensionv OR cnum = :extension OR cnum = :extensionv) ORDER by $orderby $order LIMIT $start,$end";
			$sth = $this->cdrdb->prepare($sql);
			$sth->execute(array(':chan' => '%/'.$extension.'-%', ':dst_channel' => '%-'.$defaultExtension.'@%', ':extension' => $extension, ':extensionv' => 'vmu'.$extension));
		}
		$calls = $sth->fetchAll(\PDO::FETCH_ASSOC);
		foreach($calls as &$call) {
			if(empty($call['dst']) && preg_match('/\/(.*)\-/',$call['dstchannel'],$matches)) {
				$call['dst'] = $matches[1];
			}
			if(empty($call['src']) && preg_match('/\/(.*)\-/',$call['channel'],$matches)) {
				$call['src'] = $matches[1];
			}
			//This Check $fromAPI to avoid to send the unwanted data to DPMA Call Log API only.
			if(!$fromAPI) {
			if($call['duration'] > 59) {
				$min = floor($call['duration'] / 60);
				if($min > 59) {
					$call['niceDuration'] = sprintf(_('%s hour, %s min, %s sec'),gmdate("H", $call['duration']), gmdate("i", $call['duration']), gmdate("s", $call['duration']));
				} else {
					$call['niceDuration'] = sprintf(_('%s min, %s sec'),gmdate("i", $call['duration']), gmdate("s", $call['duration']));
				}
			} else {
				$call['niceDuration'] = sprintf(_('%s sec'),$call['duration']);
			}
			$call['niceUniqueid'] = str_replace(".","_",$call['uniqueid']);
			$call['recordingformat'] = !empty($call['recordingfile']) ? strtolower(pathinfo($call['recordingfile'],PATHINFO_EXTENSION)) : '';
			$call['recordingfile'] = $this->processPath($call['recordingfile']);
			$call['requestingExtension'] = $extension;
			}
		}
		return $calls;
	}

	/**
	* Get the Number of Pages by limit for extension
	* @param {int} $extension The Extension to lookup
	* @param {int} $limit=100 The limit of results per page
	*/
	public function getPages($extension,$search='',$limit=100) {
		if(!empty($search)) {
			$sql = "SELECT count(*) as count FROM ".$this->db_table." WHERE (dstchannel LIKE :chan OR channel LIKE :chan OR src = :extension OR dst = :extension OR src = :extensionv OR dst = :extensionv OR cnum = :extension) AND (clid LIKE :search OR src LIKE :search OR dst LIKE :search)";
			$sth = $this->cdrdb->prepare($sql);
			$sth->execute(array(':chan' => '%/'.$extension.'-%', ':extension' => $extension, ':search' => '%'.$search.'%',':extensionv' => 'vmu'.$extension));
		} else {
			$sql = "SELECT count(*) as count FROM ".$this->db_table." WHERE (dstchannel LIKE :chan OR channel LIKE :chan OR src = :extension OR dst = :extension OR src = :extensionv OR dst = :extensionv OR cnum = :extension)";
			$sth = $this->cdrdb->prepare($sql);
			$sth->execute(array(':chan' => '%/'.$extension.'-%', ':extension' => $extension, ':extensionv' => 'vmu'.$extension));
		}
		$res = $sth->fetch(\PDO::FETCH_ASSOC);
		$total = $res['count'];
		if(!empty($total)) {
			return ceil($total/$limit);
		} else {
			return false;
		}
	}

	public function getTotalCalls($extension,$search='') {
		if(!empty($search)) {
			$sql = "SELECT count(*) as count FROM ".$this->db_table." WHERE (dstchannel LIKE :chan OR channel LIKE :chan OR src = :extension OR dst = :extension OR src = :extensionv OR dst = :extensionv OR cnum = :extension) AND (clid LIKE :search OR src LIKE :search OR dst LIKE :search)";
			$sth = $this->cdrdb->prepare($sql);
			$sth->execute(array(':chan' => '%/'.$extension.'-%', ':extension' => $extension, ':search' => '%'.$search.'%',':extensionv' => 'vmu'.$extension));
		} else {
			$sql = "SELECT count(*) as count FROM ".$this->db_table." WHERE (dstchannel LIKE :chan OR channel LIKE :chan OR src = :extension OR dst = :extension OR src = :extensionv OR dst = :extensionv OR cnum = :extension)";
			$sth = $this->cdrdb->prepare($sql);
			$sth->execute(array(':chan' => '%/'.$extension.'-%', ':extension' => $extension, ':extensionv' => 'vmu'.$extension));
		}
		$res = $sth->fetch(\PDO::FETCH_ASSOC);
		$total = $res['count'];
		if(!empty($total)) {
			return $total;
		} else {
			return 0;
		}
	}

	/**
	 * Tear apart the file name to get our correct path
	 * @param  string $recordingFile The recording file
	 * @return string                The full path
	 */
	public function processPath($recordingFile) {
		if(empty($recordingFile)) {
			return '';
		}
		$spool = $this->FreePBX->Config->get('ASTSPOOLDIR');
		$mixmondir = $this->FreePBX->Config->get('MIXMON_DIR');
		$rec_parts = explode('-',$recordingFile);
		$fyear = substr($rec_parts[3],0,4);
		$fmonth = substr($rec_parts[3],4,2);
		$fday = substr($rec_parts[3],6,2);
		$monitor_base = $mixmondir ? $mixmondir : $spool . '/monitor';
		$recordingFile = "$monitor_base/$fyear/$fmonth/$fday/" . $recordingFile;
		//check to make sure the file size is bigger than 44 bytes (header size)
		if(file_exists($recordingFile) && is_readable($recordingFile) && filesize($recordingFile) > 44) {
			return $recordingFile;
		}
		return '';
	}

	public function getTotal() {
		$sql = "SELECT count(*) as count FROM ".$this->getDbTable();
		$sth = $this->cdrdb->prepare($sql);
		$sth->execute();
		return $sth->fetchColumn();
	}

	public function getGraphQLCalls($after, $first, $before, $last, $orderby, $startDate, $endDate) {
		switch($orderby) {
				case 'duration':
						$orderby = 'duration';
				break;
				case 'date':
				default:
						$orderby = 'timestamp';
				break;
		}
		$first = !empty($first) ? (int) $first : 5;
		$after = !empty($after) ? (int) $after : 0;
		$whereClause = " ";
		if((isset($startDate) && !empty($startDate)) && (isset($endDate) && !empty($endDate))){
			$whereClause = " where DATE(calldate) BETWEEN '".$startDate."' AND '".$endDate."'";
		}
		$sql = "SELECT *, UNIX_TIMESTAMP(calldate) As timestamp FROM ".$this->getDbTable()." ".$whereClause." Order By :orderBy DESC LIMIT :limitValue OFFSET :afterValue";
		$sth = $this->cdrdb->prepare($sql);
		$sth->bindValue(':orderBy', $orderby, \PDO::PARAM_STR);
		$sth->bindValue(':limitValue', (int) trim($first), \PDO::PARAM_INT);
		$sth->bindValue(':afterValue', (int) trim($after), \PDO::PARAM_INT);
		$sth->execute();
		$calls = $sth->fetchAll(\PDO::FETCH_ASSOC);
		return $calls;
	}

	public function getGraphQLRecordByID($rid) {
		$sql = "SELECT *, UNIX_TIMESTAMP(calldate) As timestamp FROM ".$this->getDbTable()." WHERE uniqueid = :uid";
		$sth = $this->cdrdb->prepare($sql);
		try {
				$sth->execute(array("uid" => str_replace("_",".",$rid)));
				$recording = $sth->fetch(\PDO::FETCH_ASSOC);
		} catch(\Exception $e) {
				return array();
		}
		return $recording;
	}
	
	/**
	 * This function will check whether the cdrTrigger is created or not.
	 * If the trigger exists, it will set the $db_table variable to 'replicate_cdr'.
	 * So when 'pbx.users.callLogs.getList' method is called, it will fetch the call logs from 'replicate_cdr' instead of 'cdr' table
	 */
	private function checkCdrTrigger() {
		$query = "SHOW TRIGGERS WHERE `Trigger` = 'cdrTrigger'";
		$res = $this->cdrdb->prepare($query);
		$res->execute();
		$result = $res->fetch(\PDO::FETCH_ASSOC);
		if (!empty($result)) {
			$this->db_table = 'replicate_cdr';
		}
	}

}
