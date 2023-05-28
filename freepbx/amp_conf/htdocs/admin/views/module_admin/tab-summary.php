<?php
// vim: :set filetype=php tabstop=4 shiftwidth=4 autoindent smartindent:
if ($activetab == "summary") {
	$c = 'class="tab-pane active"';
} else {
	$c = 'class="tab-pane"';
}
?>
<div role="tabpanel" <?php echo $c; ?> id="summarytab" style='padding-top: 1em'>
<?php
if($edgemode) {
	print "<div class='container-fluid'>";
	print show_help(sprintf(_("This system has edge mode enabled. This means you will get modules as they are released and may encounter bugs not seen in general availibility modules.</br> For more information visit %s"),'<a href="http://wiki.freepbx.org/x/boi3Aw">http://wiki.freepbx.org/x/boi3Aw</a>'), _('EDGE MODE'), false, false);
  	print "</div>";
}
?>
  <div class='container-fluid'>
    <div class='row'>
      <div class='col-xs-5 col-sm-4 col-md-3'><?php echo _("Current PBX Version:"); ?></div>
      <div class='col-xs-7 col-sm-8'><?php echo get_framework_version(); ?></div>
    </div>

    <div class='row'>
      <div class='col-xs-5 col-sm-4 col-md-3'><?php echo _("Current System Version:"); ?></div>
	  <div class='col-xs-7 col-sm-8'><?php echo $pbxversion; ?> &nbsp;
<?php if ($systemupdateavail) {
	echo "<span class='sysupdateavail'>".sprintf(_("Notice: System Upgrade (%s) available!"), $systemupdateavail['version'])."</span>";
}
?>
	  </div>
    </div>

    <div class='row'>
      <div class='col-xs-5 col-sm-4 col-md-3'><?php echo _("Total Module Count:"); ?></div>
      <div class='col-xs-7 col-sm-8'><?php echo $totalmodules; ?></div>
    </div>

    <div class='row'>
      <div class='col-xs-5 col-sm-4 col-md-3'><?php echo _("Enabled:"); ?></div>
      <div class='col-xs-7 col-sm-8'><?php echo $activemodules; ?></div>
    </div>

<?php if ($disabledmodules) { ?>
    <div class='row'>
      <div class='col-xs-5 col-sm-4 col-md-3'><?php echo _("Disabled:"); ?></div>
      <div class='col-xs-7 col-sm-8'><?php echo $disabledmodules; ?></div>
    </div>
<?php } ?>

<?php if ($brokenmodules) { ?>
    <div class='row'>
      <div class='col-xs-5 col-sm-4 col-md-3'><?php echo _("Broken:"); ?></div>
      <div class='col-xs-7 col-sm-8'><?php echo $brokenmodules; ?></div>
    </div>
<?php } ?>

<?php if ($needsupgrademodules) { ?>
    <div class='row'>
      <div class='col-xs-5 col-sm-4 col-md-3'><?php echo _("Needs Upgrade:"); ?></div>
      <div class='col-xs-7 col-sm-8'><?php echo $needsupgrademodules; ?></div>
    </div>
<?php } ?>

	<p style='padding-top: .5em'><?php echo _("The numbers below may be inaccurate if new modules have been released since the last check:"); ?></p>

    <div class='row'>
      <div class='col-xs-5 col-sm-4 col-md-3'><?php echo _("Last online check:"); ?></div>
      <div class='col-xs-7 col-sm-8'><?php echo $lastonlinecheck->format("c"); ?></div>
    </div>

    <div class='row'>
      <div class='col-xs-5 col-sm-4 col-md-3'><?php echo _("Modules with Upgrades:"); ?></div>
	  <div class='col-xs-7 col-sm-8'><a class='clickable' id='moduleupdatecount'><?php echo count($availupdates); ?></a></div>
    </div>

    <div class='row'>
      <div class='col-xs-5 col-sm-4 col-md-3'><?php echo _("System Upgrades Available:"); ?></div>
	  <div class='col-xs-7 col-sm-8'><a class='clickable showsystemupdatestab'><?php echo $pendingupgradessystem; ?></a></div>
    </div>

  </div>
</div>
