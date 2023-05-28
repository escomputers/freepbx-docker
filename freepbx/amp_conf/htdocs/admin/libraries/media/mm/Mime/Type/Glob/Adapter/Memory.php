<?php
/**
 * mm: the PHP media library
 *
 * Copyright (c) 2007-2014 David Persson
 *
 * Distributed under the terms of the MIT License.
 * Redistributions of files must retain the above copyright notice.
 */

namespace mm\Mime\Type\Glob\Adapter;

/**
 * This is a very simplistic adapter and can be used for building up your own
 * glob database in memory.
 */
class Memory extends \mm\Mime\Type\Glob\Adapter {

	public function register($item) {
		return $this->_register($item);
	}

	public function to($type) {
		return $this->_to($type);
	}

	public function analyze($file, $reverse = false) {
		if ($reverse) {
			return $this->_testReverse($file, $this->_items);
		}
		if ($results = $this->_test($file, $this->_items, true)) {
			return $results;
		}
		return $this->_test($file, $this->_items, false);
	}
}

?>