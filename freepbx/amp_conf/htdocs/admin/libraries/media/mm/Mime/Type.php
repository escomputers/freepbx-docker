<?php
/**
 * mm: the PHP media library
 *
 * Copyright (c) 2007-2014 David Persson
 *
 * Distributed under the terms of the MIT License.
 * Redistributions of files must retain the above copyright notice.
 */

namespace mm\Mime;

use OutOfBoundsException;

/**
 * The `Type` class allows for detecting MIME types of files and streams
 * by analyzing it's contents and/or extension. The class makes use of two
 * adapters (`magic` and `glob`) which must be configured before using any
 * of the methods.
 */
class Type {

	const REGEX = '^[\-\w\.\+]+\/[\-\w\.\+]+$';

	/**
	 * Magic.
	 *
	 * @see config()
	 * @var mm\Mime\Type\Magic\Adapter
	 */
	public static $magic;

	/**
	 * Glob.
	 *
	 * @see config()
	 * @var mm\Mime\Type\Glob\Adapter
	 */
	public static $glob;

	/**
	 * Mapping MIME type (part/needle) to media name.
	 *
	 * @see guessName()
	 * @var array
	 */
	public static $name = array(
		'application/ogg'       => 'audio',
		'application/pdf'       => 'document',
		'application/msword'    => 'document',
		'officedocument'        => 'document',
		'image/icon'            => 'icon',
		'text/css'              => 'css',
		'text/javascript'       => 'javascript',
		'text/code'             => 'generic',
		'text/rtf'              => 'document',
		'text/plain'            => 'text',
		'image/'                => 'image',
		'audio/'                => 'audio',
		'video/'                => 'video',
		'/'                     => 'generic'
	);

	/**
	 * Preferred types to use if yielding multiple results.
	 *
	 * @see guessType()
	 */
	public static $preferredTypes = array(
		'audio/ogg'
	);

	/**
	 * Preferred extensions to use if yielding multiple results.
	 *
	 * @see guessExtension()
	 */
	public static $preferredExtensions = array(
		'bz2', 'css', 'doc', 'html', 'jpg',
		'mov', 'mpeg', 'mp3', 'mp4', 'm4a', 'oga', 'ogv',
		'php', 'ps',  'rm', 'ra', 'rv', 'swf',
		'tar', 'tiff', 'txt', 'xhtml', 'xml', 'xsl',
		'mo'
	);

	/**
	 * Set and change configuration during runtime.
	 *
	 * @param string $type Either `'magic'` or `'glob'`.
	 * @param array $config Config specifying engine and db
	 *              e.g. `['adapter' => 'Fileinfo', 'file' => '/etc/magic']`.
	 */
	public static function config($type, array $config = array()) {
		if ($type != 'magic' && $type != 'glob') {
			throw new OutOfBoundsException("Invalid type `{$type}`.");
		}
		$class = '\mm\Mime\Type\\' . ucfirst($type) . '\Adapter\\' . $config['adapter'];

		static::${$type} = new $class($config);
	}

	public static function reset() {
		static::$glob = static::$magic = null;
	}

	/**
	 * Simplifies a MIME type string.
	 *
	 * @param string $mimeType A valid MIME type string.
	 * @param boolean If `false` removes properties, defaults to `false`.
	 * @param boolean If `false` removes experimental indicators, defaults to `false`.
	 * @return string The simplified MIME type string.
	 */
	public static function simplify($mimeType, $properties = false, $experimental = false) {
		if (!$experimental) {
			$mimeType = str_replace('x-', null, $mimeType);
		}

		if (!$properties) {
			if (strpos($mimeType, ';') !== false) {
				$mimeType = strtok($mimeType, ';');
			} else {
				$mimeType = strtok($mimeType, ' ');
			}
		}
		return $mimeType;
	}

	/**
	 * Guesses the extension (suffix) for an existing file or a MIME type.
	 *
	 * @param string|resource $file Path to a file, an open handle to a file or a MIME type string.
	 * @return string|void A string with the first matching extension (w/o leading dot).
	 */
	public static function guessExtension($file) {
		if (is_string($file) && preg_match('/' . static::REGEX . '/', $file)) {
			$mimeType = static::simplify($file, false, true);
		} else {
			$mimeType = static::guessType($file);
		}

		$globMatch = (array) static::$glob->analyze($mimeType, true);
		if (count($globMatch) === 1) {
			return array_shift($globMatch);
		}

		$preferMatch = array_intersect($globMatch, static::$preferredExtensions);
		if (count($preferMatch) === 1) {
			return array_shift($preferMatch);
		}
	}

	/**
	 * Guesses the MIME type of the file.
	 *
	 * @param string|resource $file Path to/name of a file or an open handle to a file.
	 * @param options $options Valid options are:
	 *                - `'paranoid'`: If set to `true` the file's MIME type is guessed by
	 *                                looking at it's contents only.
	 *                - `'properties'`: Leave properties intact, defaults to `false`.
	 *                - `'experimental'`: Leave experimental indicators intact, defaults to `true`.
	 * @return string|void String with MIME type on success.
	 */
	public static function guessType($file, $options = array()) {
		$defaults = array(
			'paranoid' => false,
			'properties' => false,
			'experimental' => true
		);
		extract($options + $defaults);

		$magicMatch = $globMatch = array();
		$openedHere = false;

		if (!$paranoid) {
			if (is_resource($file)) {
				$meta = stream_get_meta_data($file);
				$name = $meta['uri'];
			} else {
				$name = $file;
			}
			$globMatch = (array) static::$glob->analyze($name);

			if (count($globMatch) === 1) {
				 return static::simplify(array_shift($globMatch), $properties, $experimental);
			}
			$preferMatch = array_intersect($globMatch, static::$preferredTypes);

			if (count($preferMatch) === 1) {
				return array_shift($preferMatch);
			}
		}

		if (is_resource($file)) {
			$handle = $file;
		} elseif (is_file($file)) {
			$handle = fopen($file, 'r');
			$openedHere = true;
		} else {
			return;
		}

		$magicMatch = static::$magic->analyze($handle);
		$magicMatch = empty($magicMatch) ? array() : array($magicMatch);

		if (empty($magicMatch)) {
			rewind($handle);
			$peek = fread($handle, 32);

			if ($openedHere) {
				fclose($handle);
			}

			if (preg_match('/[\t\n\r]+/', $peek)) {
				return 'text/plain';
			}
			return 'application/octet-stream';
		}

		if ($openedHere) {
			fclose($handle);
		}

		if (count($magicMatch) === 1) {
			return static::simplify(array_shift($magicMatch), $properties, $experimental);
		}

		if ($globMatch && $magicMatch) {
			$combinedMatch = array_intersect($globMatch, $magicMatch);

			if (count($combinedMatch) === 1) {
				return static::simplify(array_shift($combinedMatch), $properties, $experimental);
			}
		}
	}

	/**
	 * Determines lowercase media name.
	 *
	 * @param string $file Path to/name of a file, an open handle to a file or a MIME type string.
	 * @return string
	 */
	public static function guessName($file) {
		if (is_string($file) && preg_match('/' . static::REGEX . '/', $file)) {
			$mimeType = static::simplify($file);
		} else {
			$mimeType = static::guessType($file, array('experimental' => false));
		}
		foreach (static::$name as $pattern => $name) {
			if (strpos($mimeType, $pattern) !== false) {
				return $name;
			}
		}
		return 'generic';
	}
}
