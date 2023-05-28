<?php

// Add default VM_DDTYPE of '' if no VM_DDTYPE exists already
$sql = "SELECT value FROM globals WHERE variable = 'VM_DDTYPE' ";
$asa = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!is_array($asa)) { // does not exist already
        // Default to ''
        $sql = "INSERT INTO globals (variable, value) VALUES ('VM_DDTYPE', 'u') ";
        $result = $db->query($sql);
        if(DB::IsError($result)) {
                die($result->getDebugInfo());
        }
}

// Add default VM_GAIN of '' if no VM_GAIN exists already
$sql = "SELECT value FROM globals WHERE variable = 'VM_GAIN' ";
$asb = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (!is_array($asb)) { // does not exist already
        // Default to ''
        $sql = "INSERT INTO globals (variable, value) VALUES ('VM_GAIN', '') ";
        $result = $db->query($sql);
        if(DB::IsError($result)) {
                die($result->getDebugInfo());
        }
}

?>
