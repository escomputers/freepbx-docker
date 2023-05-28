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

use InvalidArgumentException;

/**
 * This adapter supports glob database files compiled into the Freedesktop format.
 * The implementation here doesn't strictly follow the spec when it comes to matching
 * against patterns. A precompiled glob database file comes with this library and
 * is located in `resources/glob.db`. You may also have a much more current version
 * of such a file on your system. Most often those files are located below `/usr/share/mime`.
 *
 * @link http://standards.freedesktop.org/shared-mime-info-spec/shared-mime-info-spec-0.13.html
 */
class Freedesktop extends \mm\Mime\Type\Glob\Adapter {

	public function __construct($config) {
		$this->_read($config['file']);
	}

	public function register($item, $indent = 0, $priority = 50) {
		return $this->_register($item, $indent, $priority);
	}

	public function to($type) {
		return $this->_to($type);
	}

	public function analyze($file, $reverse = false) {
		if ($reverse) {
			return $this->_testReverse($file, $this->_items);
		}
		if ($results = $this->_test($file, $this->_items, true)) {
			return array_unique($results);
		}
		return $this->_test($file, $this->_items, false);
	}

	protected function _read($file) {
		$handle = fopen($file, 'r');

		$itemRegex = '^(\d{2}:)?[-\w.+]*\/[-\w.+]+:[\*\.a-zA-Z0-9]*$';

		if (!preg_match("/{$itemRegex}/m", fread($handle, 4096))) {
			throw new InvalidArgumentException("File `{$file}` has wrong format");
		}
		rewind($handle);

		while (!feof($handle)) {
			$line = trim(fgets($handle));

			if (empty($line) || $line[0] === '#') {
				continue;
			}
			$line = explode(':', $line);

			if (count($line) > 2) {
				list($priority, $mime_type, $pattern) = $line;
			} else {
				$priority = null;
				list($mime_type, $pattern) = $line;
			}

			/* Regex setup to match only items we can use for our simplified matching. */
			if (!preg_match('/^(\*\.)+([a-zA-Z0-9\.]+)$/', $pattern, $matches)) {
				continue;
			}
			$pattern = $matches[2];

			$this->_register(compact('mime_type', 'pattern', 'priority'));
		}
		fclose($handle);
	}
}

?>