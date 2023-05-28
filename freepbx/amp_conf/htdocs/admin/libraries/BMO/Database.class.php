<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * For ease of use, this is a PDO Object. You can call it with standard
 * PDO paramaters, and it will connect as normal.
 *
 * However, if you just want to use it as a random Database thing, then
 * it'll figure out what you want to do and just do it, without you needing
 * to hold its hand.
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
namespace FreePBX;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
class Database extends \PDO {
	private $dsn = null; //pdo dsn
	private $dConn = null; //docterine connection
	private $dVersion = null; //driver version
	/**
	 * Connecting to the Database object
	 * If you pass nothing to this it will assume the default database
	 *
	 * Otherwise you can send it parameters that match PDO parameter settings:
	 * PDO::__construct ( string $dsn [, string $username [, string $password [, array $options ]]] )
	 *
	 * You will then be returned a PDO Database object that you can work with
	 * to manipulate databases outside of FreePBX, a good example of this is with
	 * CDRs where the module has to connect to the external CDR Database
	 */
	public function __construct() {
		$args = func_get_args();

		if (is_object($args[0]) && get_class($args[0]) == "FreePBX") {
			$this->FreePBX = $args[0];
			array_shift($args);
		}

		// This is used for Bootstrapping the Database object.
		//
		// You can NOT USE \FreePBX::Config() here, as THAT depends on *this*.
		if (class_exists("FreePBX")) {
			$amp_conf = \FreePBX::$conf;
		} else if (is_array($args[0]) && !empty($args[0])) {
			$amp_conf = $args[0];
			array_shift($args);
		} else {
			throw new \Exception("FreePBX class does not exist, and no amp_conf found.");
		}

		if (isset($args[1]) && !empty($args[0]) && is_string($args[0])) {
			$username = $args[1];
		} else {
			$username = $amp_conf['AMPDBUSER'];
		}

		if (isset($args[2]) && !empty($args[0]) && is_string($args[0])) {
			$password = $args[2];
		} else {
			$password = $amp_conf['AMPDBPASS'];
		}

		// If the first param still exists, it should be a DSN.
		if (isset($args[0]) && !empty($args[0]) && is_string($args[0])) {
			$dsnarr = $this->dsnToArray($args[0]);
		} else {
			$dsnarr = array();
		}

		// Now go through and put anything in place that was missing
		if (!isset($dsnarr['host'])) {
			$dsnarr['host'] = isset($amp_conf['AMPDBHOST'])?$amp_conf['AMPDBHOST']:'localhost';
		}

		if (!isset($dsnarr['dbname'])) {
			$dsnarr['dbname'] = isset($amp_conf['AMPDBNAME'])?$amp_conf['AMPDBNAME']:'asterisk';
		}

		// Note the inverse logic. We REMOVE engine from dsnarr if it exists, because that
		// isn't technically part of the DSN.
		if (isset($dsnarr['engine'])) {
			$engine = $dsnarr['engine'];
			unset ($dsnarr['engine']);
		} else {
			$engine = isset($amp_conf['AMPDBENGINE'])?$amp_conf['AMPDBENGINE']:'mysql';
		}

		$engine = ($engine == 'mariadb') ? 'mysql' : $engine;

		// We only want to add port to the DSN if it's actually defined.
		if (isset($amp_conf['AMPDBPORT'])) {
			// Make sure this is an int
			$port = (int) $amp_conf['AMPDBPORT'];
			if ($port > 1024) {
				$dsnarr['port'] = $port;
			}
		}

		// If there's a socket defined, we don't want host or port defined
		if (isset($amp_conf['AMPDBSOCK'])) {
			unset($dsnarr['host']);
			unset($dsnarr['port']);
			$dsnarr['unix_socket'] = $amp_conf['AMPDBSOCK'];
		}

		// Always utf8.
		$charset = "utf8";
		if ($engine == 'mysql') {
			//we cant learn the server version BEFORE we connect. So we have to figure it out now
			$output = exec('mysql --version 2>/dev/null');
			if(preg_match('/Distrib\s*(\d+\.\d+\.\d+)/i',$output,$matches) && version_compare($matches[1],"5.5.3","ge")) {
				$charset = 'utf8mb4';
			}
		}

		$dsnarr['charset'] = isset($dsnarr['charset']) ? $dsnarr['charset'] : $charset;

		// Were there any database options?
		$options = isset($args[3]) ? $args[3] : array();
		$dsnarr['driverOptions'] = $options;

		//this id only for PDO
		if ($engine == 'mysql') {
			$options[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES '.$charset;
		}

		// This DSN array is now suitable for building into a valid DSN!
		$this->dsn = "$engine:";
		foreach ($dsnarr as $k=>$v) {
			if (is_string($v)) {
				$this->dsn .= "$k=$v;";
			}
		}

		if(!empty($port)){
			$this->dsn .= "port=$port;";
		}
		
		try {
			if ($options) {
				parent::__construct($this->dsn, $username, $password, $options);
			} else {
				parent::__construct($this->dsn, $username, $password);
			}
		} catch(\Exception $e) {
			$_SESSION['force_remove_stack_trace'] = true;
			die_freepbx($e->getMessage(), $e);
		}
		if(defined('LOGQUERIES')) {
			$this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, array('FreePBX\Database\PDOStatement', array($this)));
		}
		$this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->dVersion = $this->getAttribute(\PDO::ATTR_SERVER_VERSION);
	}

