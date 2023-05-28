<?php

// Meetme table will only exist if Conferencing module installed
$sql = "SELECT exten FROM meetme";
$confs = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($confs)) { // no error...module installed

	// Add 'joinmsg' field
	$sql = "SELECT joinmsg FROM meetme";
	$confjoin = $db->getRow($sql, DB_FETCHMODE_ASSOC);
	if(DB::IsError($confjoin)) { // error, new field doesn't exist
		$sql = "ALTER TABLE meetme ADD joinmsg VARCHAR( 255 ) NULL";
		$result = $db->query($sql);
		if(DB::IsError($result)) {
			die($result->getDebugInfo());
		}
	}
}

// Incoming table should exist, but you never know
$sql = "SELECT destination FROM incoming";
$incomes = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($incomes)) { // no error...module installed

        // Add 'alertinfo' field
        $sql = "SELECT alertinfo FROM incoming";
        $incomealertinfo = $db->getRow($sql, DB_FETCHMODE_ASSOC);
        if(DB::IsError($incomealertinfo)) { // error, new field doesn't exist
                $sql = "ALTER TABLE incoming ADD alertinfo VARCHAR( 32 ) NULL";
                $result = $db->query($sql);
                if(DB::IsError($result)) {
                        die($result->getDebugInfo());
                }
        }

}

?>

