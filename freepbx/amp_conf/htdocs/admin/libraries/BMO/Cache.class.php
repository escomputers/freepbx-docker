<?php
namespace FreePBX;
/**
 * This is part of the FreePBX Big Module Object.
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2018 Sangoma Technologies
 */

/**
 * This controls the caching parts of FreePBX.
 * It's a direct implementation of Doctrine Cache.
 * It's implemented this way so that in the future FreePBX can utilize
 * other means of caching
 */

use Doctrine\Common\Cache\ChainCache;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\PhpFileCache;
use Doctrine\Common\Cache\RedisCache;

class Cache {
	private $cache;
	private $freepbx;
	private $namespaceClones = array();
	private $cloned = false;

	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new Exception("Not given a FreePBX Object");
		}
		$this->freepbx = $freepbx;
	}

	public function __clone() {
		$this->cloned = true;
	}

	/**
	 * Fetches an entry from the cache.
	 *
	 * @param string $id The id of the cache entry to fetch.
	 *
	 * @return mixed The cached data or FALSE, if no cache entry exists for the given id.
	 */
	public function fetch($id) {
		return $this->init()->fetch($id);
	}

	/**
	 * Tests if an entry exists in the cache.
	 *
	 * @param string $id The cache id of the entry to check for.
	 *
	 * @return bool TRUE if a cache entry exists for the given cache id, FALSE otherwise.
	 */
	public function contains($id) {
		return $this->init()->contains($id);
	}

	/**
	 * Puts data into the cache.
	 *
	 * If a cache entry with the given id already exists, its data will be replaced.
	 *
	 * @param string $id       The cache id.
	 * @param mixed  $data     The cache entry/data.
	 * @param int    $lifeTime The lifetime in number of seconds for this cache entry.
	 *                         If zero (the default), the entry never expires (although it may be deleted from the cache
	 *                         to make place for other entries).
	 *
	 * @return bool TRUE if the entry was successfully stored in the cache, FALSE otherwise.
	 */
	public function save($id, $data, $lifeTime = 300){
		return $this->init()->save($id, $data, $lifeTime);
	}

	/**
	 * Deletes a cache entry.
	 *
	 * @param string $id The cache id.
	 *
	 * @return bool TRUE if the cache entry was successfully deleted, FALSE otherwise.
	 *              Deleting a non-existing entry is considered successful.
	 */
	public function delete($id) {
		return $this->init()->delete($id);
	}

	/**
	 * Retrieves cached information from the data store.
	 *
	 * The server's statistics array has the following values:
	 *
	 * - <b>hits</b>
	 * Number of keys that have been requested and found present.
	 *
	 * - <b>misses</b>
	 * Number of items that have been requested and not found.
	 *
	 * - <b>uptime</b>
	 * Time that the server is running.
	 *
	 * - <b>memory_usage</b>
	 * Memory used by this server to store items.
	 *
	 * - <b>memory_available</b>
	 * Memory allowed to use for storage.
	 *
	 * @return array|null An associative array with server's statistics if available, NULL otherwise.
	 */
	public function getStats() {
		return $this->init()->getStats();
	}

	/**
	 * Returns an associative array of values for keys is found in cache.
	 *
	 * @param string[] $keys Array of keys to retrieve from cache
	 *
	 * @return mixed[] Array of retrieved values, indexed by the specified keys.
	 *                 Values that couldn't be retrieved are not contained in this array.
	 */
	public function fetchMultiple(array $keys) {
		return $this->init()->fetchMultiple($keys);
	}

	/**
	 * Returns a boolean value indicating if the operation succeeded.
	 *
	 * @param array $keysAndValues Array of keys and values to save in cache
	 * @param int   $lifetime      The lifetime. If != 0, sets a specific lifetime for these
	 *                             cache entries (0 => infinite lifeTime).
	 *
	 * @return bool TRUE if the operation was successful, FALSE if it wasn't.
	 */
	public function saveMultiple(array $keysAndValues, $lifetime = 0) {
		return $this->init()->saveMultiple($keysAndValues, $lifetime);
	}

	/**
	 * Deletes several cache entries.
	 *
	 * @param string[] $keys Array of keys to delete from cache
	 *
	 * @return bool TRUE if the operation was successful, FALSE if it wasn't.
	 */
	public function deleteMultiple(array $keys) {
		return $this->init()->deleteMultiple($keys);
	}

	/**
	 * Set Namespace and clone this class
	 * This prevents issues where the namespace is not properly reset
	 * @method cloneByNamespace
	 * @param  [type]            $namespace [description]
	 */
	public function cloneByNamespace($namespace, $persistent = true) {
		if($this->cloned) {
			throw new \Exception("This is already a clone. Please dont clone the clone. Clone the original");
		}
		if(isset($this->namespaceClones[$namespace])) {
			return $this->namespaceClones[$namespace];
		}
		$self = clone $this;
		$self->init(true, $persistent)->setNamespace($namespace);
		$this->namespaceClones[$namespace] = $self;
		return $this->namespaceClones[$namespace];
	}

	/**
	 * Sets the namespace to prefix all cache ids with.
	 *
	 * @param string $namespace
	 *
	 * @return void
	 */
	public function setNamespace($namespace) {
		return $this->init()->setNamespace($namespace);
	}

	/**
	 * Retrieves the namespace that prefixes all cache ids.
	 *
	 * @return string
	 */
	public function getNamespace() {
		return $this->init()->getNamespace();
	}

	/**
	 * Flushes all cache entries, globally.
	 *
	 * @return bool TRUE if the cache entries were successfully flushed, FALSE otherwise.
	 */
	public function flushAll() {
		return $this->init()->flushAll();
	}

	/**
	 *  Deletes all cache entries in the current cache namespace.
	 *
	 * @return bool TRUE if the cache entries were successfully deleted, FALSE otherwise.
	 */
	public function deleteAll() {
		return $this->init()->deleteAll();
	}

	/**
	 * Initalize the cache driver
	 * @method init
	 * @return object The cache driver
	 */
	private function init($force = false, $persistent = true) {
		if($force || !isset($this->cache)) {

			$chain = array(
				new ArrayCache()
			);
			if($persistent) {
				if(class_exists('Redis')) {
					try {
						$redis = new \Redis();
						$redis->connect('127.0.0.1');
						$redis->get('foo');
						$redisCache = new RedisCache();
						$redisCache->setRedis($redis);
						$chain[] = $redisCache;
					} catch(\Exception $e) {
						freepbx_log(FPBX_LOG_WARNING, "Redis enabled but not running, falling back to filesystem [{$e->getMessage()}]. Either fix Redis or remove the php redis extension");
					}
				}
				if(count($chain) === 1 && is_object($this->freepbx->Config) && is_a($this->freepbx->Config,'FreePBX\Config')) {
					$cachePath = $this->freepbx->Config->get('ASTSPOOLDIR')."/cache";
					if(!file_exists($cachePath)) {
						mkdir($cachePath,0777,true);
						$user = $this->freepbx->Config->get('AMPASTERISKWEBUSER');
						chown($cachePath,$user);
						$group = $this->freepbx->Config->get('AMPASTERISKWEBGROUP');
						chgrp($cachePath,$group);

					}
					if(is_writable($cachePath)) {
						$chain[] = new PhpFileCache($cachePath);
					} else {
						$this->freepbx->Notifications->add_error('framework', 'CACHEPATCH', _('Cache path is not writable!'), sprintf(_("The cache path of %s is not writable, caching is not enabled as a result the system might be slower. Please fix this by running 'fwconsole chown' from the CLI."),$cachePath), "", true, true);
					}
				}
			}
			$this->cache = new ChainCache($chain);
		}
		return $this->cache;
	}
}
