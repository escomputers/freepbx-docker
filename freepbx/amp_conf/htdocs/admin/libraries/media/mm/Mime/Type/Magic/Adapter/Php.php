<?php
/**
 * mm: the PHP media library
 *
 * Copyright (c) 2007-2014 David Persson
 *
 * Distributed under the terms of the MIT License.
 * Redistributions of files must retain the above copyright notice.
 */

namespace mm\Mime\Type\Magic\Adapter;

/**
 * Can parse files containing one huge PHP array.
 *
 * Files must look like this:
 * {{{
 * <?php return [
 *     item0,
 *     item1,
 *     item2,
 *     item3,
 * ]; ?>
 * }}}
 */
class Php extends \mm\Mime\Type\Magic\Adapter {

	public function __construct($config) {
		foreach (require $config['file'] as $item) {
			$this->_register($item);
		}
	}

	public function analyze($file) {
		return $this->_test($handle, $this->_items);
	}

	public function to($type) {
		return $this->_to($type);
	}

	public function register($item) {
		return $this->_register($item);
	}
}

?>