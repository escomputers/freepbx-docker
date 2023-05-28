<?php /* $Id: custom-context.php $ */
/*
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
  
global $db;
global $amp_conf;

if (! function_exists("out")) {
	function out($text) {
		echo $text."<br />";
	}
}

if (! function_exists("outn")) {
	function outn($text) {
		echo $text;
	}
}

//check if we have custom context installed, and migrate them if we do
$sql = 'DESCRIBE customcontexts_includes_list';
$test = $db->getAll($sql);
if(!DB::IsError($test)) { 
	$sql = "SELECT version FROM modules WHERE modulename = 'customcontexts'";
	$results = $db->getRow($sql,DB_FETCHMODE_ASSOC);
	if (isset($results['version'])) {
		$ver = $results['version'];
	} else {
		$ver = null;
	}
	outn(_("checking if Custom Context migration is required..."));
	if ($ver !== null && version_compare($ver, "2.8.0beta1.0", "<")) {
			outn(_("migrating.."));
			/* 
			 * We need to now migrate from from the old format of dispname_id where the only supported dispname
			 * so far has been "routing" and the "id" used was the imperfect nnn-name. As it truns out, it was
			 * possible to have the same route name perfiously so we will try to detect that. This was really ugly
			 * so if we can't find stuff we will simply report errors and let the user go back and fix things.
			 */
			$sql = "SELECT * FROM customcontexts_includes_list WHERE context = 'outbound-allroutes'";
			$includes = $db->getAll($sql, DB_FETCHMODE_ASSOC);
			if(DB::IsError($includes) || !isset($includes)) { 
				out(_("Unknown error fetching Custom Context table data or no data to migrate"));
				out(_("Custom Context Migration aborted"));
			} elseif (substr_count($includes[0]['include'],'-') == 1) { //check to see if the routes were migrated yet. Kludgy, but it works
				out(_("Already Migrated!"));
			} else {
				/* 
				 * If there are any rows then lets get our route information. We will force this module to depend on
				 * the new core, so we can count on the APIs being available. If there are indentical names, then
				 * oh well...
				 */
				$sql = "SELECT a.*, b.seq FROM `outbound_routes` a JOIN `outbound_route_sequence` b ON a.route_id = b.route_id ORDER BY `seq`";
				$routes = $db->getAll($sql,DB_FETCHMODE_ASSOC);
				if(DB::IsError($routes)) {
					die($routes->getDebugInfo() . "SQL - <br /> $sql" );
				}
				$newincludes = array();
				foreach ($includes as $inc => $myinclude) {
					$include = explode('-',$myinclude['include'],3);
					$include[1] = (int)$include[1];
					foreach ($routes as $rt => $route) {
						//if we have a trunk with the same name match it and take it out of the list
						if ($include[2] == $route['name']){
							$newincludes[] = array('new' => 'outrt-'.$route['route_id'], 
																		'sort' => $route['seq'], 'old' => $myinclude['include']);
							//unset the routes so we dont search them again
							unset($includes[$inc]);
							unset($routes[$rt]);
						} 
					}	
				}

				//alert user of unmigrated routes
				foreach ($includes as $include) {
					out(_('FAILED to migrating Custom Context route '.$include['description'].'. NO MATCH FOUND'));
					outn(_("Continuing..."));
				}

				// We new have all the indices, so lets save them
				$sql = $db->prepare('UPDATE customcontexts_includes_list SET include = ?, sort = ? WHERE include = ?');
				$result = $db->executeMultiple($sql,$newincludes);
				if(DB::IsError($result)) {
					out("FATAL: ".$result->getDebugInfo()."\n".'error updating customcontexts_includes_list table. Aborting!');	
				} else {
					//now update the customcontexts_includes table
					foreach ($newincludes as $inc => $newinclude){ 
						unset($newincludes[$inc]['sort']);
				}
					$sql = $db->prepare('UPDATE customcontexts_includes SET include = ? WHERE include = ?');
					$result = $db->executeMultiple($sql,$newincludes);
					if(DB::IsError($result)) {
						out("FATAL: ".$result->getDebugInfo()."\n".'error updating customcontexts_includes table. Aborting!');	
					} else {
					out(_("done! Reload FreePBX to update all Custom Context settings."));			    
				}
			}
		}
	} else {
	  out(_("not needed"));
	}
}
?>
