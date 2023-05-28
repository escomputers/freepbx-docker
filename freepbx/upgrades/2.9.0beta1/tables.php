<?php
// added again here for alpha testerd uprgrading
$sql = "SELECT sortorder FROM freepbx_settings";
$confs = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (DB::IsError($confs)) { // no error... Already done
  $sql = "ALTER TABLE freepbx_settings ADD sortorder INT ( 11 ) NOT NULL DEFAULT 0";
  $results = $db->query($sql);
  if(DB::IsError($results)) {
    die($results->getMessage());
  }
  out("Added field sortorder to freepbx_settings");
}
