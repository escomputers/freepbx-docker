<?php
global $amp_conf;
global $db;

/*  fix manager.conf settings for older manager.conf files being upgraded as new permissions are needed for later releases of Asterisk
 *  in english, this is limited to everything between the AMPMGRUSER section and any new section the user may have edited. It replaces
 *  everything to the right of a 'read =' or 'write =' permission line with the full set of permissoins Asterisk offers.
 */
exec('sed -i.2.8.0.bak "/^\['.$amp_conf['AMPMGRUSER'].'\]/,/^\[.*\]/s/^\(\s*read\s*=\|\s*write\s*=\).*/\1 system,call,log,verbose,command,agent,user,config,command,dtmf,reporting,cdr,dialplan,originate/" '.$amp_conf['ASTETCDIR'].'/manager.conf',$outarr,$ret);

$autoincrement = (($amp_conf["AMPDBENGINE"] == "sqlite") || ($amp_conf["AMPDBENGINE"] == "sqlite3")) ? "AUTOINCREMENT":"AUTO_INCREMENT";

$outbound_routes = "
CREATE TABLE outbound_routes (
	`route_id` INTEGER NOT NULL PRIMARY KEY $autoincrement,
	`name` VARCHAR( 40 ),
	`outcid` VARCHAR( 40 ),
	`outcid_mode` VARCHAR( 20 ),
	`password` VARCHAR( 30 ),
	`emergency_route` VARCHAR( 4 ),
	`intracompany_route` VARCHAR( 4 ),
	`mohclass` VARCHAR( 80 ),
	`time_group_id` INTEGER DEFAULT NULL
)
";

$outbound_route_patterns = "
CREATE TABLE outbound_route_patterns (
	`route_id` INTEGER NOT NULL,
	`match_pattern_prefix` VARCHAR( 60 ),
	`match_pattern_pass` VARCHAR( 60 ),
	`match_cid` VARCHAR( 60 ),
	`prepend_digits` VARCHAR( 100 ),
  PRIMARY KEY (`route_id`, `match_pattern_prefix`, `match_pattern_pass`,`match_cid`,`prepend_digits`)
)
";

$outbound_route_trunks = "
CREATE TABLE outbound_route_trunks (
	`route_id` INTEGER NOT NULL,
	`trunk_id` INTEGER NOT NULL,
	`seq` INTEGER NOT NULL,
  PRIMARY KEY  (`route_id`, `trunk_id`, `seq`) 
)
";

$outbound_route_sequence = "
CREATE TABLE outbound_route_sequence (
	`route_id` INTEGER NOT NULL,
	`seq` INTEGER NOT NULL,
  PRIMARY KEY  (`route_id`, `seq`) 
)
";

