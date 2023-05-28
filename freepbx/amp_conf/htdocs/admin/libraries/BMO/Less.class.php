<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * Object wrapper for the less compiler in FreePBX
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
namespace FreePBX;
if(!class_exists('Less_Parser', false)) {
	include dirname(dirname(__FILE__)).'/less/Less.php';
}

class Less extends \Less_Parser {
	public function __construct($freepbx = null, $env = null) {
		if ($freepbx == null) {
			throw new Exception("Not given a FreePBX Object");
		}
		$this->FreePBX = $freepbx;

		parent::__construct($env);
	}

	/**
	 * Generate all FreePBX Main Style Sheets
	 * @param {array} $variables = array() Array of variables to override
	 */
	public function generateMainStyles($variables = array()) {
		$this->FreePBX->Performance->Start("Less Parser Generate Main Styles");
		$less_rel = '../';
		$less_path = $this->FreePBX->Config->get('AMPWEBROOT') .'/admin/assets/less';

		$varOverride = $this->FreePBX->Hooks->processHooks($variables);
		if(!empty($varOverride)) {
			foreach($varOverride as $o) {
				$variables = array_merge($o, $variables);
			}
		}

		//compile these all into one giant file so that variables cross
		//"jq-ui-bootstrap"
		$less_dirs = array("freepbx","schmooze-font");
		$out = array();
		$out['compiled_less_files'] = array();
		foreach($less_dirs as $dir) {
			$path = $less_path."/".$dir;
			if (is_dir($path)) {
				$files[$path."/".$dir.".less"] = $less_rel;
			}
		}

		\Less_Cache::$cache_dir = $less_path.'/cache';
		$filename = \Less_Cache::Get( $files, array('compress' => true), $variables );
		try {
			$filename = \Less_Cache::Get( $files, array('compress' => true), $variables );
		} catch(\Exception $e) {
			dbug($e);
			die_freepbx(sprintf(_('Can not write to cache folder at %s/cache. Please run (from the CLI): %s'),$dir,'fwconsole chown'));
		}
		$out['compiled_less_files'][] = 'cache/'.$filename;

		$extra_less_dirs = array("buttons");
		$out['extra_compiled_less_files'] = array();
		foreach($extra_less_dirs as $dir) {
			$path = $less_path."/".$dir;
			if (is_dir($path)) {
				$file = $this->getCachedFile($path,$less_rel, $variables);
				$out['extra_compiled_less_files'][$dir] = $dir.'/cache/'.$file;
			}
		}
		$this->FreePBX->Performance->Stop("Less Parser Generate Main Styles");
		return $out;
	}

	/**
	 * Generate Individual Module Style Sheets
	 * @param {string} $module    The module name
	 * @param {array} $variables =             array() Array of variables to override
	 */
	public function generateModuleStyles($module, $pagename = '', $variables = array()) {
		$this->FreePBX->Performance->Start("Less Parser Generate Module Styles");
		$less_rel = '/admin/assets/' . $module;
		$less_path = $this->FreePBX->Config->get('AMPWEBROOT') . '/admin/modules/' . $module . '/assets/less';
		$files = array();
		if(file_exists($less_path)) {
			$varOverride = $this->FreePBX->Hooks->processHooks($variables);
			if(!empty($varOverride)) {
				foreach($varOverride as $o) {
					$variables = array_merge($o, $variables);
				}
			}
			$f = $this->getCachedFile($less_path,$less_rel,$variables);
			if(!empty($f)) {
				$files[] = 'cache/'.$f;
			}

			if(!empty($pagename)) {
				$page_less_path = $less_path."/".$pagename;
				if(file_exists($page_less_path)) {
					$f = $this->getCachedFile($page_less_path,$less_rel,$variables);
					if(!empty($f)) {
						$files[] = $pagename.'/cache/'.$f;
					}
				}
			} else {
				//we dont know the page so generate it for all page folders
				foreach(glob($less_path.'/*', GLOB_ONLYDIR) as $dir) {
					if(!preg_match('/cache$/',$dir)) {
						$this->getCachedFile($dir,$less_rel,$variables);
					}
				}
			}
		}
		$this->FreePBX->Performance->Stop("Less Parser Generate Module Styles");
		return $files;
	}