	/**
	 * Fetch the mysql command to use with required parameters
	 * @param string cmdName = mysql or mysqldump
	 */
	public function fetchSqlCommand($mysqlCmd='mysql') {
		global $amp_conf;
		$host = $amp_conf['AMPDBHOST'];
		$port = isset($amp_conf['AMPDBPORT'])?$amp_conf['AMPDBPORT']:'';
		$dbuser = $this->FreePBX->Config->get('AMPDBUSER') ? $this->FreePBX->Config->get('AMPDBUSER'):$amp_conf['AMPDBUSER'];
		$dbpass = $this->FreePBX->Config->get('AMPDBPASS') ? $this->FreePBX->Config->get('AMPDBPASS'):$amp_conf['AMPDBPASS'];
		$dbname = $this->FreePBX->Config->get('AMPDBNAME') ? $this->FreePBX->Config->get('AMPDBNAME') : 'asterisk';

		if ($host =='localhost' || $host == '127.0.0.1') {
			$hostname = '';
		} else {
			$hostname = '-h '.$host;
		}
		if ($port =='') {
			$portnum = '';
		}else {
			$portnum = '-P '.$port;
		}
		$sqlCommand = "{$mysqlCmd} {$portnum} {$hostname} -u{$dbuser} -p{$dbpass} {$dbname} ";

		return $sqlCommand;
	}

	public function migrateMultipleXML(\SimpleXMLElement $XMLtables, $dryrun = false) {
		$tables = array();
		foreach($XMLtables as $table) {
			$tname = (string)$table->attributes()->name;
			$cols = array();
			$indexes = array();
			foreach($table->field as $field) {
				$name = (string)$field->attributes()->name;
				$cols[$name] = array();
				foreach($field->attributes() as $key => $value) {
					if($key == "name") {
						continue;
					}
					$key = strtolower($key);
					switch ($key) {
						case 'notnull':
						case 'primarykey':
						case 'autoincrement':
						case 'unique':
						case 'fixed':
							$cols[$name][$key] = ($value === true || "true" === strtolower($value));
						break;
						default:
							$cols[$name][$key] = (string)$value;
						break;
					}
				}
			}
			if(!empty($table->key)) {
				foreach($table->key as $field) {
					$name = (string)$field->attributes()->name;
					$indexes[$name] = array();
					foreach($field->attributes() as $key => $value) {
						if($key == "name") {
							continue;
						}
						$indexes[$name][$key] = (string)$value;
					}
					$indexes[$name]['cols'] = array();
					foreach($field->column as $col) {
						$indexes[$name]['cols'][] = (string)$col->attributes()->name;
					}
				}
			}
			$tables[$tname] = array(
				'columns' => $cols,
				'indexes' => $indexes
			);
		}

		$migrate = new Database\Migration($this->getDoctrineConnection(), $this->dVersion);
		return $migrate->modifyMultiple($tables,$dryrun);
	}

	public function query() {
		$args = func_get_args();
		if(defined('LOGPREPARES')) {
			$logger = \FreePBX::Logger()->createLogDriver('query_performance', \FreePBX::Config()->get('ASTLOGDIR').'/query_performance.log', \Monolog\Logger::DEBUG);
			$logger = $logger->withName(posix_getpid());
			$logger->debug($args[0]);
		}
		return call_user_func_array('parent::query',$args);
	}

	public function migrate($table) {
		$migrate = new Database\Migration($this->getDoctrineConnection(), $this->dVersion);
		$migrate->setTable($table);
		return $migrate;
	}

	public function getDoctrineConnection() {
		if(empty($this->dConn)) {
			$this->dConn = DriverManager::getConnection(array("pdo" => $this));
		}
		return $this->dConn;
	}

