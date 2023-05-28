<?php

/** Remove the old Zap Channel Routing in favor of assigning DIDs to Zap Channels
 *  and then use starndard DID routing for all incoming calls. This increases the
 *  flexibility since multiple channels can point to the same DID if desired and
 *  then DID or DID+CID routing can be used.
 *
 *  1. Check if zapchandids table exists by trying to create it. If the table creation
 *     fails then it must already exist (or something else is wrong) so we stop.
 *  2. Select all channel routes in incoming and create entries in zapchandids table.
 *     point to a special DID (zapchan<nn>) which can later be changed if desired.
 *  3. Update old Zap Channel entries in 'incoming' table to the new DID.
 *  4. Drop the old channel field in incoming which is no longer needed.
 */
outn("Trying to create zapchandids table..");
$sql = "
CREATE TABLE zapchandids (
	channel int(11) NOT NULL default '0',
	description varchar(40) NOT NULL default '',
	did varchar(60) NOT NULL default '',
	PRIMARY KEY  (channel)
)";
$results = $db->query($sql);
if(!DB::IsError($results)) {
	out("Created, Starting Conversion");
	outn("Creating Zap Channel DIDs and converting old routes to DIDs..");
	$chan_prefix = 'zapchan';
	$sql="SELECT channel, description FROM incoming WHERE channel != ''";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if(DB::IsError($results)) {
		out("FATAL ERROR: ".$results->getMessage());
	} else {
		outn("Got ".count($results)." channels, converting..");
		foreach ($results as $row) {
			$channel     = $row['channel'];
			$description = $row['description'];
			$did         = $chan_prefix.$channel;
			$sql = "INSERT INTO zapchandids (channel, description, did) VALUES ('$channel', '$description', '$did')";
			$results = $db->query($sql);
			if (DB::IsError($results)) { 
				out("ERROR: ".$channel);
    		out("Error inserting $channel to zapchandids: ".$results->getMessage());
				outn("Continuing..");
			} else {
				outn($channel.".");
			}
		}
		out(".OK");
		outn("Updating old zap routes to did routes..");
		// Now update the incoming table:
		$sql = "UPDATE incoming SET extension=CONCAT('$chan_prefix',channel), channel='' WHERE channel != ''";
		$results = $db->query($sql);
		if (DB::IsError($results)) { 
   		out("FATAL: failed to transform old routes: ".$results->getMessage());
		} else {
			out("OK");
			outn("Removing deprecated channel field from incoming..");
			$sql = "ALTER TABLE incoming DROP channel";
			$results = $db->query($sql);
			if (DB::IsError($results)) { 
   			out("ERROR: failed: ".$results->getMessage());
			} else {
				out("OK");
			}
		}
	}
} else {
	out("already exists, no conversion will be done");
}

outn("Converting ampusers sections table from varchar 255 to blob to handle large numbers of modules..");
$sql = "ALTER TABLE `ampusers` CHANGE `sections` `sections` BLOB NOT NULL";
$results = $db->query($sql);
if(DB::IsError($results)) {
	out("ERROR: failed to convert table ".$results->getMessage());
} else {
	out("OK");
}

?>
