<?php
namespace FreePBX\modules;
use BMO;
use PDO;
// vim: set ai ts=4 sw=4 ft=php:
if(!function_exists('music_list')) {
	include(__DIR__.'/functions.inc.php');
}

class Music implements \BMO {
	/** Extensions to show in the convert to section
	 * Limited on purpose because there are far too many,
	 * Most of which are not supported by asterisk
	 */
	public $convert = array(
		"wav",
		"sln",
		"sln16",
		"sln48",
		"g722",
		"ulaw",
		"alaw",
		"g729",
		"gsm"
	);

	private $tmp = "/tmp";

	private $message = array();

	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new Exception("Not given a FreePBX Object");
		}
		$this->FreePBX = $freepbx;
		$this->db = $freepbx->Database;
		$this->mohdir = $freepbx->Config->get('MOHDIR');
		$this->varlibdir = $freepbx->Config->get('ASTVARLIBDIR');
		$this->mohpath = $this->varlibdir.'/'.$this->mohdir;
		$this->config = $this->loadMoHConfig();
		$this->tmp = $this->FreePBX->Config->get("ASTSPOOLDIR") . "/tmp";
		if(!file_exists($this->tmp)) {
			mkdir($this->tmp,0777,true);
		}
	}

	public function setDatabase($pdo){
		$this->db = $pdo;
		return $this;
	}
	public function resetDatabase(){
		$this->db = $this->FreePBX->Database;
		return $this;
	}

	public function showPage() {
		$mh = $this;
		$heading = _("On Hold Music");
		$request = $_REQUEST;
		$request['view'] = isset($request['view'])?$request['view']:'';
		$request['action'] = isset($request['action'])?$request['action']:'';
		$request['category'] = isset($request['category'])?$this->stripCategory($request['category']):"";
		$request['id'] = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';

		$display_mode = "advanced";
		$mode = $this->FreePBX->Config->get("FPBXOPMODE");
		if(!empty($mode)) {
			$display_mode = $mode;
		}
		if($display_mode == "basic") {
			$cat = $this->getCategoryByName("default");
			if(empty($cat)) {
				throw new \Exception("Error in Music on Hold. Cant find default class");
			}
			$request["action"] = 'edit';
			$request['id'] = $cat['id'];
		}
		switch($request["action"]){
			case "edit":
				$media = $this->FreePBX->Media;
				$supported = $media->getSupportedFormats();
				ksort($supported['in']);
				ksort($supported['out']);
				$supportedHTML5 = $media->getSupportedHTML5Formats();
				$convertto = array_intersect($supported['out'], $mh->convert);
				$data = $this->getCategoryByID($request['id']);
				if($display_mode == "basic") {
					//only files allowed in this mode
					$data['type'] = 'files';
				}
				$heading .= ' - '.$data['category'];
				$path = $this->getCategoryPath($data['category']);
				$files = array();
				foreach($mh->fileList($path) as $f) {
					$i = pathinfo($f);
					$fn = $i['filename'];
					$files[$fn] = strtolower($i['filename']);
				}
				$files = array_values($files);
				$view = ($display_mode == "basic") ? __DIR__.'/views/basic_form.php' : __DIR__.'/views/advanced_form.php';
				$content = load_view($view, array("display_mode" => $display_mode, "files" => $files, "convertto" => $convertto, "supportedHTML5" => implode(",",$supportedHTML5), "supported" => $supported, 'data' => $data));
			break;
			case "add":
				$content = load_view(__DIR__.'/views/addcatform.php');
			break;
			default:
				$content = load_view(__DIR__.'/views/grid.php', array('message' => $this->message, 'request' => $request));
			break;
		}
		$request["action"] = ($request["action"] == "delete") ? "" : $request["action"];
		return load_view(__DIR__.'/views/main.php', array('request' => $request, 'heading' => $heading, 'content' => $content));
	}

	public function doConfigPageInit($page) {
		if(!empty($_REQUEST['action'])) {
			switch($_REQUEST['action']) {
				case "addnew":
					$this->addCategory($_POST['category'],$_POST['type']);
				break;
				case "delete":
					$this->deleteCategory($_REQUEST['id']);
				break;
			}
		}
	}

	public function updateCategoryByID($id, $type, $random = false, $application = '', $format = '') {
		$sql = "UPDATE music SET type = :type, random = :random, application = :application, format = :format WHERE id = :id";
		$sth = $this->db->prepare($sql);
		$sth->execute(array(
			"type" => $type,
			"random" => $random,
			"application" => $application,
			"format" => $format,
			"id" => $id
		));
		needreload();
	}

	/**
	 * Add Category by generated id
	 *
	 * @param string $name The Category name
	 * @param string $type The Category type
	 * @return void
	 */
	public function addCategory($name,$type) {
		$this->addCategoryById(null, $name,$type);
	}

	/**
	 * Add Category by ID
	 *
	 * @param integer $id The Category ID
	 * @param string $name The Category name
	 * @param string $type The Category type
	 * @return void
	 */
	public function addCategoryById($id, $name,$type) {
		$name = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $name);
		$name = preg_replace("/\s+|'+|`+|\\+|\/+|\"+|<+|>+|\?+|\*|\.+|&+|\|/","",$name);
		$name = basename($name);
		$cat = $this->getCategoryByName($name);
		if(!empty($cat)) {
			$this->message = array(
				"type" => "danger",
				"message" => _("Category Already Exists")
			);
			return;
		}
		$sql = "INSERT INTO music (`id`, `category`, `type`) VALUES (?, ?,?)";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($id,$name,$type));

		if(!file_exists($this->mohpath . "/" . $name)) {
			mkdir($this->mohpath . "/" . $name);
		}
		needreload();
	}

	public function genConfig() {
		$ccc = $this->FreePBX->Config->get("CACHERTCLASSES") ? "yes" : "no";
		$conf["musiconhold_additional.conf"]['general']['cachertclasses'] = $ccc;

		$conf["musiconhold_additional.conf"]['none'] = array(
			"mode" => "files",
			"sort" => "alpha",
			"directory" => $this->mohpath."/.nomusic_reserved"
		);
		if(!file_exists($this->mohpath."/.nomusic_reserved")) {
			mkdir($this->mohpath."/.nomusic_reserved",0777,true);
			copy(__DIR__."/silence.wav",$this->mohpath."/.nomusic_reserved/silence.wav");
			$AMPASTERISKWEBUSER = $this->FreePBX->Config->get("AMPASTERISKWEBUSER");
			$AMPASTERISKUSER = $this->FreePBX->Config->get("AMPASTERISKUSER");
			$AMPASTERISKGROUP = $this->FreePBX->Config->get("AMPASTERISKGROUP");
			$AMPASTERISKWEBGROUP = $this->FreePBX->Config->get("AMPASTERISKWEBGROUP");

			$ampowner = $AMPASTERISKWEBUSER;
			/* Address concerns carried over from amportal in FREEPBX-8268. If the apache user is different
			 * than the Asterisk user we provide permissions that allow both.
			 */
			$ampgroup =  $AMPASTERISKWEBUSER != $AMPASTERISKUSER ? $AMPASTERISKGROUP : $AMPASTERISKWEBGROUP;
			chown($this->mohpath."/.nomusic_reserved",$ampowner);
			chown($this->mohpath."/.nomusic_reserved/silence.wav",$ampowner);
		}

		$categories = $this->getCategories();
		foreach($categories as $cat) {
			$name = $cat['category'];
			switch($cat['type']) {
				case "files":
					$conf["musiconhold_additional.conf"][$name] = array(
						"mode" => $cat['type'],
						"sort" => ($cat['random']) ? "random" : "alpha",
						"directory" => $this->getCategoryPath($cat['category'])
					);
				break;
				case "custom":
					$conf["musiconhold_additional.conf"][$name] = array(
						"mode" => $cat['type'],
						"application" => $cat['application'],
						"format" => $cat['format']
					);
				break;
			}
		}
		return $conf;
	}

	public function writeConfig($conf){
		$this->FreePBX->WriteConfig($conf);
	}

	/**
	 * Delete Category
	 * @param  integer $id ID of category
	 */
	public function deleteCategory($id) {
		$info = $this->getCategoryByID($id);
		$sql = "DELETE FROM music WHERE id = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($id));
		if(!empty($info['category'])) {
			$path = $this->getCategoryPath(basename($info['category']));
			$this->rmdirr($path);
		}
		needreload();
	}

	/**
	 * Recursively Remove a Directory
	 * @param  string $path The path to remove
	 * @return boolean       True if removed, false if it did not
	 */
	public function rmdirr($path) {
		if($path == $this->mohpath) {
			return false;
		}
		// Sanity check
		if (!file_exists($path)) {
			return false;
		}

		// Simple delete for a file
		if (is_file($path)) {
			return unlink($path);
		}

		// Loop through the folder
		$dir = dir($path);
		while (false !== $entry = $dir->read()) {
			// Skip pointers
			if ($entry == '.' || $entry == '..') {
				continue;
			}

			// Recurse
			$this->rmdirr($path."/".$entry);
		}

		// Clean up
		$dir->close();
		return rmdir($path);
	}

	public function install() {
		$freepbx_conf = $this->FreePBX->Config;
		if ($freepbx_conf->conf_setting_exists('AMPMPG123')) {
			$freepbx_conf->remove_conf_setting('AMPMPG123');
		}

		// CACHERTCLASSES
		//
		$set['value'] = true;
		$set['defaultval'] =& $set['value'];
		$set['readonly'] = 0;
		$set['hidden'] = 0;
		$set['level'] = 3;
		$set['module'] = 'music';
		$set['category'] = 'System Setup';
		$set['emptyok'] = 0;
		$set['name'] = 'Cache MoH Classes';
		$set['description'] = 'When enabled Asterisk will use 1 instance of moh class for all channels who are using it, decreasing consumable cpu cycles and memory in the process';
		$set['type'] = CONF_TYPE_BOOL;
		$freepbx_conf->define_conf_setting('CACHERTCLASSES',$set,true);

		$sql = "SELECT * FROM music WHERE category = 'default'";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$default = $sth->fetch(PDO::FETCH_ASSOC);
		if(empty($default)) {
			$random = file_exists($this->mohpath."/.random") ? 1 : 0;
			$sql = "INSERT INTO music (`category`, `type`, `random`) VALUES (?,?,?)";
			$sth = $this->db->prepare($sql);
			$sth->execute(array("default","files",$random));
		}

		foreach(glob($this->mohpath."/*",GLOB_ONLYDIR) as $cat) {
			$category = basename($cat);
			$sql = "SELECT * FROM music WHERE category = ?";
			$sth = $this->db->prepare($sql);
			$sth->execute(array($category));
			$c = $sth->fetch(PDO::FETCH_ASSOC);
			if(!empty($c)) {
				continue;
			}
			$random = file_exists($cat."/.random") ? 1 : 0;
			$application = "";
			$format = "";
			if(file_exists($cat."/.custom")) {
				$type = "custom";
				$application = file_get_contents($cat."/.custom");
				$application = explode("\n",$application);
				if (isset($application[1])) {
					$format = explode('=',$application[1],2);
					$format = $format[1];
				} else {
					$format = "";
				}
				$application = $application[0];
			} else {
				$type = "files";
			}

			$sql = "INSERT INTO music (`category`, `type`, `random`, `application`, `format`) VALUES (?,?,?,?,?)";
			$sth = $this->db->prepare($sql);
			$sth->execute(array($category,$type,$random,$application,$format));
		}
	}
	public function uninstall() {

	}


	/**
	 * Get all categories
	 * @return [type] [description]
	 */
	public function getCategories(){
		$sql = "SELECT * FROM music";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$cats = $sth->fetchAll(PDO::FETCH_ASSOC);
		return !empty($cats) ? $cats : array();
	}

	public function ajaxRequest($req, &$setting) {
		switch($req) {
			case "gethtml5":
			case "playback":
			case "download":
			case "getJSON":
			case "upload":
			case "deleteCategory":
			case "deletemusic":
			case "save":
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
			case "deletemusic":
				$category = $this->getCategoryByID($_POST['categoryid']);
				if(empty($category)) {
					return array("status" => false, "message" => _("Invalid category"));
				}
				$path = $this->getCategoryPath($category['category']);
				$name = basename($_POST['name']);
				foreach(glob($path."/".$name."*") as $file) {
					if(!unlink($file)) {
						return array("status" => false, "message" => sprintf(_("Unable to delete %s"),$file));
						break;
					}
				}
				return array("status" => true);
			break;
			case "save":
				if($_POST['type'] == "files") {
					//do conversions here whooooo
					$media = $this->FreePBX->Media();
					$category = $this->getCategoryByID($_POST['id']);
					$path = $this->getCategoryPath($category['category']);
					$files = $this->fileList($path);
					foreach($files as $file) {
						$name = pathinfo($file,PATHINFO_FILENAME);
						$media->load($path."/".$file);
						foreach($_POST['codecs'] as $codec) {
							$fpath = $path . "/" .$name.".".$codec;
							if(!file_exists($fpath)) {
								$media->convert($fpath);
							}
						}
					}
				}
				$this->updateCategoryByID($_POST['id'],$_POST['type'], ($_POST['erand'] == "yes"), $_POST['application'], $_POST['format']);
				return array("status" => true);
			break;
			case "deleteCategory":
				if(empty($_POST['name']) || empty($_POST['categoryid'])) {
					return array("status" => false);
				}
				$category = $this->getCategoryByID($_REQUEST['categoryid']);
				$path = $this->getCategoryPath($category['category']);
				$name = basename($_POST['name']);
				foreach(glob($path."/".$name."*") as $file) {
					unlink($file);
				}
				return array("status" => true);
			break;
			case "upload":
				// XXX If the posted file was too large,
				// we will get here, but $_FILES is empty!
				// Specifically, if the file that was posted is
				// larger than 'post_max_size' in php.ini.
				// So, php will throw an error, as index
				// $_FILES["files"] does not exist, because
				// $_FILES is empty.
				if (!isset($_FILES)) {
					return array("status" => false,
						"message" => _("File upload failed"));
				}
				foreach ($_FILES["files"]["error"] as $key => $error) {
					switch($error) {
						case UPLOAD_ERR_OK:
							$extension = pathinfo($_FILES["files"]["name"][$key], PATHINFO_EXTENSION);
							$extension = strtolower($extension);
							$supported = $this->FreePBX->Media->getSupportedFormats();
							$category = $this->getCategoryByID($_POST['id']);
							$path = $this->getCategoryPath($category['category']);
							$media = $this->FreePBX->Media();
							if(in_array($extension,$supported['in'])) {
								$tmp_name = $_FILES["files"]["tmp_name"][$key];
								$dname = \Media\Media::cleanFileName($_FILES["files"]["name"][$key]);
								$dname = basename($dname);
								$dname = pathinfo($dname,PATHINFO_FILENAME);
								$name = $dname . '.' . $extension;
								move_uploaded_file($tmp_name, $this->tmp."/".$name);
								$media->load($this->tmp."/".$name);
								foreach($_POST['codec'] as $c) {
									$media->convert($path."/".$dname.".".$c);
								}
								unlink($this->tmp."/".$name);
								return array("status" => true, "name" => $dname, "filename" => $name, "formats" => $_POST['codec'], "category" => $category['category'], "categoryid" => $_POST['id']);
							} else {
								return array("status" => false, "message" => _("Unsupported file format"));
								break;
							}
						break;
						case UPLOAD_ERR_INI_SIZE:
							return array("status" => false, "message" => _("The uploaded file exceeds the upload_max_filesize directive in php.ini"));
						break;
						case UPLOAD_ERR_FORM_SIZE:
							return array("status" => false, "message" => _("The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form"));
						break;
						case UPLOAD_ERR_PARTIAL:
							return array("status" => false, "message" => _("The uploaded file was only partially uploaded"));
						break;
						case UPLOAD_ERR_NO_FILE:
							return array("status" => false, "message" => _("No file was uploaded"));
						break;
						case UPLOAD_ERR_NO_TMP_DIR:
							return array("status" => false, "message" => _("Missing a temporary folder"));
						break;
						case UPLOAD_ERR_CANT_WRITE:
							return array("status" => false, "message" => _("Failed to write file to disk"));
						break;
						case UPLOAD_ERR_EXTENSION:
							return array("status" => false, "message" => _("A PHP extension stopped the file upload"));
						break;
					}
				}
				return array("status" => false, "message" => _("Can Not Find Uploaded Files"));
			break;
			case "gethtml5":
				$media = $this->FreePBX->Media();
				$category = $this->getCategoryByID($_REQUEST['categoryid']);
				$path = $this->getCategoryPath($category['category']);
				$file = $path . "/" . basename($_REQUEST['file']);
				if (file_exists($file))	{
					$media->load($file);
					$files = $media->generateHTML5();
					$final = array();
					foreach($files as $format => $name) {
						$final[$format] = "ajax.php?module=music&command=playback&file=".$name;
					}
					return array("status" => true, "files" => $final);
				} else {
					return array("status" => false, "message" => _("File does not exist"));
				}
			break;
			case "getJSON":
				switch ($_REQUEST['jdata']) {
					case 'categories':
						return $this->getCategories();
					break;
					case 'musiclist':
						$category = $this->getCategoryByID($_REQUEST['id']);
						$path = $this->getCategoryPath($category['category']);
						$files = array();
						$count = 0;
						$list = $this->fileList($path);
						asort($list);
						foreach ($list as $value){
							/*
								Parse the file if there's a name + extension (name.ext)
							*/
							list($fn,$fe) = explode(".",$value);
							if(!empty($fn) && !empty($fe)){
								$fp = pathinfo($value);
								if(!isset($files[$fp['filename']])){
									$files[$fp['filename']] = array(
										'categoryid' => $category['id'],
										'category' => $category['category'],
										'id' => $count,
										'filename' => $value,
										'name' => $fp['filename'],
										'formats' => array(
											$fp['extension']
										)
									);
									$count++;
								} else {
									$files[$fp['filename']]['formats'][] = $fp['extension'];
								}
							}
						}

						return array_values($files);
					break;
					default:
						print json_encode(_("Invalid Request"));
					break;
				}
			break;
		}
	}

	/**
	 * Get all Music Files for a Category
	 * @param  string $path The full path
	 * @return array       array of files
	 */
	public function getAllMusic($path=null) {
		if ($path === null) {
			global $amp_conf;
			// to get through possible upgrade gltiches, check if set
			if (!isset($amp_conf['MOHDIR'])) {
				$amp_conf['MOHDIR'] = '/mohmp3';
			}
			$path = $amp_conf['ASTVARLIBDIR'].'/'.$amp_conf['MOHDIR'];
		}
		$i = 1;
		$arraycount = 0;
		$filearray = Array("default");

		if (is_dir($path)){
			if ($handle = opendir($path)){
				while (false !== ($file = readdir($handle))){
					if ( ($file != ".") && ($file != "..") && ($file != "CVS") && ($file != ".svn") && ($file != ".git") && ($file != ".nomusic_reserved" ) )
					{
						if (is_dir("$path/$file"))
							$filearray[($i++)] = "$file";
					}
				}
			closedir($handle);
			}
		}
		if (isset($filearray)) {
			sort($filearray);
			// add a none categoy for no music
			if (!in_array("none",$filearray)) {
				$filearray[($i++)] = "none";
			}
			return ($filearray);
		} else {
			return null;
		}
	}

	/**
	 * Get a list of supported files from the provided directory.
	 * @param  string $path path to directory, webroot user must have read permissions
	 * @return array       	list of files or null
	 */
	public function fileList($path){
		$pattern = '';
		$handle = opendir($path) ;
		$supported = $this->FreePBX->Media->getSupportedFormats("AsteriskShell");
		$extensions = $supported['out'];
		//generate the pattern to look for.
		$pattern = '/(\.'.implode('|\.',$extensions).')$/i';
		//store file names that match pattern in an array
		$i = 0;
		while (($file = readdir($handle))!==false) {
			if ($file != "." && $file != "..") {
				if(preg_match($pattern,$file)) {
					$file_array[$i] = $file; //pattern is matched store it in file_array.
					$i++;
				}
			}
		}
		closedir($handle);
		return (isset($file_array)) ? $file_array : array();  //return the size of the array
	}

	public function getActionBar($request) {
		$display_mode = "advanced";
		$mode = $this->FreePBX->Config->get("FPBXOPMODE");
		if(!empty($mode)) {
			$display_mode = $mode;
		}
		if($display_mode == "basic") {
			$request['action'] = 'edit';
		}
		$buttons = array();
		switch($request['display']) {
			case 'music':
				$buttons = array(
					'delete' => array(
						'name' => 'delete',
						'id' => 'delete',
						'value' => _('Delete')
					),
					'reset' => array(
						'name' => 'reset',
						'id' => 'reset',
						'value' => _('Reset')
					),
					'submit' => array(
						'name' => 'submit',
						'id' => 'submit',
						'value' => _('Submit')
					)
				);
				if ($display_mode != "basic" && (empty($request['id'])||($request['id'] == '1'))) {
					unset($buttons['delete']);
				}
				$request['action'] = isset($request['action'])?$request['action']:'';
				switch ($request['action']) {
					case 'add':
					case 'edit':
					case 'updatecategory':
					case 'addstream':
						/*if we match the above case(s) nothing to do*/
					break;
					default:
						/*If we don't match we return an empty array a.k.a no buttons*/
						$buttons = array();
					break;
				}
			break;
		}
		return $buttons;
	}

	/**
	 * Get Category by Name
	 * @param  string $name The category name
	 * @return array       Array of category data
	 */
	public function getCategoryByName($name) {
		$sql = "SELECT * FROM music WHERE category = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($name));
		return $sth->fetch(PDO::FETCH_ASSOC);
	}

	/**
	 * Get Category by ID
	 * @param  int $name The ID
	 * @return array       Array of category data
	 */
	public function getCategoryByID($id) {
		$sql = "SELECT * FROM music WHERE id = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($id));
		return $sth->fetch(PDO::FETCH_ASSOC);
	}

	/**
	 * Strip category of invalid characters
	 * @param  string $category [description]
	 * @return [type]           [description]
	 */
	private function stripCategory($category) {
		return basename(htmlspecialchars(strtr($category," ./\"\'\`", "------")));
	}

	private function getCategoryPath($category=null) {
		$path = $this->mohpath;
		if(!empty($category) && $category != 'default'){
			$path .= '/'.basename($category);
		}
		return $path;
	}

	private function loadMoHConfig() {
		$path = $this->FreePBX->Config->get('ASTETCDIR');
		if(file_Exists($path."/musiconhold_additional.conf")) {
			$lc = $this->FreePBX->LoadConfig("musiconhold_additional.conf");
			return $lc->ProcessedConfig;
		} else {
			return array();
		}
	}

	public function getRightNav($request) {
		if(isset($request['action']) && ($request['action'] == 'edit' || $request['action'] == 'add' || $request['action'] == 'updatecategory' || $request['action'] == 'addstream')){
			return load_view(__DIR__."/views/bootnav.php",array('request' => $request));
		}
	}
}