	/**
	 * COMPAT: Queries Database using PDO
	 *
	 * This is a FreePBX Compatibility hook for the global 'sql' function that
	 * previously used PEAR::DB
	 *
	 * @param $sql string SQL String to run
	 * @param $type string Type of query
	 * @param $fetchmode int One of the PDO::FETCH_ methos (see http://www.php.net/manual/en/pdo.constants.php for info)
	 */
	public function sql($sql = null, $type = "query", $fetchmode = \PDO::FETCH_BOTH) {
		if (!$sql)
			throw new \Exception("No SQL Given to Database->sql()");

		switch ($type) {
		case "query":
			// Note that the basic PDO::query doesn't fetch. So no need for $fetchmode
			$res = $this->sql_query($sql);
			break;
		case "getAll":
			// Return the complete result set
			$res = $this->sql_getAll($sql, $fetchmode);
			break;
		case "getOne":
			// Return the first item of the first row
			$res = $this->sql_getOne($sql);
			break;
		case "getRow":
			// Return the first the first row
			$res = $this->sql_getRow($sql, $fetchmode);
			break;
		default:
			throw new \Exception("Unknown SQL query type of $type");
		}

		return $res;
	}

	/**
	 * Returns a PDOStatement object
	 *
	 * This is for compatibility with older code. I expect this will never be used,
	 * as PDO has much smarter ways of doing things.
	 *
	 * @param $sql string SQL String
	 * @return object PDOStatement object
	 */
	private function sql_query($sql) {
		return $this->query($sql);
	}

	/**
	 * Performs a SQL Query, and returns all results
	 *
	 * This should always return the exact same result as PEAR's $db->getAll query.
	 *
	 * @param $sql string SQL String
	 * @param $fetchmode int PDO::FETCH_* Method
	 * @return array|object Result of the SQL Query
	 */
	private function sql_getAll($sql, $fetchmode) {
		$res = $this->query($sql);
		return $res->fetchAll($fetchmode);
	}

	private function sql_getRow($sql, $fetchmode) {
		$res = $this->query($sql);
		return $res->fetch($fetchmode);
	}

	/**
	 * Perform a SQL Query, and return the first item of the first row.
	 *
	 * @param $sql string SQL String
	 * @return string
	 */

	private function sql_getOne($sql) {
		$res = $this->query($sql);
		$line = $res->fetchColumn();
		return !empty($line) ? $line : false;
	}

	/**
	 * COMPAT: getMessage - returns an error message
	 *
	 * This will throw an exception, as it shouldn't be used and is a holdover from the PEAR $db object.
	 */
	public function getMessage() {
		// There is a PDO call for this.. I think.
		throw new \Exception("getMessage was called on the DB Object");
	}

	/**
	 * COMPAT: isError - checks if the last query was successfull.
	 *
	 * This will throw an exception, as it shouldn't be used and is a holdover from the PEAR $db object.
	 */
	public function isError($result) {
		// Should check that the $result is an object, and it's a PDOStatement object, I think.
		throw new \Exception("isError was called on the DB Object");
	}

	/**
	 * COMPAT: escapeSimple - Wraps the supplied string in quotes.
	 *
	 * This wraps the requested string in quotes, and returns it. It's a bad idea. You should be using
	 * prepared queries for this. At some point this will be deprecated and removed.
	 */
	public function escapeSimple($str = null) {
		// Using PDO::quote
		return $this->quote($str);
	}

	/**
	 * HELPER: getOne - Returns first result
	 *
	 * Returns the first result of the first row of the query. Handy shortcut when you're doing
	 * a query that only needs one item returned.
	 */
	public function getOne($sql = null) {
		if ($sql === null)
			throw new \Exception("No SQL given to getOne");

		return $this->sql_getOne($sql);
	}

	/**
	 * Parses DSN string in to an array
	 * @param  string $dsn a formatted DSN string.
	 * @return array  Returns an array containing the parsed DSN
	 */
	public function dsnToArray($dsn) {

		$tmparr = explode(':', $dsn);

		if (!isset($tmparr[1])) {
			throw new \Exception("Unable to parse DSN string '$dsn'");
		}

		$retarr = array('engine' => $tmparr[0]);
		$sections = explode(';', $tmparr[1]);
		foreach ($sections as $setting) {
			$tmparr = explode('=',$setting);
			if(count($tmparr) === 2) {
				$retarr[$tmparr[0]] = $tmparr[1];
			} else {
				throw new \Exception("Section '$setting' can not be parsed");
			}
		}
		return $retarr;
	}
}
