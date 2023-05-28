<?php
class modulelist{
	public static function create($db) {
		return \FreePBX::create()->Modulelist;
	}
	public function is_loaded() {
		return \FreePBX::Modulelist()->is_loaded();
	}
	public function initialize($module_list) {
		return \FreePBX::Modulelist()->initialize($module_list);
	}
	public function invalidate() {
		return \FreePBX::Modulelist()->invalidate();
	}
}