outn("Create new outbound_routes table.. ");
$result = $db->query($outbound_routes);
if (DB::IsError($result) && $result->getCode() == DB_ERROR_ALREADY_EXISTS ) {
  out("Table exists, skipping migration");
} elseif (DB::IsError($result)) {
  out("failed, FATAL Error");
	out($result->getMessage());
} else {
  out("ok");
  outn("create outbound_route_patterns.. ");
  $result = $db->query($outbound_route_patterns);
  if (DB::IsError($result) && $result->getCode() != DB_ERROR_ALREADY_EXISTS ) {
    out("failed, FATAL Error");
	  out($result->getDebugInfo());
  } else {
    out("ok");
    outn("create outbound_route_trunks.. ");
    $result = $db->query($outbound_route_trunks);
    if (DB::IsError($result) && $result->getCode() != DB_ERROR_ALREADY_EXISTS ) {
      out("failed, FATAL Error");
	    out($result->getDebugInfo());
    } else {
      out("ok");
      outn("create outbound_route_sequence.. ");
      $result = $db->query($outbound_route_sequence);
      if (DB::IsError($result) && $result->getCode() != DB_ERROR_ALREADY_EXISTS ) {
        out("failed, FATAL Error");
	      out($result->getDebugInfo());
      } else {
        out("ok");

        $routepriority = __core_routing_getroutenames();

        $routes = array();
        $accum = array();
        $dialpattern = array();
        $turnkpriority = array();
        foreach ($routepriority as $route) {
		      $extdisplay = $route[0];
		      $accum[] = substr($extdisplay,4);
  
		      $routecid_array = __core_routing_getroutecid($extdisplay);
          $accum[] = $routecid_array['routecid'];
          $accum[] = $routecid_array['routecid_mode'];

		      $accum[] = __core_routing_getroutepassword($extdisplay);
		      $accum[] = __core_routing_getrouteemergency($extdisplay);
		      $accum[] = __core_routing_getrouteintracompany($extdisplay);
		      $accum[] = __core_routing_getroutemohsilence($extdisplay);

		      $dialpattern[$extdisplay] = __core_routing_getroutepatterns($extdisplay);
		      $trunkpriority[$extdisplay] = __core_routing_getroutetrunks($extdisplay);

          $routes[$extdisplay] = $accum;
          unset($accum);
        }

        $compiled = $db->prepare('INSERT INTO `outbound_routes` (`name`, `outcid`, `outcid_mode`, `password`, `emergency_route`, `intracompany_route`, `mohclass`) values (?,?,?,?,?,?,?)');
	      $result = $db->executeMultiple($compiled,$routes);
	      if(DB::IsError($result)) {
		      out("FATAL: ".$result->getDebugInfo()."\n".'error inserting into outbound_routes table');	
        }
        $route_ids = $db->getCol('SELECT `route_id` FROM `outbound_routes` ORDER BY `route_id`');
	      if(DB::IsError($route_ids)) {
		      out("FATAL: ".$route_ids->getDebugInfo()."\n".'error getting route_ids to create outbound_route_sequence');	
        }
        // assumption here is that routepriorities always return in order, I think that is correct, which means we inserted in order
        $seq = 0;
        $outbound_route_sequence = array();
        foreach ($route_ids as $route_id) {
          outn("processing route_id $route_id..");
          $outbound_route_sequence[] = array($route_id,$seq);
          $seq++;

          $this_patterns = array_shift($dialpattern);

          $insert_patterns = array();
          foreach ($this_patterns as $pattern) {
            $parts = explode('|',$pattern,2);
            if (count($parts) == 1) {
              $prepend = '';
              $exten = $pattern;
            } else {
              $prepend = $parts[0];
              $exten = $parts[1];
            }
            $parts = explode('/',$exten,2);
            if (count($parts) == 1) {
              $insert_patterns[] = array($route_id, $prepend, $exten, '');
            } else {
              if ($parts[1][0] == "_") {
                $parts[1] = substr($parts[1],1);
              }
              $insert_patterns[] = array($route_id, $prepend, $parts[0], $parts[1]);
            }
          }
          $compiled = $db->prepare('INSERT INTO `outbound_route_patterns` (`route_id`, `match_pattern_prefix`, `match_pattern_pass`, `match_cid`) values (?,?,?,?)');
	        $result = $db->executeMultiple($compiled,$insert_patterns);
	        if(DB::IsError($result)) {
		        out("FATAL: ".$result->getDebugInfo()."\n".'error inserting into outbound_route_patterns table');	
          } else {
            outn('patterns..');
          }
          unset($insert_pattern);

          $this_trunks = array_shift($trunkpriority);
          $trunk_seq = 0;
          $insert_trunks = array();
          foreach ($this_trunks as $trunk) {
            $insert_trunks[] = array($route_id, substr($trunk,4),$trunk_seq);
            $trunk_seq++;
          }

          $compiled = $db->prepare('INSERT INTO `outbound_route_trunks` (`route_id`, `trunk_id`, `seq`) values (?,?,?)');
	        $result = $db->executeMultiple($compiled,$insert_trunks);
	        if(DB::IsError($result)) {
		        out("FATAL: ".$result->getDebugInfo()."\n".'error inserting into outbound_route_trunks table');	
          } else {
            outn('trunks..');
          }
          unset($insert_trunks);
          out("migrated");
        }

        outn('Updating route sequence..');
        $compiled = $db->prepare('INSERT INTO `outbound_route_sequence` (`route_id`, `seq`) values (?,?)');
	      $result = $db->executeMultiple($compiled,$outbound_route_sequence);
	      if(DB::IsError($result)) {
		      out("FATAL: ".$result->getDebugInfo()."\n".'error inserting into outbound_route_sequence table');	
        } else {
          out("ok");
          outn('Removing old extensions table..');
          //TODO: add removal code once thoroughly tested
          out("not implemented until thouroghly tested");
        }
      }
    }
  }
}

//----------  DEPRECATED functions added here so installs work from tarball --------------

