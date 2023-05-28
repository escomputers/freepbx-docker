<?php

namespace FreePBX\modules\Dashboard\Sections;

class Notepad {
	public $rawname = 'Notepad';

	public function getSections($order) {
		return array(
			array(
				"title" => _("Notepad"),
				"group" => _("Notes"),
				"width" => "550px",
				"order" => isset($order['notepad']) ? $order['notepad'] : "400",
				"section" => "notepad"
			)
		);
	}

	public function getContent($section) {
		if (!class_exists('TimeUtils')) {
			include dirname(__DIR__).'/classes/TimeUtils.class.php';
		}
		$notes = $this->getNotes();
		foreach ($notes as $ts=>&$note) {
			$note->time = date("Y-m-d H:i:s", $ts);
			$note->ago = \TimeUtils::getReadable(time() - $ts, 1);
		}
		return load_view(dirname(__DIR__) . "/views/sections/notepad.php", array("data" => $notes));
	}

	public function getNotes() {
		$dash = \FreePBX::Dashboard();
		return $dash->getAll("notes");
	}
}