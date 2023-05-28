<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * DB_Helper provides $this->getConfig and $this->setConfig
 *
 * This is for use with FreePBX's BMO
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
namespace FreePBX;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;
class DB_Helper {

	private static $cache = [];

	private static $db = false;
	private static $dbname = "kvstore";

	private static $checked = false;

	/* Allow overriding of class detection */
	public $classOverride = false;

	/** Don't new DB_Helper */
	public function __construct() {
		throw new \Exception("You should never 'new' DB_Helper. Just use it as an 'extends'");
	}

	/** This is our pseudo-__construct, called whenever our public functions are called. */
	private static function checkDatabase($self) {
		// Have we already run?
		if (self::$checked === false) {
			self::$checked = array();
			self::$db = \FreePBX::create()->Database;
		}

		// What table should I be using for this call?
		$tablename = self::getTableName($self);

		// Have I already validated it?
		if (isset(self::$checked[$tablename])) {
			// Yes.
			return self::$checked[$tablename];
		}

		// Lets make sure it exists, and create it if it doesn't.
		try {
			$res = self::$db->query("SELECT * FROM `$tablename` LIMIT 1");
		} catch (\Exception $e) {
			if ($e->getCode() == "42S02") { // Table does not exist
				self::createTable($tablename);
			} else {
				self::checkException($e);
			}
		}

		self::$checked[$tablename] = array(
			"dbGet" => self::$db->prepare("SELECT `val`, `type` FROM `$tablename` WHERE `key` = :key AND `id` = :id"),
			"dbGetAll" => self::$db->prepare("SELECT `key` FROM `$tablename` WHERE `id` = :id ORDER BY `key`"),
			"dbDel" => self::$db->prepare("DELETE FROM `$tablename` WHERE `key` = :key  AND `id` = :id"),
			"dbAdd" => self::$db->prepare("INSERT INTO `$tablename` ( `key`, `val`, `type`, `id` ) VALUES ( :key, :val, :type, :id ) ON DUPLICATE KEY UPDATE `val` = :val, `type` = :type"),
			"dbDelId" => self::$db->prepare("DELETE FROM `$tablename` WHERE `id` = :id"),
			"dbGetFirst" => self::$db->prepare("SELECT `key` FROM `$tablename` WHERE `id` = :id ORDER BY `key` LIMIT 1"),
			"dbGetLast" => self::$db->prepare("SELECT `key` FROM `$tablename` WHERE `id` = :id ORDER BY `key` DESC LIMIT 1"),
			"dbEmpty" => self::$db->prepare("DELETE FROM `$tablename`"),
			"dbGetAllIds" => self::$db->prepare("SELECT DISTINCT(`id`) FROM `$tablename` WHERE `id` <> 'noid'"),
			"dbGetByType" => self::$db->prepare("SELECT * FROM `$tablename` WHERE `type` = :type"),
			"tablename" => $tablename
		);

		//clear/set in memory cache
		self::$cache[$tablename] = [];

		// Now this has run, everything IS JUST FINE.
		return self::$checked[$tablename];
	}

	/**
	 * Return the name of the table we're using.
	 *
	 * Will be 'kvstore_modulename'.  Backslashes, if the module is namespaced,
	 * will be converted to underscores.
	 *
	 * @param $self object the '$this' object used in $this->getConfig or setConfig
	 * @returns string database name
	 */
	public static function getTableName($self) {
		if ($self->classOverride) {
			$mod = $self->classOverride;
		} else {
			$mod = get_class($self);
		}
		$dbname = self::$dbname."_".str_replace('\\', '_', $mod);
		return $dbname;
	}

	/**
	 * Create the kvstore table for this module
	 *
	 * @param $tablename Table to create
	 */
	public static function createTable($tablename) {

		if (strpos($tablename, '`') !== false) {
			throw new \Exception("Table name contains a backtick, serious bug");
		}

		// Basic table definition
		$create = "CREATE TABLE IF NOT EXISTS `$tablename` ( `key` CHAR(255) NOT NULL, `val` VARCHAR(4096), `type` CHAR(16) DEFAULT NULL, `id` CHAR(255) DEFAULT NULL)";
		// These are limited to 190 chars as prefixes are limited to 255 chars in total (or 1000 in later versions
		// of mysql), and UTF can cause that to overflow. 190 is plenty.
		// Increase KVstore key lengths to the max of 190. This was 50 before
		// https://issues.freepbx.org/browse/FREEPBX-14956
		$index['uniqueindex'] = "ALTER TABLE `$tablename` ADD UNIQUE INDEX `uniqueindex` (`key`(190), `id`(190))";
		$index['keyindex'] = "ALTER TABLE `$tablename` ADD INDEX `keyindex` (`key`(190))";
		$index['idindex'] = "ALTER TABLE `$tablename` ADD INDEX `idindex` (`id`(190))";

		self::$db->query($create);
		foreach ($index as $i) {
			$res = self::$db->query($i);
		}
	}

