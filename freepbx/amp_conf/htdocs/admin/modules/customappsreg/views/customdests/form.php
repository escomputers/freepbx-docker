<?php
$readonly = '';
$warning = '';
$usage = '';
if (isset($_REQUEST['destid']) && isset($allDests[$_REQUEST['destid']])) {
	$destid = $_REQUEST['destid'];
	$current = $allDests[$destid];
  $current['dest']  = isset($current['dest'])?$current['dest']:'';
	$usage_list = framework_display_destination_usage(\FreePBX::Customappsreg()->getDestTarget($destid));
  if($usage_list){
    $warning = _("WARNING: This destination is being used by other module objects. Changing this destination may cause unexpected issue.");
    $usage .= '<div class="info info-warning">';
    $usage .= '<p><b>'.$warning.'</b></p>';
    $usage .= '</div>';
  }
  $delURL = '?display=customdests&action=delete&destid='.$destid;
} else {
	$current = array("target" => "", "description" => "", "notes" => "", "destret" => false, 'dest' => '');
	$usage_list = false;
	$destid = false;
  $delURL = '';
}
$target  = $current['target'];
$desc    = $current['description'];
$notes   = $current['notes'];
$destret = $current['destret'];


if ($destid) {
	$subhead = "<h2>"._("Edit: ")."$desc</h2>\n";
} else {
	$subhead = "<h2>"._("Add Custom Destination")."</h2>\n";
}
?>

<?php echo $subhead?>
<?php echo $usage?>
<?php
if($allDests){
	$dest_decs = array();
	foreach($allDests as $tmp_dest){
		if($destid != $tmp_dest['destid']){
			$dest_decs[] = $tmp_dest['description'];
	       }
	}
}
?>
<script>
var dest_decs = [];
<?php
if(!empty($dest_decs)){
	echo "dest_decs = " . json_encode($dest_decs) . ";";
}
?>
</script>
<form class="fpbx-submit" id="destsForm" name="destsForm" action="?display=customdests" method="post" data-fpbx-delete="<?php echo $delURL?>" onsubmit="return checkCustomDest();">
  <input type="hidden" name="action" value="<?php echo $destid?'edit':'add'?>">
  <input type="hidden" name="destid" value="<?php echo $destid?$destid:''?>" <?php echo $destid?'':'disabled'?>>
  <!--Target-->
  <div class="element-container">
    <div class="row">
      <div class="col-md-12">
        <div class="row">
          <div class="form-group">
            <div class="col-md-3">
              <label class="control-label" for="target"><?php echo _("Target") ?></label>
              <i class="fa fa-question-circle fpbx-help-icon" data-for="target"></i>
            </div>
            <div class="col-md-9">
              <input type="text" class="form-control" id="target" name="target" value="<?php echo isset($target)?$target:''?>">
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="target-help" class="help-block fpbx-help-block"><?php echo _("This is the Custom Destination to be published. It should be formatted exactly as you would put it in a goto statement, with context, exten, priority all included. An example might look like:<br />mycustom-app,s,1")?></span>
      </div>
    </div>
  </div>
  <!--END Target-->
