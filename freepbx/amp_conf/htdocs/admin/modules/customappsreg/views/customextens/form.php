<?php
$custom_exten = isset($_REQUEST['extdisplay']) ? preg_replace("/[^0-9*#]/" ,"",$_REQUEST['extdisplay']) :  '';
if ($custom_exten != '') {
	// load
	$row = customappsreg_customextens_get($custom_exten);

	$description = $row['description'];
	$notes       = $row['notes'];

	$disp_description = $row['description'] != '' ? '('.$row['custom_exten'].') '.$row['description'] : '('.$row['custom_exten'].')';
	$subhead =  "<h2>"._("Edit: ")."$disp_description"."</h2>";
  $delURL = '?display=customextens&action=delete&extdisplay='.$custom_exten;
} else {
	$subhead = "<h2>"._("Add Custom Extension")."</h2>";
  $delURL = '';
}
if (!empty($ce->conflict_url)) {
	echo "<h5>"._("Conflicting Extensions")."</h5>";
	echo implode('<br .>',$conflict_url);
}
echo $subhead;
?>

<form name="editCustomExten" id="editCustomExten" class="fpbx-submit" action="config.php?display=customextens" method="post" onsubmit="return checkCustomExten(editCustomExten);" data-fpbx-delete="<?php echo $delURL?>">
	<input type="hidden" name="extdisplay" value="<?php echo $custom_exten; ?>">
	<input type="hidden" name="old_custom_exten" value="<?php echo $custom_exten; ?>">
	<input type="hidden" name="action" value="<?php echo ($custom_exten != '' ? 'edit' : 'add'); ?>">
  <!--Custom Extension-->
  <div class="element-container">
    <div class="row">
      <div class="col-md-12">
        <div class="row">
          <div class="form-group">
            <div class="col-md-3">
              <label class="control-label" for="extdisplay"><?php echo _("Custom Extension") ?></label>
              <i class="fa fa-question-circle fpbx-help-icon" data-for="extdisplay"></i>
            </div>
            <div class="col-md-9">
              <input type="text" class="form-control" id="extdisplay" name="extdisplay" value="<?php echo isset($custom_exten)?$custom_exten:''?>">
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="extdisplay-help" class="help-block fpbx-help-block"><?php echo _("This is the Extension or Feature Code you are using in your dialplan that you want the FreePBX Extension Registry to be aware of.")?></span>
      </div>
    </div>
  </div>
  <!--END Custom Extension-->
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
              <input type="text" class="form-control" id="description" name="description" value="<?php echo isset($description)?$description:''?>">
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="description-help" class="help-block fpbx-help-block"><?php echo _("Brief description that will be published in the Extension Registry about this extension")?></span>
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
              <textarea class="form-control" rows="6" id="notes" name="notes"><?php echo isset($notes)?$notes:''?></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="notes-help" class="help-block fpbx-help-block"><?php echo _("More detailed notes about this extension to help document it. This field is not used elsewhere.")?></span>
      </div>
    </div>
  </div>
  <!--END Notes-->
</form>

<script language="javascript">
<!--

function checkCustomExten() {
	var msgInvalidCustomExten = "<?php echo _('Invalid Extension, must not be blank'); ?>";
	var msgInvalidDescription = "<?php echo _('Invalid description specified, must not be blank'); ?>";

	// form validation
	defaultEmptyOK = false;

	if (isEmpty($('#extdisplay').val())) {
		return warnInvalid($('#extdisplay'), msgInvalidCustomExten);
	}
	if (isEmpty($('#description').val())) {
		return warnInvalid($('#extdisplay'), msgInvalidDescription);
	}

	return true;
}
$("#extdisplay").blur(function(){
  var msgDuplicateExten = "<?php echo _('The entered extension conflicts with another extension on the system'); ?>";
  var val = $("#extdisplay").val().trim();
  if(val.length > 0 && val in extmap){
    return warnInvalid($("#extdisplay"), msgDuplicateExten);
  }
});
//-->
</script>
