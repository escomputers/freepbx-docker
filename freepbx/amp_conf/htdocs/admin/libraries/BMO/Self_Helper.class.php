<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * DB_Helper catches the FreePBX object, and provides autoloading
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
namespace FreePBX;
class Self_Helper extends DB_Helper {

	private $moduleNamespace = '\\FreePBX\\Modules\\';
	private $freepbxNamespace = '\\FreePBX\\';

	public function __construct($freepbx = null) {
		if (!is_object($freepbx)) {
			throw new \Exception("Need to be instantiated with a FreePBX Object",500);
		}
		$this->FreePBX = \FreePBX::create();
	}

	/**
	 * PHP Magic __get - runs AutoLoader if BMO doesn't already have the object.
	 *
	 * @param $var Class Name
	 * @return object New Object
	 */
	public function __get($var) {
		// Does the BMO know about this object already?
		if (isset(\FreePBX::create()->$var)) {
			$this->$var = \FreePBX::create()->$var;
			return $this->$var;
		}

		return $this->autoLoad($var);
	}

	/**
	 * PHP Magic __call - runs AutoLoader
	 *
	 * Note that this DELIBERATELY doesn't look at the BMO cache for $obj.
	 * This is used when you need to pass params to an object on creation,
	 * which means they may be different each time. Note that autoLoad DOES
	 * save it as FreePBX::$var, so it will continue to be used there.
	 *
	 * @param $var Class Name
	 * @param $args Any params to be passed to the new object
	 * @return object New Object
	 */
	public function __call($var, $args) {
		return $this->autoLoad($var, $args);
	}

	/**
	* Used to inject a new class into the BMO construct
	* @param {string} $classname The class name
	* @param {string} $hint Where to find the class (directory)
	*/
	public function injectClass($classname, $hint = null) {
		$this->loadObject($classname, $hint);
		$this->autoLoad($classname);
	}

	/**
	 * AutoLoader for BMO.
	 *
	 * This implements a half-arsed spl_autoload that ignore PSR1 and PSR4. I am
	 * admitting that at the start so no-one gets on my case about it.
	 *
	 * However, as we're having no end of issues with PHP Autoloading things properly
	 * (as of PHP 5.3.3, which is our minimum version at this point in time), this will
	 * do in the interim.
	 *
	 * This tries to load the BMO Object called. It looks first in the BMO Library
	 * dir, which is assumed to be the same directory as this file. It then grabs
	 * a list of all active modules, and looks through them for the class requested.
	 *
	 * If it doesn't find it, it'll throw an exception telling you why.
	 *
	 * @return object The object as an object!
	 */
	private function autoLoad() {
		// Figure out what is wanted, and return it.
		if (func_num_args() == 0) {
			throw new \Exception("Nothing given to the AutoLoader");
		}

		// If we have TWO arguments, we've been called by __call, if we only have
		// one we've been called by __get.

		$args = func_get_args();
		$var = $args[0];

		if ($var == "FreePBX") {
			throw new \Exception("No. You ALREADY HAVE the FreePBX Object. You don't need another one.",500);
		}

		// Ensure no-one's trying to include something with a path in it.
		if (strpos($var, "/") || strpos($var, "..")) {
			throw new \Exception("Invalid include given to AutoLoader - $var",500);
		}

		// This will throw an Exception if it can't find the class.
		$this->loadObject($var);

		// Never try to fix autoload cases, these must be correct.
		$var = $this->Modules->cleanModuleName($var, false);

		$class = class_exists($this->moduleNamespace.$var,false) ? $this->moduleNamespace.$var : (class_exists($this->freepbxNamespace.$var,false) ? $this->freepbxNamespace.$var : $var);

		// If loadObject didn't contain the class we were looking for, crash
		if (!class_exists($class,false)) {
			throw new \Exception("Tried to load $var, but $class does not exist. Bug");
		}
		// Now, we may have paramters (__call), or we may not..
		if (isset($args[1]) && isset($args[1][0])) {
			if (isset($args[1][1])) {
				throw new \Exception(_("Multiple params to autoload (__call) not supported. Don't do that. Or re-write this."),500);
			}
			$this->$var = new $class($this, $args[1][0]);
		} else {
			$this->$var = new $class($this);
		}
		// We keep the object inside BMO, even if it has params. This is so you can keep using the
		// same object, and not creating it every time
		\FreePBX::create()->$var = $this->$var;
		return $this->$var;
	}

