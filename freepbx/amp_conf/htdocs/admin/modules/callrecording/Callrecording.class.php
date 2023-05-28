<?php
namespace FreePBX\modules;
use FreePBX_Helpers;
use BMO;
use PDO;

class Callrecording extends FreePBX_Helpers implements BMO {
	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new Exception("Not given a FreePBX Object");
		}
		$this->FreePBX = $freepbx;
		$this->db = $freepbx->Database;
	}
    public function install() {}
    public function uninstall() {}

	public static function myConfigPageInits() {
		 return array("routing");
	}
    public function doConfigPageInit($page) {
		$request = $_REQUEST;
		if($page == "callrecording"){
			$type = $this->getReq('type','setup');
			$action = $this->getReq('action','');
			if($this->getReq('delete', false)){
				$action = 'delete';
			}

			$callrecording_id = $this->getReq('callrecording_id',false);
			$description = $this->getReq('description','');
			$callrecording_mode = $this->getReq('callrecording_mode','');
			$dest = $this->getReq('dest','');

			if (isset($request['goto0']) && $request['goto0']) {
				$dest = $request[ $request['goto0'].'0' ];
			}

			if('add' == $action){
				$this->add($description, $callrecording_mode, $dest);
			}
			if('edit' == $action){
				$this->edit($callrecording_id, $description, $callrecording_mode, $dest);
			}
			if('delete' == $action){
				$this->delete($callrecording_id);
			}
			if(!empty($action)){
				needreload();
			}
		}
		if($page == "routing"){
			$viewing_itemid = isset($request['id'])?$request['id']:'';
			$action = (isset($request['action']))?$request['action']:null;
			$route_id = $viewing_itemid;
			if (isset($request['Submit']) ) {
				$action = (isset($action))?$action:'editroute';
			}
			if ($action){
				callrecording_adjustroute($route_id,$action,$request['callrecording']);
			}
		}
	}

	public function getRecording($id){
		$sql = "SELECT callrecording_id, description, callrecording_mode, dest FROM callrecording WHERE callrecording_id = :callrecording_id";
		$stmt = $this->db->prepare($sql);
		$stmt->execute(['callrecording_id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
	}

	public function listAll(){
		$sql = "SELECT callrecording_id, description, callrecording_mode, dest FROM callrecording ORDER BY description ";
		return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
	}

	public function add($description, $callrecording_mode, $dest){
		$sql = "SELECT * FROM callrecording WHERE description = :description";
		$stm = $this->db->prepare($sql);
		$stm->execute(array(":description" => $description));
		$ret = $stm->fetch(\PDO::FETCH_ASSOC);
		if(empty($ret["description"])){
			$sql = "INSERT INTO callrecording (description, callrecording_mode, dest) VALUES (:description, :mode, :dest)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute([
				':description' => $description,
				':mode' => $callrecording_mode,
				':dest' => $dest,
			]);
			return $this->db->lastInsertId();
		}
		return;
	}

	public function upsert($id,$description, $callrecording_mode, $dest){
		$sql = "INSERT INTO callrecording (callrecording_id, description, callrecording_mode, dest) VALUES (:id, :description, :mode, :dest)";
		$sql .= " ON DUPLICATE KEY UPDATE description = VALUES(description), callrecording_mode= VALUES(callrecording_mode), dest= VALUES(dest)";
		$stmt = $this->db->prepare($sql);
		return $stmt->execute([
			':id' => $id,
			':description' => $description,
			':mode' => $callrecording_mode,
			':dest' => $dest,
		]);
	}
	public function edit($callrecording_id, $description, $callrecording_mode, $dest){
		$sql = "UPDATE callrecording SET description = :description, callrecording_mode = :mode, dest = :dest WHERE callrecording_id = :id";
		$stmt = $this->db->prepare($sql);
		return $stmt->execute([
			':description' => $description,
			':mode' => $callrecording_mode,
			':dest' => $dest,
			':id' => $callrecording_id,
		]);
	}
	public function delete($id){
		$sql = "DELETE FROM callrecording WHERE callrecording_id = :callrecording_id";
		$stmt = $this->db->prepare($sql);
		return $stmt->execute(['callrecording_id' => $id]);
	}

	public function dumpExtensions(){
		$sql = "SELECT * FROM callrecording_module";
		return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
	}

	public function insertExtensionData($extension, $cidnum, $callrecording, $display){
		$sql = "INSERT INTO callrecording_module (extension, cidnum, callrecording, display) VALUES (:extension, :cidnum, :callrecording, :display)";
		$stmt = $this->db->prepare($sql);
		return $stmt->execute([
			':extension' => $extension,
			':cidnum' => $cidnum,
			':callrecording' => $callrecording,
			':display' => $display,
		]);
	}

	public function getActionBar($request) {
		$buttons = array();

		switch($request['display']) {
			case 'callrecording':
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
				if (empty($request['extdisplay'])) {
					unset($buttons['delete']);
				}
				if($request['view'] != 'form'){
					unset($buttons);
				}
			break;
		}
		return $buttons;
	}
	public function getRightNav($request){
		if($request['view']=='form'){
    	return load_view(__DIR__."/views/bootnav.php",array('request' => $request));
		}
	}

	public function listRules(){
		$sql = "SELECT callrecording_id, description, callrecording_mode, dest FROM callrecording ORDER BY description ";
		$stmt = $this->db->prepare($sql);
		$stmt->execute();
		$results = $stmt->fetchall(\PDO::FETCH_ASSOC);
		return $results;
	}
	public function getallRules($id=""){
		$sql = "SELECT callrecording_id,description FROM callrecording ORDER BY description ";
		$stmt = $this->db->prepare($sql);
		$stmt->execute();
		$results = $stmt->fetchall(\PDO::FETCH_ASSOC);
		$res = array();
		if(is_array($results)) {
			foreach($results as $r) {
				if($r['callrecording_id'] != $id) {
					$res[] = $r['description'];
				}
			}
		}
		return $res;
	}

	public function ajaxRequest($req, &$setting) {
		return ('getJSON' == $req);
	}

	public function ajaxHandler(){
		if($_REQUEST['command'] == 'getJSON' && $_REQUEST['jdata'] == 'grid'){
			return array_values($this->listAll());
		}
		return [];
	}
	public function search($query, &$results) {
		$rules = $this->listAll();
		foreach ($rules as $rule) {
			$results[] = array("text" => sprintf(_("Call Recording: %s"),$rule['description']), "type" => "get", "dest" => "?display=callrecording&view=form&extdisplay=".$rule['callrecording_id']);
		}
	}

	public function bulkhandlerExport($type) {
	    $data = NULL;
	    switch ($type) {
	        case "dids":
	            $dids = $this->FreePBX->Core->getAllDIDs();
	            $data = array();
	            $this->FreePBX->Modules->loadFunctionsInc("callrecording");
	            foreach($dids as $did) {
	                $key = $did['extension']."/".$did["cidnum"];
	                $call_rec = callrecording_display_get('did', $did['extension'], $did["cidnum"]);
	                if(!empty($call_rec)) {
	                    $data[$key] = array(
	                            "callrecording" => $call_rec
	                    );
	                } else {
	                    $data[$key] = array(
	                            "callrecording" => 'dontcare'
	                    );
	                }
	            }
	            break;
	    }
	    return $data;
	}

	public function bulkhandlerImport($type, $rawData, $replaceExisting = false) {
	    switch ($type) {
	        case 'dids':
	            $this->FreePBX->Modules->loadFunctionsInc("callrecording");
	            foreach ($rawData as $data) {
                    $data['callrecording'] = isset($data['callrecording'])?$data['callrecording']:'dontcare';
	                callrecording_display_update('did', $data['callrecording'], $data['extension'], $data["cidnum"]);
	            }
	            break;
	    }
	}
}
