<?php /* $Id */
/**
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
*/
namespace FreePBX;


class Freepbx_conf {
	public $freepbx_conf;
	public function __construct() {
		$this->freepbx_conf = \FreePBX::create()->Config;
	}
	public static function create() {
		return \FreePBX::create()->Config;
	}
	/**
	 * Quick hack to just pass everything we don't know about to the existing class.
	 *
	 * There's nothing wrong with the freepbx_conf class, it just needs to be converted
	 * to use PDO.
	 *
	 */
	private function autoload($cmd, $args = null) {
		if (method_exists($this->freepbx_conf, $cmd)) {
			// OK, just pass it along.. Probably should put a warning
			// in here at some point, to encourage people to move the
			// code across to here and tidy it up.
			return call_user_func_array(array($this->freepbx_conf, $cmd),  $args);
		}
		throw new \Exception("Unknown function ".$cmd);
	}
	public function __get($var) { return $this->autoload($var); }
	public function __call($var, $args) { return $this->autoload($var, $args); }
	public static function __callStatic($var, $args) { return $this->autoload($var, $args); }
}