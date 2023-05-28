<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2016 Sangoma Technologies
 */

namespace FreePBX;
class Monitoring {
	protected static $status = array(
		0 => "OK",
		1 => "WARNING",
		2 => "CRITICAL",
	);
	const OK = 0;
	const WARNING = 1;
	const CRITICAL = 2;

	/**
	 * Gets a textual representation of the status from an integer
	 * @param integer $level
	 * @return string
	 */
	public static function getStatus($level = 0) {
		$status = "UNKNOWN";

		if (in_array($level, array_keys(self::$status))) {
			$status = self::$status[$level];
		}
		return $status;
	}

	/**
	 * Generate a report for sensu
	 * @param array $output
	 * @param integer $level
	 */
	public static function report($output, $level = 0) {
		if (is_array($output) || is_object($output)) {
			$output['status'] = self::getStatus($level);

			$output = json_encode($output);
		}

		print $output;

		exit($level);
	}
	
	/**
	 * asteriskInfo
	 *
	 * @return void
	 */
	public function asteriskInfo(){
		return engine_getinfo();
	}
	
	/**
	 * asteriskRunning
	 *
	 * @return void
	 */
	public function asteriskRunning(){
		if(file_exists('/var/run/asterisk/asterisk.ctl')){
			return true;
		}
		return false;
	}
	
	/**
	 * astmanInfo
	 *
	 * @return void
	 */
	public function astmanInfo($freepbx){
		$ami = $freepbx->astman->connected();
		if($ami){
			return true;
		}
		return false;
	}
	
	/**
	 * dbStatus
	 *
	 * @return void
	 */
	public function dbStatus(){
		if(DB_OK){
			return true;
		}
		return false;
	}
	
	/**
	 * GUIMode
	 *
	 * @param  mixed $freepbx
	 * @return void
	 */
	public function GUIMode($freepbx){
		return $freepbx->Config()->get('FPBXOPMODE');
	}
	
	/**
	 * setupWizardDetails
	 *
	 * @param  mixed $freepbx
	 * @return void
	 */
	public function setupWizardDetails($freepbx){
		$sql = $freepbx->database->prepare("SELECT val FROM `kvstore_OOBE` where `key` like ?");
		$sql->execute(array('completed'));
		return $sql->fetchAll(\PDO::FETCH_ASSOC);
	}
	
	/**
	 * autoUpdateDetails
	 *
	 * @return void
	 */
	public function autoUpdateDetails(){
		$um = new \FreePBX\Builtin\UpdateManager();
		return $um->getCurrentUpdateSettings();
	}
}