	/**
	 * Requests a var previously stored
	 *
	 * getConfig requests the variable stored with the key $var, and returns it.
	 * Note that it will return an array or a StdObject if it was handed an array
	 * or object, respectively.
	 *
	 * The optional second parameter allows you to specify a sub-grouping - if
	 * you setConfig('foo', 'bar'), then getConfig('foo') == 'bar'. However,
	 * if you getConfig('foo', 1), that will return (bool) false.
	 *
	 * @param string $var Key to request (not null)
	 * @param string $id Optional sub-group ID.
	 * @return bool|string|array|StdObject Returns what was handed to setConfig, or bool false if it doesn't exist
	 */
	public function getConfig($var = null, $id = "noid") {
		if ($var === null) {
			throw new \Exception("Can't getConfig for null");
		}

		// Call our pretend __construct to get our prepared statements
		$p = self::checkDatabase($this);

		// Have we been asked to emulate another module? If so, reset.
		if ($this->classOverride) {
			$this->classOverride = false;
			// Preserve this to check for defaults
			$mod = $this->classOverride;
		} else {
			$mod = get_class($this);
		}

		if(isset(self::$cache[$p['tablename']][$id][$var])) {
			if(self::$cache[$p['tablename']][$id][$var]['type'] !== 'blob') {
				return self::$cache[$p['tablename']][$id][$var]['val'];
			}
			$res = [
				'type' => self::$cache[$p['tablename']][$id][$var]['type'],
				'val' => self::$cache[$p['tablename']][$id][$var]['val']
			];
		} else {
			$query[':id'] = $id;
			$query[':key'] = $var;

			try {
				$p['dbGet']->execute($query);
			} catch (\Exception $e) {
				self::checkException($e);
			}
			$res = $p['dbGet']->fetch();
		}

		if (!empty($res)) {
			// Found it! Is it linked to a blob?
			if ($res['type'] == "blob") {
				$tmparr = $this->getBlob($res['val']);
				$type = $tmparr['type'];
				$val = $tmparr['content'];
			} else {
				$type = $res['type'];
				$val = $res['val'];
			}

			if ($type == "json-obj") {
				$val = json_decode($val);
			} elseif ($type == "json-arr") {
				$val = json_decode($val, true);
			}

			self::$cache[$p['tablename']][$id][$var] = [
				'type' => $type,
				'val' => $val
			];
			return $val;
		}

		// We don't have a result. Maybe there's a default?
		if (class_exists($mod) && property_exists($mod, "dbDefaults")) {
			$def = $mod::$dbDefaults;
			if (isset($def[$var])) {
				return $def[$var];
			}
		}

		return false;
	}

