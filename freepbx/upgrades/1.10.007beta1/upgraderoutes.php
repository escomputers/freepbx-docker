<?php

/* check for old prefix based routing */
outn("Upgrading Dial Prefix to Outbound Routes..");

// see if they're still using the old dialprefix method
$sql = "SELECT variable,value FROM globals WHERE variable LIKE 'DIAL\\\_OUT\\\_%'";
// we SUBSTRING() to remove "outrt-"
$results = $db->getAll($sql);
if(DB::IsError($results)) {
	die($results->getMessage());
}

outn(count($results)." to update..");

if (count($results) > 0) {
	// yes, they are using old method, let's update
	
	// get the default trunk
	$sql = "SELECT value FROM globals WHERE variable = 'OUT'";
	$results_def = $db->getAll($sql);
	if(DB::IsError($results_def)) {
		die($results_def->getMessage());
	}
	
	if (preg_match("/{OUT_(\d+)}/", $results_def[0][0], $matches)) {
		$def_trunk = $matches[1];
	} else {
		$def_trunk = "";
	}
	
	$default_patterns = array(	// default patterns that used to be in extensions.conf
				".",
/*
				"NXXXXXX",
				"NXXNXXXXXX",
				"1800NXXXXXX",
				"1888NXXXXXX",
				"1877NXXXXXX",
				"1866NXXXXXX",
				"1NXXNXXXXXX",
				"011.",
				"911",
				"411",
				"311",
*/
				);

	$default_patterns2 = array(	// default patterns that used to be in extensions.conf
				"NXXXXXX",
				"NXXNXXXXXX",
				"1800NXXXXXX",
				"1888NXXXXXX",
				"1877NXXXXXX",
				"1866NXXXXXX",
				"1NXXNXXXXXX",
				"011.",
				"911",
				"411",
				"311",
				);
	
	foreach ($results as $temp) {
		// temp[0] is "DIAL_OUT_1"
		// temp[1] is the dial prefix
		
		$trunknum = substr($temp[0],9);
		
		$name = "route".$trunknum;
		
		$trunks = array(1=>"OUT_".$trunknum); // only one trunk to use
		
		$patterns = array();
		foreach ($default_patterns as $pattern) {
			$patterns[] = $temp[1]."|".$pattern;
		}
		
		if ($trunknum == $def_trunk) {
			// this is the default trunk, add the patterns with no prefix
			$patterns = array_merge($patterns, $default_patterns2);
		}
		
		// add this as a new route
		addroute($name, $patterns, $trunks);
	}
	
	
	// delete old values
	$sql = "DELETE FROM globals WHERE (variable LIKE 'DIAL\\\_OUT\\\_%') OR (variable = 'OUT') ";
	debug($sql);
	$result = $db->query($sql);
	if(DB::IsError($result)) {
		die($result->getMessage());
	}
	
}

out("OK");

outn("Upgrading Routes Names..");
$reason=FixTables();
$reason=FixRoutes();
out("OK");

