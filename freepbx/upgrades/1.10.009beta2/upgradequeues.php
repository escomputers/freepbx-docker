<?php  /* $Id$ */
/* set descr = jump for goto priorites */
outn("Upgrading Queues..");

// get all did routes
$sql = "SELECT extension FROM extensions WHERE context = 'ext-queues' and (application = 'Macro' or application = 'Goto') and priority = '5' ORDER BY extension";
$results = $db->getAll($sql);
if(DB::IsError($results)) {
        die($results->getMessage());
}

out(count($results)." to upgrade...");

if (count($results) > 0) {
        // yes, there are queues defined and gotos are at priority 5

        foreach ($results as $key => $value) {
			$sql = "UPDATE extensions SET descr = 'jump' WHERE context = 'ext-queues' AND extension = '".$value[0]."' AND priority = '5' LIMIT 1";
			$results = $db->query($sql);
			if(DB::IsError($results)) {
      		  die($results->getMessage());
			}
        }
}

out("OK");

?>          