	/**
	 * Store a variable, array or object.
	 *
	 * setConfig stores $val against $key, in a format that will return
	 * it almost identically when returned by getConfig.
	 *
	 * The optional third parameter allows you to specify a sub-grouping - if
	 * you setConfig('foo', 'bar'), then getConfig('foo') == 'bar'. However,
	 * getConfig('foo', 1) === (bool) false.
	 *
	 * @param string $key Key to set $var to (not null)
	 * @param string $var Value to set $key to. Can be (bool) false, which will delete the key.
	 * @param string $id Optional sub-group ID.
	 * @return true
	 */
	public function setConfig($key = null, $val = false, $id = "noid") {

		if ($key === null)
			throw new \Exception("Can't setConfig null");

		// Our pretend __construct();
		$p = self::checkDatabase($this);

		// Start building the query
		$query[':key'] = $key;
		$query[':id'] = $id;

		// Have we been asked to emulate another module? If so, reset.
		if ($this->classOverride) {
			$this->classOverride = false;
		}

		// Does this already exist?
		try {
			$p['dbGet']->execute($query);
			// Does this value already exist?
			$check = $p['dbGet']->fetch();
		} catch (\Exception $e) {
			self::checkException($e);
		}

		if ($val === false) { // Just wanted to delete
			try {
				if (!empty($check)) {
					// Yes it does. Is it a blob?
					if ($check['type'] == "blob") {
						// Delete that blob
						$this->deleteBlob($check['val']);
					}
					// Now delete the row.
					$p['dbDel']->execute($query);
				}
			} catch (\Exception $e) {
				self::checkException($e);
			}
			if(isset(self::$cache[$p['tablename']][$id][$key])) {
				unset(self::$cache[$p['tablename']][$id][$key]);
			}
			return true;
		}

		if (is_array($val)) {
			$query[':val'] = json_encode($val);
			$query[':type'] = "json-arr";
		} elseif (is_object($val)) {
			$query[':val'] = json_encode($val);
			$query[':type'] = "json-obj";
		} else {
			$query[':val'] = $val;
			$query[':type'] = null;
		}

		// Is our value too large to store in the standard kvstore?
		// If it is, store it as a blob, and link to it.
		if (strlen($query[':val']) > 4000) {
			if (!empty($check) && $check['type'] === "blob") {
				$uuid = $this->insertBlob($check['val'], $query[':val'], $query[':type']);
			} else {
				$uuid = $this->insertBlob(null, $query[':val'], $query[':type']);
			}
			$query[':type'] = "blob";
			$query[':val'] = $uuid;
			$val = $uuid;
		} else {
			if (!empty($check) && $check['type'] == "blob") {
				$this->deleteBlob($check['val']);
			}
		}

		self::$cache[$p['tablename']][$id][$key] = [
			'type' => $query[':type'],
			'val' => $val
		];
		//the check was already a blob and is now a blob again nothing changed
		if(!empty($check) && $check['val'] === $query[':val']) {
			return true;
		}
		$p['dbAdd']->execute($query);
		return true;
	}

	/**
	 * Alias function to delete
	 * @param {string} $key = null The key name
	 * @param string $id Optional sub-group ID.
	 */
	public function delConfig($key = null, $id = "noid") {
		$this->setConfig($key, false, $id);
	}

	/**
	 * Store multiple variables, arrays or objects.
	 *
	 * setMultiConfig is the same as setConfig, except it uses an associative array,
	 * and uses a transaction to speed up the commit.
	 *
	 * @param array $keyval
	 * @param string $id Optional sub-group ID.
	 * @return true
	 */
	public function setMultiConfig($keyval = false, $id = "noid") {
		if (!is_array($keyval)) {
			throw new \Exception('setMultiConfig was not given an array');
		}

		// Have we been asked to emulate another module? If so, error
		if ($this->classOverride) {
			// It'll be reset after the first one.
			throw new \Exception("Can't override with setMultiConfig");
		}

		self::$db->beginTransaction();
		foreach ($keyval as $key => $val) {
			$this->setConfig($key, $val, $id);
		}
		self::$db->commit();
	}

	/**
	 * Returns an associative array of all key=>value pairs referenced by $id
	 *
	 * If no $id was provided, return all pairs that weren't set with an $id.
	 * Returns an ordered list from however MySQL orders it (order by `key`)
	 *
	 * If null $id was provided then return every single entry
	 *
	 * @param string $id Optional sub-group ID.
	 * @return array
	 */
	public function getAll($id = "noid") {

		// Our pretend __construct();
		$p = self::checkDatabase($this);

		// Have we been asked to emulate another module? If so, error.
		if ($this->classOverride) {
			// It'll be reset after the first one.
			throw new \Exception("Can't override with getAll");
		}

		if(is_null($id)) {
			$ids = $this->getAllids();
			$ids[] = 'noid';
			$retarr = array();
			foreach($ids as $id) {
				$keys = $this->getAllKeys($id);
				foreach($keys as $key) {
					$retarr[$id] = $this->getConfig($key, $id);
				}
			}
		} else {
			$out = $this->getAllKeys($id);

			$retarr = array();
			foreach ($out as $k) {
				$retarr[$k] = $this->getConfig($k, $id);
			}
		}


		return $retarr;
	}

	/**
	 * Delete All Keys from module, and drop the table
	 *
	 * Used when uninstalling a module.
	 */
	public function deleteAll() {
		// Our pretend __construct();
		$p = self::checkDatabase($this);

		$tablename = self::getTableName($this);

		// Have we been asked to emulate another module? If so, reset.
		if ($this->classOverride) {
			$this->classOverride = false;
		}

		// Find any blobs and delete them
		try {
			$p['dbGetByType']->execute(array("type" => "blob"));
			$blobs = $p['dbGetByType']->fetchAll();
		} catch (\Exception $e) {
			self::checkException($e);
		}

		foreach ($blobs as $tmparr) {
				$this->deleteBlob($tmparr['val']);
		}

		// And now drop the table.
		$ret = $p['dbEmpty']->execute();

		// We unset so if we're called again in the same session,
		// we will recreate the table.
		unset(self::$checked[$tablename]);
		unset(self::$cache[$p['tablename']]);
		return $ret;
	}