function addroute($name, $patterns, $trunks) {
	global $db;

	$trunktech=array();

	//Retrieve each trunk tech for later lookup
	$sql="select * from globals WHERE variable LIKE 'OUT\\_%'";
        $result = $db->getAll($sql);
        if(DB::IsError($result)) {
		die($result->getMessage());
	}
	foreach($result as $tr) {
		$tech = strtok($tr[1], "/");
		$trunktech[$tr[0]]=$tech;
	}
	
	$trunks = array_values($trunks); // probably already done, but it's important for our dialplan
	
	foreach ($patterns as $pattern) {
		
		if (false !== ($pos = strpos($pattern,"|"))) {
			// we have a | meaning to not pass the digits on
			// (ie, 9|NXXXXXX should use the pattern _9NXXXXXX but only pass NXXXXXX, not the leading 9)
			
			$pattern = str_replace("|","",$pattern); // remove all |'s
			$exten = "EXTEN:".$pos; // chop off leading digit
		} else {
			// we pass the full dialed number as-is
			$exten = "EXTEN"; 
		}
		
		if (!preg_match("/^[0-9*]+$/",$pattern)) { 
			// note # is not here, as asterisk doesn't recoginize it as a normal digit, thus it requires _ pattern matching
			
			// it's not strictly digits, so it must have patterns, so prepend a _
			$pattern = "_".$pattern;
		}
		
		foreach ($trunks as $priority => $trunk) {
			$priority += 1; // since arrays are 0-based, but we want priorities to start at 1
			
			$sql = "INSERT INTO extensions (context, extension, priority, application, args) VALUES ";
			$sql .= "('outrt-".$name."', ";
			$sql .= "'".$pattern."', ";
			$sql .= "'".$priority."', ";
			$sql .= "'Macro', ";
			if ($trunktech[$trunk] == "ENUM")
				$sql .= "'dialout-enum,".substr($trunk,4).",\${".$exten."}'"; // cut off OUT_ from $trunk
			else
				$sql .= "'dialout-trunk,".substr($trunk,4).",\${".$exten."}'"; // cut off OUT_ from $trunk
			$sql .= ")";
			
			debug($sql);
			$result = $db->query($sql);
			if(DB::IsError($result)) {
				die($result->getMessage());
			}
		}
		
		$priority += 1;
		$sql = "INSERT INTO extensions (context, extension, priority, application, args, descr) VALUES ";
		$sql .= "('outrt-".$name."', ";
		$sql .= "'".$pattern."', ";
		$sql .= "'".$priority."', ";
		$sql .= "'Macro', ";
		$sql .= "'outisbusy', ";
		$sql .= "'No available circuits')";
		
		debug($sql);
		$result = $db->query($sql);
		if(DB::IsError($result)) {
			die($result->getMessage());
		}
	}

	
	// add an include=>outrt-$name  to [outbound-allroutes]:
	
	// we have to find the first available priority.. priority doesn't really matter for the include, but
	// there is a unique index on (context,extension,priority) so if we don't do this we can't put more than
	// one route in the outbound-allroutes context.
	$sql = "SELECT priority FROM extensions WHERE context = 'outbound-allroutes' AND extension = 'include'";
	$results = $db->getAll($sql);
	if(DB::IsError($results)) {
		die($results->getMessage());
	}
	$priorities = array();
	foreach ($results as $row) {
		$priorities[] = $row[0];
	}
	for ($priority = 1; in_array($priority, $priorities); $priority++);
	
	// $priority should now be the lowest available number
	
	$sql = "INSERT INTO extensions (context, extension, priority, application, args, descr, flags) VALUES ";
	$sql .= "('outbound-allroutes', ";
	$sql .= "'include', ";
	$sql .= "'".$priority."', ";
	$sql .= "'outrt-".$name."', ";
	$sql .= "'', ";
	$sql .= "'', ";
	$sql .= "'2')";
	
	debug($sql);
	$result = $db->query($sql);
	if(DB::IsError($result)) {
		die($priority.$result->getMessage());
	}
	
}

function FixTables() {
        global $db;

      	$sql = "ALTER TABLE extensions modify context varchar(45) NOT NULL default 'default'";
      	$result = $db->query($sql);
	if(DB::IsError($result)) {
		die($result->getMessage());
      	}
      	$sql = "ALTER TABLE extensions modify application varchar(45) NOT NULL default ''";
      	$result = $db->query($sql);
	if(DB::IsError($result)) {
		die($result->getMessage());
      	}
        return true;
}
function FixRoutes() {
        global $db;

	$sql = "SELECT DISTINCT SUBSTRING(context,7) FROM extensions WHERE context LIKE 'outrt-001-%' ORDER BY context ";
        $routes = $db->getAll($sql);
        if (count($routes) > 0) {
                // the route is already in the correct format therefore we need not rename them
		return true;
	}

        $sql = "DELETE from  extensions WHERE context='outbound-allroutes'";
        $result = $db->query($sql);
        if(DB::IsError($result)) {
        	die($result->getMessage().$sql);
        }
        $sql = "SELECT DISTINCT SUBSTRING(context,7) FROM extensions WHERE context LIKE 'outrt-%' ORDER BY context ";
        $routes = $db->getAll($sql);
        if (count($routes) > 0) {
                // there's a route therefore we need to rename them
		$key=1;
		foreach ($routes as $route) {
			$prefix=setroutepriorities($key);
      			$sql = "UPDATE extensions SET context = 'outrt-".$prefix."-".$route[0]."' WHERE context = 'outrt-".$route[0]."'";
      			$result = $db->query($sql);
			if(DB::IsError($result)) {
				die($result->getMessage());
      			}

			// Delete and readd the outbound-allroutes entries
	                $sql = "INSERT INTO extensions (context, extension, priority, application, args, descr, flags) VALUES ";
	                $sql .= "('outbound-allroutes', ";
        	        $sql .= "'include', ";
	                $sql .= "'".$key."', ";
        	        $sql .= "'outrt-".$prefix."-".$route[0]."', ";
	                $sql .= "'', ";
	                $sql .= "'', ";
	                $sql .= "'2')";

	                $result = $db->query($sql);
	                if(DB::IsError($result)) {
	                        die($result->getMessage(). $sql);
	                }
			$key++;
	        }
        }
	else
		return false;

        return true;
}
function setroutepriorities($key)
{
        if ($key<10)
                $prefix = sprintf("00%d",$key);
        else if ((9<$key)&&($key<100))
                $prefix = sprintf("0%d",$key);
        else if ($key>100)
                $prefix = sprintf("%d",$key);
        return ($prefix);
}

?>