function __core_routing_getroutenames() 
{
	global $amp_conf;
	global $db;
	
	if ($amp_conf["AMPDBENGINE"] == "sqlite3") 
	{
		$sql = "SELECT DISTINCT context FROM extensions WHERE context LIKE 'outrt-%' ORDER BY context ";
		$results = $db->getAll($sql);
		if(DB::IsError($results)) {
			die($results->getDebugInfo() . "SQL - <br /> $sql" );
		}

		foreach( array_keys($results) as $idx )
		{
			 $results[$idx][0] = substr( $results[$idx][0], 6);
		}
	}
	else
	{
		$sql = "SELECT DISTINCT SUBSTRING(context,7) FROM extensions WHERE context LIKE 'outrt-%' ORDER BY context ";
		$results = $db->getAll($sql);
		if(DB::IsError($results)) {
			die($results->getDebugInfo() . "SQL - <br /> $sql" );
		}
	}
	return $results;
}
function __core_routing_getroutecid($route) {
  global $db;

  $sql = "SELECT DISTINCT args FROM extensions WHERE context = 'outrt-".$route."' AND (args LIKE 'ROUTECID%' OR args LIKE 'EXTEN_ROUTE_CID%') ";
  $results = $db->getOne($sql);
  if(DB::IsError($results)) {
    die_freepbx($results->getMessage());
  }
  if (preg_match('/^(.*)=(.*)/', $results, $matches)) {
    $routecid = $matches[2];
    $routecid_mode = $matches[1] == 'ROUTECID' ? 'override_extension':'';
  } else {
    $routecid = '';
    $routecid_mode = '';
  }
  return array('routecid' => $routecid, 'routecid_mode' => $routecid_mode);
}
function __core_routing_getroutepassword($route) {
	global $db;
	$sql = "SELECT DISTINCT args FROM extensions WHERE context = 'outrt-".$route."' AND (args LIKE 'dialout-trunk,%' OR args LIKE 'dialout-enum,%' OR args LIKE 'dialout-dundi,%') ORDER BY CAST(priority as UNSIGNED) ";
	$results = $db->getOne($sql);
	if(DB::IsError($results)) {
		die_freepbx($results->getMessage());
	}
	if (preg_match('/^.*,.*,.*,(\d+|\/\S+)/', $results, $matches)) {
		$password = $matches[1];
	} else {
		$password = "";
	}
	return $password;
}
function __core_routing_getrouteemergency($route) {
	global $db;
	$sql = "SELECT DISTINCT args FROM extensions WHERE context = 'outrt-".$route."' AND (args LIKE 'EMERGENCYROUTE%') ";
	$results = $db->getOne($sql);
	if(DB::IsError($results)) {
		die_freepbx($results->getMessage());
	}
	if (preg_match('/^.*=(.*)/', $results, $matches)) {
		$emergency = $matches[1];
	} else {
		$emergency = "";
	}
	return $emergency;
}
function __core_routing_getrouteintracompany($route) {
  global $db;
  $sql = "SELECT DISTINCT args FROM extensions WHERE context = 'outrt-".$route."' AND (args LIKE 'INTRACOMPANYROUTE%') ";
  $results = $db->getOne($sql);
  if(DB::IsError($results)) {
    die_freepbx($results->getMessage());
  }
  if (preg_match('/^.*=(.*)/', $results, $matches)) {
    $intracompany = $matches[1];
  } else {
    $intracompany = "";
  }
  return $intracompany;
}
function __core_routing_getroutemohsilence($route) {
  global $db;
  $sql = "SELECT DISTINCT args FROM extensions WHERE context = 'outrt-".$route."' AND (args LIKE 'MOHCLASS%') ";
  $results = $db->getOne($sql);
  if(DB::IsError($results)) {
    die_freepbx($results->getMessage());
  }
  if (preg_match('/^.*=(.*)/', $results, $matches)) {
    $mohsilence = $matches[1];
  } else {
    $mohsilence = "";
  }
  return $mohsilence;
}
function __core_routing_getroutepatterns($route) {
	global $db;
	$sql = "SELECT extension, args FROM extensions WHERE context = 'outrt-".$route."' AND (args LIKE 'dialout-trunk%' OR args LIKE 'dialout-enum%' OR args LIKE 'dialout-dundi%') ORDER BY extension ";
	$results = $db->getAll($sql);
	if(DB::IsError($results)) {
		die_freepbx($results->getMessage());
	}
	
	$patterns = array();
	foreach ($results as $row) {
		if ($row[0][0] == "_") {
			// remove leading _
			$pattern = substr($row[0],1);
		} else {
			$pattern = $row[0];
		}
		
		if (preg_match("/{EXTEN:(\d+)}/", $row[1], $matches)) {
			// this has a digit offset, we need to insert a |
			$pattern = substr($pattern,0,$matches[1])."|".substr($pattern,$matches[1]);
		}
		
		$patterns[] = $pattern;
	}
	return array_unique($patterns);
}
function __core_routing_getroutetrunks($route) {
	global $db;
	$sql = "SELECT DISTINCT args FROM extensions WHERE context = 'outrt-".$route."' AND (args LIKE 'dialout-trunk,%' OR args LIKE 'dialout-enum,%' OR args LIKE 'dialout-dundi,%') ORDER BY CAST(priority as UNSIGNED) ";
	$results = $db->getAll($sql);
	if(DB::IsError($results)) {
		die_freepbx($results->getMessage());
	}
	$trunks = array();
	foreach ($results as $row) {
		if (preg_match('/^dialout-trunk,(\d+)/', $row[0], $matches)) {
			// check in_array -- even though we did distinct
			// we still might get ${EXTEN} and ${EXTEN:1} if they used | to split a pattern
			if (!in_array("OUT_".$matches[1], $trunks)) {
				$trunks[] = "OUT_".$matches[1];
			}
		} else if (preg_match('/^dialout-enum,(\d+)/', $row[0], $matches)) {
			if (!in_array("OUT_".$matches[1], $trunks)) {
				$trunks[] = "OUT_".$matches[1];
			}
		} else if (preg_match('/^dialout-dundi,(\d+)/', $row[0], $matches)) {
			if (!in_array("OUT_".$matches[1], $trunks)) {
				$trunks[] = "OUT_".$matches[1];
			}
		}
	}
	return $trunks;
}