	/**
	 * Returns an array of all keys referenced by $id
	 *
	 * If no $id was provided, return all pairs that weren't set with an $id.
	 * Returns an ordered list from however MySQL orders it (order by `key`)
	 *
	 * @param string $id Optional sub-group ID.
	 * @return array
	 */
	public function getAllKeys($id = "noid") {

		// Our pretend __construct();
		$p = self::checkDatabase($this);

		// Have we been asked to emulate another module? If so, reset.
		if ($this->classOverride) {
			$this->classOverride = false;
		}

		$query[':id'] = $id;

		try {
			$p['dbGetAll']->execute($query);
		} catch (\Exception $e) {
			self::checkException($e);
		}
		$ret = $p['dbGetAll']->fetchAll(\PDO::FETCH_COLUMN, 0);
		return $ret;
	}

	/**
	 * Returns a standard array of all IDs, excluding 'noid'.
	 *
	 * Due to font ambiguity (with LL in lower case and I in upper
	 * case looking identical in some situations) this uses 'ids' in
	 * lower case.
	 *
	 * @return array
	 */
	public function getAllids() {

		// Our pretend __construct();
		$p = self::checkDatabase($this);

		// Have we been asked to emulate another module? If so, reset.
		if ($this->classOverride) {
			$this->classOverride = false;
		}

		$p['dbGetAllIds']->execute();
		$ret = $p['dbGetAllIds']->fetchAll(\PDO::FETCH_COLUMN, 0);
		return $ret;
	}

	/**
	 * Delete all entries that match the ID specified
	 *
	 * This normally is used to remove an item.
	 *
	 * @param string $id Optional sub-group ID.
	 * @return void
	 */
	public function delById($id = null) {

		// Our pretend __construct();
		$p = self::checkDatabase($this);

		if ($id === null) {
			throw new \Exception("Coder error. You can't delete a blank ID");
		}

		// Have we been asked to emulate another module? If so, reset.
		if ($this->classOverride) {
			$this->classOverride = false;
		}

		// Find any blobs and delete them
		try {
			$p['dbGetByType']->execute(array("type" => "blob"));
			$blobs = $p['dbGetByType']->fetchAll();
		} catch (\Exception $e) {
			self::checkException($e);
		}

		foreach ($blobs as $tmparr) {
			if ($tmparr['id'] === $id) {
				$this->deleteBlob($tmparr['val']);
			}
		}

		// Now delete everything else.
		$query[':id'] = $id;

		try {
			$p['dbDelId']->execute($query);
			unset(self::$cache[$p['tablename']][$id]);
		} catch (\Exception $e) {
			self::checkException($e);
		}

		if(isset(self::$cache[$p['tablename']][$id])) {
			unset(self::$cache[$p['tablename']][$id]);
		}
	}

	/**
	 * Return the FIRST ordered entry with this id
	 *
	 * Useful with timestamps?
	 *
	 * @param string $id Required grouping ID.
	 * @return array
	 */
	public function getFirst($id = null) {

		if ($id === null) {
			throw new \Exception("Coder error. getFirst requires an ID");
		}

		$p = self::checkDatabase($this);

		// Have we been asked to emulate another module? If so, reset.
		if ($this->classOverride) {
			$this->classOverride = false;
		}

		$query[':id'] = $id;
		try {
			$p['dbGetFirst']->execute($query);
		} catch (\Exception $e) {
			self::checkException($e);
		}
		$ret = $p['dbGetFirst']->fetchAll(\PDO::FETCH_COLUMN, 0);
		return $ret[0];
	}

	/**
	 * Return the LAST ordered entry with this id
	 *
	 * @param string $id Required grouping ID.
	 * @return array
	 */
	public function getLast($id = null) {

		if ($id === null) {
			throw new \Exception("Coder error. getFirst requires an ID");
		}

		$p = self::checkDatabase($this);

		// Have we been asked to emulate another module? If so, reset.
		if ($this->classOverride) {
			$this->classOverride = false;
		}

		$query[':id'] = $id;
		try {
			$p['dbGetLast']->execute($query);
		} catch (\Exception $e) {
			self::checkException($e);
		}
		$ret = $p['dbGetLast']->fetchAll(\PDO::FETCH_COLUMN, 0);
		return $ret[0];
	}

