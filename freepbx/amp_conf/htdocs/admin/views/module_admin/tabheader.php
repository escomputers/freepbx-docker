<?php
if ($activetab == "modules") {
	$m = 'class="active"';
	$s = '';
} else {
	$s = 'class="active"';
	$m = '';
}
?>

<div class='fpbx-container container-fluid'>
  <?php
      echo $httpdRestart;
  ?>
  <ul class='nav nav-tabs' role='tablist'>
    <li role="presentation" <?php echo $s; ?>><a href="#summarytab" aria-controls="summarytab" role="tab" data-toggle="tab"><?php echo _("Summary")?></a></li>
    <li role="presentation"><a href="#scheduletab" aria-controls="scheduletab" role="tab" data-toggle="tab"><?php echo _("Scheduler and Alerts")?></a></li>
    <li role="presentation" <?php echo $m; ?>><a href="#modulestab" aria-controls="modulestab" role="tab" data-toggle="tab"><?php echo _("Module Updates")?></a></li>
    <li role="presentation"><a href="#systemupdatestab" aria-controls="systemupdatestab" role="tab" data-toggle="tab"><?php echo _("System Updates")?></a></li>
  </ul>
  <div class='tab-content display'>