	/**
	 * Find the file for the object
	 * @param string $objname The Object Name (same as class name, filename)
	 * @param string $hint The location of the Class file
	 * @return bool True if found or throws exception
	 */
	private function loadObject($objname, $hint = null) {
		$objname = str_ireplace('FreePBX\\modules\\','',$objname);
		$class = class_exists($this->moduleNamespace.$objname,false) ? $this->moduleNamespace.$objname : (class_exists($this->freepbxNamespace.$objname,false) ? $this->freepbxNamespace.$objname : $objname);

		// If it already exists, we're fine.
		if (class_exists($class,false)) {
			//do reflection tests for any non-bmo class we dont want to load here
			$class = new \ReflectionClass($class);

			if($class->getNamespaceName() === "FreePBX" || $class->implementsInterface('\FreePBX\BMO')) {
				return true;
			}
		}

		// This is the file we loaded the class from, for debugging later.
		$loaded = false;

		if ($hint) {
			if (!file_exists($hint)) {
				throw new \Exception(sprintf(_("Attempted to load %s with a hint of %s and it didn't exist"),$objname,$hint),404);
			} else {
				$try = $hint;
			}
		} else {
			// Does this exist as a default Library inside BMO?
			$try = __DIR__."/$objname.class.php";
		}

		if (file_exists($try)) {
			include $try;
			$loaded = $try;
		} else {
			// It's a module, hopefully.
			// This is our root to search from
			$objname = $this->Modules->cleanModuleName($objname);
			$path = $this->Config->get_conf_setting('AMPWEBROOT')."/admin/modules/";

			$active_modules = array_keys($this->Modules->getActiveModules());
			foreach ($active_modules as $module) {
				// Lets try this one..
				//TODO: this needs to look with dirname not from webroot
				$try = $path.$module."/$objname.class.php";
				if(file_exists($try)) {
					//Now we need to make sure this is not a revoked module!
					try {
						$signature = $this->Modules->getSignature($module);
						if(!empty($signature['status'])) {
							$revoked = $signature['status'] & GPG::STATE_REVOKED;
							if($revoked) {
								return false;
							}
						}
					} catch(\Exception $e) {}

					$info = $this->Modules->getInfo($module);
					$needs_zend = isset($info[$module]['depends']['phpcomponent']) && stristr($info[$module]['depends']['phpcomponent'], 'zend');
					$licFileExists = glob ('/etc/schmooze/license-*.zl');
					$complete_zend = (!function_exists('zend_loader_install_license') || empty($licFileExists));
					if ($needs_zend && class_exists('\Schmooze\Zend',false) && \Schmooze\Zend::fileIsLicensed($try) && $complete_zend) {
						break;
					}

					include $try;
					$loaded = $try;
					break;
				}
			}
		}

		// Right, after all of this we should now have our object ready to create.
		if (!class_exists($objname,false) && !class_exists($this->moduleNamespace.$objname,false) && !class_exists($this->freepbxNamespace.$objname,false)) {
			// Bad things have happened.
			if (!$loaded) {
				$sobjname = strtolower($objname);
				throw new \Exception(sprintf(_("Unable to locate the FreePBX BMO Class '%s'"),$objname) . sprintf(_("A required module might be disabled or uninstalled. Recommended steps (run from the CLI): 1) fwconsole ma install %s 2) fwconsole ma enable %s"),$sobjname,$sobjname),404);
				//die_freepbx(sprintf(_("Unable to locate the FreePBX BMO Class '%s'"),$objname), sprintf(_("A required module might be disabled or uninstalled. Recommended steps (run from the CLI): 1) amportal a ma install %s 2) amportal a ma enable %s"),$sobjname,$sobjname));
			}

			// We loaded a file that claimed to represent that class, but didn't.
			throw new \Exception(sprintf(_("Attempted to load %s but it didn't define the class %s"),$try,$objname),404);
		}

		return true;
	}
}
