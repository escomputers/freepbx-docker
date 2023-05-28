<?php
/* queries database using PEAR.
*  $type can be query, getAll, getRow, getCol, getOne, getAssoc, etc
*  $fetchmode can be DB_FETCHMODE_ORDERED, DB_FETCHMODE_ASSOC, DB_FETCHMODE_OBJECT
*  returns array, unless using getOne
*/
function sql($sql,$type="query",$fetchmode='default') {
	global $db;
	if(empty($db) || !is_object($db) || get_class($db) != "DB") {
		$dbh = FreePBX::Create()->Database();
		$db = new DB($dbh);
	}
	switch($fetchmode) {
		case 'DB_FETCHMODE_ASSOC': //You guessed it, looking for string constants!
		case DB_FETCHMODE_ASSOC:
			//simulate craptacular function from previous versions
			//that didn't even work correctly! sweet :-/
			//(The integer shouldnt go there)
			if($type == 'getAssoc') {
				$results = $db->$type($sql,true);
			} else {
				$results = $db->$type($sql,array(),DB_FETCHMODE_ASSOC);
			}
		break;
		case DB_FETCHMODE_ORDERED:
			$results = $db->$type($sql,array(),DB_FETCHMODE_ORDERED);
		break;
		case 'default':
			$results = $db->sql($sql, $type);
		break;
		default:
			throw new Exception("Unknown SQL fetchmode of $fetchmode");
		break;
	}
	return $results;
}

/**  Format input so it can be safely used as a literal in a query.
 * Literals are values such as strings or numbers which get utilized in places
 * like WHERE, SET and VALUES clauses of SQL statements.
 * The format returned depends on the PHP data type of input and the database
 * type being used. This simply calls PEAR's DB::smartQuote() function
 * @param  mixed  The value to go into the database
 * @return string  A value that can be safely inserted into an SQL query
 */
function q($value) {
	global $db;
	return $db->quoteSmart($value);
}

// sql text formatting -- couldn't see that one was available already
function sql_formattext($txt) {
	global $db;
	if (isset($txt)) {
		$fmt = $db->escapeSimple($txt);
		$fmt = "'" . $fmt . "'";
	} else {
		$fmt = 'null';
	}

	return $fmt;
}


function execSQL( $file ) {
	global $db;
	$data = null;

	// run sql script
	$fd = fopen( $file ,"r" );

	while (!feof($fd)) {
		$data .= fread($fd, 1024);
	}
	fclose($fd);

	preg_match_all("/((SELECT|INSERT|UPDATE|DELETE|CREATE|DROP).*);\s*\n/Us", $data, $matches);
	foreach ($matches[1] as $sql) {
		$result = $db->query($sql);
		if(DB::IsError($result)) { return false; }
	}
  return true;
}

/**
 * test if a pear::db object is an error
 *
 * ====================================
 * THIS FUNCTION IS EXPERIMENTAL
 * ====================================
 *
 * @pram object - the results of a query
 * @pram mixed function or an array($object, 'method') to call on error
 * @pram int - desired debug level
 * @pram array an array of variables to pass to the action function
 *
 * @returns true if the object is an error and false if the query was successful
 *
 * @example $q = $db->getOne('select foo from bar'); db_e($q);
 * @example $q = $db->getOne('select foo from bar');
 * if (db_e($q, '')) {
 *	//do error handling here
 * }
 */
function db_e($obj, $action = 'die_freepbx', $debug_level = 4, $args = '') {
	global $db;
	if ($db->isError($obj)) {
		if ($action) {
			switch ($debug_level) {
				case 0:
					$db_dbug = $args;
					break;
				case 1:
					$db_dbug = $obj->getMessage();
					break;
				case 2:
					$db_dbug = $obj->getCode();
					break;
				case 3:
					$db_dbug = $obj->getUserInfo();
					break;
				case 4:
					$db_dbug = $obj->getDebugInfo();
					break;
			}

			if (is_array($args)) {
				$args['error'] = $db_dbug;
				if (is_array($action)) {
					call_user_func_array($action[0]->$action[1], $args);
				} else {
					call_user_func_array($action, $args);
				}
			} else {
				if (is_array($action)) {
					call_user_func($action[0]->$action[1], $obj->$db_dbug());
				} else {
					call_user_func($action, $db_dbug);
				}

			}
		}
		return true;
	} else {
		return false;
	}
}
?>