	/**
	 * Check for exceptions when PDO does things, and try to auto-heal them
	 *
	 */
	public static function checkException($e) {
		if (!is_a($e, "PDOException")) {
			print "checkException wasn't given an Exception. Madness\n";
			exit;
		}

		// I can't seem to see how this is goign to go with i18n. Argh.
		$msg = $e->getMessage();
		if (preg_match('/(134|145)/', $msg)) {
			// Table corrupt. Repair it.
			print _("Database Error detected. Automatic repair started. Please retry");
			$res = self::$db->exec("REPAIR TABLE ".self::$dbname);
			exit(-2);
		} else {
			// Pass it up to the next handler
			throw $e;
		}
	}

	/**
	 * Blob handling - Set
	 *
	 * If the value handed to setConfig is longer than 4kb, then a link
	 * to this table is created instead. A UUID is generated, the value
	 * is inserted, and that uuid is returned
	 *
	 * @param $uuid The uuid used for updating, if not set will generate a uuid
	 * @param $value The contents of the blob
	 * @param $type Hint to decode the blob when handed back
	 * @return $uuid
	 */
	public function insertBlob($uuid = null, $val = false, $type = "raw") {
		if (!$val) {
			throw new \Exception("No val");
		}

		// Generate a UUID
		if(is_null($uuid)) {
			$uuid4 = Uuid::uuid4();
			$uuid = $uuid4->toString();
		}

		// Try to insert our value
		$q = self::$db->prepare('INSERT INTO `kvblobstore` (`uuid`, `type`, `content`) VALUES (:uuid, :type, :data) ON DUPLICATE KEY UPDATE `type` = :type, `content` = :data');
		$query = array("uuid" => $uuid, "data" => $val, "type" => $type);
		try {
			$q->execute($query);
		} catch (\Exception $e) {
			if ($e->getCode() == "42S02") { // Table does not exist, so we need to create it
				$create = "CREATE TABLE `kvblobstore` ( `uuid` CHAR(36) PRIMARY KEY, `type` CHAR(32), `content` LONGBLOB )";
				self::$db->query($create);
				$q->execute($query);
			} else {
				throw $e;
			}
		}
		return $uuid;
	}

	/**
	 * Blob handling - Get
	 *
	 * Return the blob as it was handed to it, with the type
	 * to assist in decoding. If the uuid doesn't exist,
	 * type is set to (bool) false, and content is an empty
	 * string.
	 *
	 * @param $uuid
	 * @return array("content" => $value, "type" => as set)
	 */
	public function getBlob($uuid = false) {
		if (!$uuid) {
			throw new \Exception("No uuid");
		}

		// Try to get our value
		$q = self::$db->prepare('SELECT * FROM `kvblobstore` WHERE `uuid`=:uuid');
		$query = array("uuid" => $uuid);

		try {
			$q->execute($query);
		} catch (\Exception $e) {
			if ($e->getCode() == "42S02") { // Table does not exist? How did that even happen? But create it...
				$create = "CREATE TABLE `kvblobstore` ( `uuid` CHAR(36) PRIMARY KEY, `type` CHAR(32), `content` LONGBLOB )";
				self::$db->query($create);
				return array("content" => "", "type" => false);
			} else {
				throw $e;
			}
		}

		// Did we get anything?
		$res = $q->fetchAll();
		if (!isset($res[0])) {
			// No.
			return array("content" => "", "type" => false);
		}

		return array("content" => $res[0]['content'], "type" => $res[0]['type']);
	}

	/**
	 * Blob handling - Delete
	 *
	 * Deletes the blob, if it exists.
	 *
	 * @param $uuid
	 */
	public function deleteBlob($uuid = false) {
		if (!$uuid) {
			throw new \Exception("No uuid");
		}

		// Try to get our value
		$q = self::$db->prepare('DELETE FROM `kvblobstore` WHERE `uuid`=:uuid');
		$query = array("uuid" => $uuid);

		try {
			$q->execute($query);
		} catch (\Exception $e) {
			if ($e->getCode() == "42S02") { // Table does not exist? How did that even happen? But create it...
				$create = "CREATE TABLE `kvblobstore` ( `uuid` CHAR(36) PRIMARY KEY, `type` CHAR(32), `content` LONGBLOB )";
				self::$db->query($create);
			} else {
				throw $e;
			}
		}
		return true;
	}
}