	/**
	 * Parse a Directory to find the appropriate less files
	 *
	 * If a bootstrap.less file exists then parse that only (looking for imports)
	 * Otherwise just find the files to parse at the same time. This will return
	 * the generated CSS however it's highly advisable you end up using getCacheFile
	 *
	 * @param string $dir The directory housing the less files
	 * @param string $uri_root The uri root of the web request
	 * @return string The CSS file output
	 */
	public function parseDir($dir, $uri_root = '') {
		//Load bootstrap only if it exists as this will tell us the correct load order
		if(!file_exists($dir.'/cache')) {
			// Explicitly don't trigger an E_WARNING, as PHP doesn't tell you what
			// it was trying to mkdir, so the error is useless.
			if(!@mkdir($dir.'/cache')) {
				die_freepbx(sprintf(_('Can not create cache folder at %s/cache. Please run (from the CLI): %s'),$dir,'fwconsole chown'));
			}
			$ampowner = $this->FreePBX->Config->get('AMPASTERISKWEBUSER');
			$ampgroup =  $ampowner != $this->FreePBX->Config->get('AMPASTERISKUSER') ? $this->FreePBX->Config->get('AMPASTERISKGROUP') : $ampowner;
			chown($dir.'/cache', $ampowner);
			chgrp($dir.'/cache', $ampgroup);
		}
		$this->SetOption('cache_dir',$dir.'/cache');
		$this->SetOption('compress',true);
		$basename = basename($dir);
		if(file_exists($dir."/bootstrap.less")) {
			$this->parseFile($dir."/bootstrap.less", $uri_root);
		} elseif(file_exists($dir."/".$basename.".less")) {
			$this->parseFile($dir."/".$basename.".less", $uri_root);
		} else {
			//load them all randomly. Probably in alpha order
			foreach(glob($dir."/*.less") as $file) {
				$this->parseFile($file, $uri_root);
			}
		}
		return $this->getCss();
	}

	/**
	 * Generates and Gets the Cached files
	 *
	 * This will generated a compiled less file into css format
	 * but it will cache it so that it doesnt happen unless the file has changed
	 *
	 * @param string $dir The directory housing the less files
	 * @param string $uri_root The uri root of the web request
	 * @param array $variables Array of variables to override
	 * @return string the CSS filename
	 */
	public function getCachedFile($dir, $uri_root = '', $variables = array()) {
		if(!file_exists($dir.'/cache')) {
			// Explicitly don't trigger an E_WARNING, as PHP doesn't tell you what
			// it was trying to mkdir, so the error is useless.
			if(!@mkdir($dir.'/cache')) {
				die_freepbx(sprintf(_('Can not create the LESS cache folder at %s. Please run (from the CLI): %s'),$dir.'/cache','fwconsole chown'));
			}
			$ampowner = $this->FreePBX->Config->get('AMPASTERISKWEBUSER');
			$ampgroup =  $ampowner != $this->FreePBX->Config->get('AMPASTERISKUSER') ? $this->FreePBX->Config->get('AMPASTERISKGROUP') : $ampowner;
			chown($dir.'/cache', $ampowner);
			chgrp($dir.'/cache', $ampgroup);
		}
		if(!is_readable($dir)) {
			die_freepbx(sprintf(_('Can not read from the LESS folder at %s. Please run (from the CLI): %s'),$dir,'fwconsole chown'));
		}
		if(!is_readable($dir.'/cache') || !is_writable($dir.'/cache')) {
			die_freepbx(sprintf(_('Can not write to the LESS cache folder at %s. Please run (from the CLI): %s'),$dir.'/cache','fwconsole chown'));
		}
		\Less_Cache::$cache_dir = $dir.'/cache';
		$files = array();
		$basename = basename($dir);
		try {
			if(file_exists($dir."/bootstrap.less")) {
				$files = array( $dir."/bootstrap.less" => $uri_root );
				$filename = \Less_Cache::Get( $files, array('compress' => true), $variables );
			} elseif(file_exists($dir."/".$basename.".less")) {
				$files = array( $dir."/".$basename.".less" => $uri_root );
				$filename = \Less_Cache::Get( $files, array('compress' => true), $variables );
			} else {
				//load them all randomly. Probably in alpha order
				foreach(glob($dir."/*.less") as $file) {
					$files[$file] = $uri_root;
				}
				uksort($files, "strcmp");

				$filename = \Less_Cache::Get( $files, array('compress' => true), $variables );
			}
		} catch(\Exception $e) {
			die_freepbx(sprintf(_('Error with the templating engine: %s'),$e->getMessage()));
		}
		return $filename;
	}
}