<?php
$unknown = \FreePBX::Customappsreg()->getUnknownDests();
if ($unknown) {
?>
  <!--Destination Quick Pick-->
  <div class="element-container">
    <div class="row">
      <div class="col-md-12">
        <div class="row">
          <div class="form-group">
            <div class="col-md-3">
              <label class="control-label" for="insdest"><?php echo _("Destination Quick Pick") ?></label>
              <i class="fa fa-question-circle fpbx-help-icon" data-for="insdest"></i>
            </div>
            <div class="col-md-9">
              <select class="form-control" id="insdest" onChange="insertDest();" <?php echo $readonly?>>
                <option value=""><?php echo _("(pick destination)")?></option>
                <?php foreach ($unknown as $thisdest) { echo "<option value='$thisdest'>$thisdest</option>\n"; } ?>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="insdest-help" class="help-block fpbx-help-block"><?php echo _("Choose un-identified destinations on your system to add to the Custom Destination Registry. This will insert the chosen entry into the Custom Destination box above.")?></span>
      </div>
    </div>
  </div>
  <!--END Destination Quick Pick-->
<?php
}
?>
  <!--Description-->
  <div class="element-container">
    <div class="row">
      <div class="col-md-12">
        <div class="row">
          <div class="form-group">
            <div class="col-md-3">
              <label class="control-label" for="description"><?php echo _("Description") ?></label>
              <i class="fa fa-question-circle fpbx-help-icon" data-for="description"></i>
            </div>
            <div class="col-md-9">
              <input type="text" class="form-control" id="description" name="description" value="<?php echo isset($desc)?$desc:''?>">
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="description-help" class="help-block fpbx-help-block"><?php echo _("")?></span>
      </div>
    </div>
  </div>
  <!--END Description-->
  <!--Notes-->
  <div class="element-container">
    <div class="row">
      <div class="col-md-12">
        <div class="row">
          <div class="form-group">
            <div class="col-md-3">
              <label class="control-label" for="notes"><?php echo _("Notes") ?></label>
              <i class="fa fa-question-circle fpbx-help-icon" data-for="notes"></i>
            </div>
            <div class="col-md-9">
              <textarea class="form-control" id="notes" name="notes" rows="6"><?php echo isset($notes)?$notes:''?></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="notes-help" class="help-block fpbx-help-block"><?php echo _("More detailed notes about this destination to help document it. This field is not used elsewhere.")?></span>
      </div>
    </div>
  </div>
  <!--END Notes-->
  <!--Return-->
  <div class="element-container">
    <div class="row">
      <div class="col-md-12">
        <div class="row">
          <div class="form-group">
            <div class="col-md-3">
              <label class="control-label" for="destret"><?php echo _("Return") ?></label>
              <i class="fa fa-question-circle fpbx-help-icon" data-for="destret"></i>
            </div>
            <div class="col-md-9 radioset">
              <input type="radio" name="destret" id="destretyes" value="1" <?php echo ($current['destret']?"CHECKED":"") ?>>
              <label for="destretyes"><?php echo _("Yes");?></label>
              <input type="radio" name="destret" id="destretno" value="0" <?php echo ($current['destret']?"":"CHECKED") ?>>
              <label for="destretno"><?php echo _("No");?></label>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="destret-help" class="help-block fpbx-help-block"><?php echo _("Does this destination end with 'Return'? If so, you can then select a subsequent destination after this call flow is complete.")?></span>
      </div>
    </div>
  </div>
  <!--END Return-->
  <!--Destination-->
  <div class="element-container <?php echo ($current['destret']?"":"hidden")?>" id="hasreturn">
    <div class="row">
      <div class="col-md-12">
        <div class="row">
          <div class="form-group">
            <div class="col-md-3">
              <label class="control-label" for="goto0"><?php echo _("Destination") ?></label>
              <i class="fa fa-question-circle fpbx-help-icon" data-for="goto0"></i>
            </div>
            <div class="col-md-9">
              <?php echo drawselects($current['dest'],0,false,false); ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="goto0-help" class="help-block fpbx-help-block"><?php echo _("Set the Destination after return")?></span>
      </div>
    </div>
  </div>
  <!--END Destination-->
</form>

<script type="text/javascript">
$(document).ready(function(){
  $('input[name="destret"]').change(function(){
    var value = $(this).val();
    if(value == 1){
      $("#hasreturn").removeClass('hidden');
    }else{
      $("#hasreturn").addClass('hidden');
    }
  });
});
function insertDest() {

  dest = document.getElementById('insdest').value;
  customDest=document.getElementById('target');

  if (dest != '') {
    customDest.value = dest;
  }

  // reset element
  document.getElementById('insdest').value = '';
}
function checkCustomDest() {
  var theForm = document.getElementById('destsForm');
	var msgInvalidCustomDest = "<?php echo _('Invalid Destination, must not be blank, must be formatted as: context,exten,pri'); ?>";
	var msgInvalidDescription = "<?php echo _('Invalid description specified, must not be blank'); ?>";

	// Make sure the custom dest is in the form "context,exten,pri"
	var re = /[^,]+,[^,]+,[^,]+/;

	// form validation
	defaultEmptyOK = false;

	if (isEmpty(theForm.target.value) || !re.test(theForm.target.value)) {
		warnInvalid(theForm.target, msgInvalidCustomDest);
    return false;
	}
	if (isEmpty(theForm.description.value)) {
		warnInvalid(theForm.description, msgInvalidDescription);
    		return false;
	}else{
		var tmp_description = theForm.description.value.trim();
		if($.inArray(tmp_description, dest_decs) != -1){
			return warnInvalid( theForm.description, tmp_description  + _(" already used, please use a different description."));
		}
	}

	return true;
}
</script>
