<?php
namespace FreePBX\Builtin;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
	public function runRestore(){
		$configs = $this->getConfigs();
		$backupinfo = $this->getBackupInfo();
		$sql = "UPDATE IGNORE freepbx_settings SET `value` = :value WHERE `keyword` = :keyword AND `module` = ''";
		$sth = $this->FreePBX->Database->prepare($sql);
		//check oembranding is installed and licensed
		$skinsettings = array("VIEW_MENU", "VIEW_LOGIN", "VIEW_FOOTER_CONTENT", "DASHBOARD_OVERRIDE_BASIC", "DASHBOARD_FREEPBX_BRAND", "BRAND_IMAGE_TANGO_LEFT", "BRAND_IMAGE_FREEPBX_LINK_LEFT", "BRAND_IMAGE_FAVICON", "BRAND_CSS_CUSTOM", "BRAND_ALT_JS");
		if ($this->FreePBX->Modules->checkStatus("oembranding") && !$this->FreePBX->Oembranding->isLicensed()) {
			$query = "UPDATE freepbx_settings SET `value`=`defaultval` Where `value` like'modules/oembranding%';";
			$st = $this->FreePBX->Database->prepare($query);
			$st->execute();
		}
		foreach($configs['settings'] as $keyword => $value) {
			if ($keyword === 'AMPMGRPASS') {
				$this->log(sprintf(_("Ignorning restore of AMPMGRPASS Advanced Settings from %s"), $module));
				continue;
			}
			if($keyword === 'FREEPBX_SYSTEM_IDENT' && $backupinfo['warmspareenabled'] == 'yes'){
				$this->log(_("Ignorning restore of FREEPBX_SYSTEM_IDENT from Advanced Settings"));
				continue;
			}
			if ($keyword === 'MODULE_REPO') {
				$this->log(sprintf(_("Ignorning restore of MODULE_REPO Advanced Settings from %s"), $module));
				continue;
			}
			if(in_array($keyword,$skinsettings)){
				$this->log(sprintf(_("Ignorning Brand view  Setting %s"), $keyword));
				continue;
			}
			$sth->execute([
				":keyword" => $keyword,
				":value" => $value
			]);
		}
		$this->FreePBX->Framework->amiUpdate(true,true,true);
	}

	public function processLegacy($pdo, $data, $tables, $unknownTables){
		$sql = "SELECT * FROM ampusers";
                $sth = $pdo->prepare($sql);
                $sth->execute();
                $res = $sth->fetchAll(\PDO::FETCH_ASSOC);
		if(!empty($res)) {
				$query = 'Truncate ampusers';
				$sth = $this->FreePBX->Database->prepare($query);
				$sth->execute();
				$this->log(_("Cleared AMPUSERS"));
				$sql = "INSERT INTO ampusers(`username`,`password_sha1`,`extension_low`,`extension_high`,`deptname`,`sections` )VALUES(:username,:password_sha1,:extension_low,:extension_high,:deptname,:sections)";
                        	$sth = $this->FreePBX->Database->prepare($sql);
			foreach($res as $user) {
				$sth->execute([
                                        ":username" => $user['username'],
                                        ":password_sha1" => $user['password_sha1'],
                                        ":extension_low" => $user['extension_low'],
                                        ":extension_high" => $user['extension_high'],
                                        ":deptname" => $user['deptname'],
                                        ":sections" => $user['sections']
					]);
			}
		}

		$skipcloudskin = array("VIEW_MENU", "VIEW_LOGIN", "VIEW_FOOTER_CONTENT", "DASHBOARD_OVERRIDE_BASIC", "DASHBOARD_FREEPBX_BRAND", "BRAND_IMAGE_TANGO_LEFT", "BRAND_IMAGE_FREEPBX_LINK_LEFT", "BRAND_IMAGE_FAVICON", "BRAND_CSS_CUSTOM", "BRAND_ALT_JS");
		$skipcdrval = array("CDRDBHOST", "CDRDBNAME", "CDRDBPASS", "CDRDBPORT", "CDRDBTYPE", "CDRDBUSER");
		$sql = "SELECT `keyword`, `value` FROM freepbx_settings WHERE module= ''";
		$sth = $pdo->prepare($sql);
		$sth->execute();
		$res = $sth->fetchAll(\PDO::FETCH_ASSOC);
		if(!empty($res)) {
			$this->log(sprintf(_("Importing Advanced Settings from %s"), $module));
			$sql = "UPDATE IGNORE freepbx_settings SET `value` = :value WHERE `keyword` = :keyword AND `module` = ''";
			$usth = $this->FreePBX->Database->prepare($sql);

			foreach($res as $data) {
				if ($data['keyword'] === 'AMPMGRPASS') {
					$this->log(sprintf(_("Ignorning restore of AMPMGRPASS Advanced Settings from %s"), $module));
					continue;
				}
				if ($data['keyword'] === 'MODULE_REPO') {
					$this->log(sprintf(_("Ignorning restore of Repo Server URLs %s"), $module));
					continue;
				}
				if (in_array($data['keyword'], $skipcdrval)) {
					$this->log(sprintf(_("Ignorning restore of %s Advanced Settings"), $data['keyword']));
					continue;
				}
				if (in_array($data['keyword'], $skipcloudskin)) {
					$this->log(sprintf(_("Ignorning restore of %s Advanced Settings from cloudskin"),$data['keyword']));
					continue;
				}
				if ($data['keyword'] === 'AMPWEBROOT') {
					$this->log("Ignorning restore of AMPWEBROOT Advanced Settings");
					$this->log(sprintf(_("Current FreePBX Web Root Directory is %s"), $this->FreePBX->Config->get_conf_setting('AMPWEBROOT')));
					continue;
				}
				if ($data['keyword'] == 'ASTMODDIR') {
					$this->log("Ignorning restore of ASTMODDIR Advanced Settings");
					$this->log(sprintf(_("Current Asterisk Modules Directory is %s"), $this->FreePBX->Config->get_conf_setting('ASTMODDIR',true)));
					continue;
				}
				$val = str_replace('\r\n', "\r\n", $data['value']);
				$usth->execute([
					":keyword" => $data['keyword'],
					":value" => $val
				]);
			}
		}
		$this->FreePBX->Framework->amiUpdate(true,true,true);
	}

	public function reset() {
		//dont uninstall as it will screw up the entire system
		//Also it shouldn't be allowed even if forced!
	}
}
