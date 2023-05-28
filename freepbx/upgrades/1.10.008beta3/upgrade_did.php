<?php  /* $Id$ */
/* check for old did routes entirs */
outn("Upgrading DID Routes..");

// get all did routes
$sql = "SELECT extension FROM extensions WHERE context = 'ext-did' and (application = 'Macro' or application = 'Goto') and priority = '1' ORDER BY extension";
$results = $db->getAll($sql);
if(DB::IsError($results)) {
        die($results->getMessage());
}

out(count($results)." to upgrade...");

if (count($results) > 0) {
        // yes, there are dids defined

        foreach ($results as $key => $value) {
			$sql = "UPDATE extensions SET priority = '2' WHERE context = 'ext-did' AND extension = '".$value[0]."' AND  priority =  '1' LIMIT 1";
			$results = $db->query($sql);
			if(DB::IsError($results)) {
      		  die($results->getMessage());
			}
			$sql = "INSERT INTO extensions (context, extension, priority, application, args, descr, flags) VALUES ('ext-did', '".$value[0]."', '1', 'SetVar', 'FROM_DID=".$value[0]."', '' , '0')";
			$results = $db->query($sql);
			if(DB::IsError($results)) {
      		  die($results->getMessage());
			}
        }
}

out("OK");

?>                 