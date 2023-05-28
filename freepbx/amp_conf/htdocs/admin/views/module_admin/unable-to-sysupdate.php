<?php
// vim: :set filetype=php tabstop=4 shiftwidth=4 autoindent smartindent:
?>
<div role="tabpanel" class="tab-pane" id="systemupdatestab">
  <div class='container-fluid' style='padding-top: .75em'>
    <div class='panel panel-danger'>
      <div class='panel-heading'>
        <h3 class='panel-title'><?php echo _("System updates not available."); ?></h3>
      </div>
      <div class='panel-body'>
<?php
echo "<p>"._("System updates are Operating-system level updates. These require an <strong>Activated</strong> machine that is running a compatible Operating System Distro, such as AsteriskNow, PBXact, or FreePBX Distro.")."</p>\n";
echo "<p>"._("As this machine does not meet those requirements, system updates must be manually performed by the operator, using 'yum', 'apt', or equivalent.")."</p>\n";
echo "<p>"._("For a list of Operating Systems that can be automatically updated, please see the FreePBX Wiki.")."</p>\n";
?>
      </div>
    </div>
  </div>
</div>


