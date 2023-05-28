<?php
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2015 Sangoma Technologies.
//
extract($request);
if ($extdisplay) {
	// load
	$row = callrecording_get($extdisplay);
	$description = $row['description'];
	$callrecording_mode   = $row['callrecording_mode'];
	$dest        = $row['dest'];
	$cm_disp = $callrecording_mode ? $callrecording_mode : 'allow';
}

$CallReclist = callrecording_list();
if($CallReclist){
	$CallRecDesc = array();
	foreach($CallReclist as $tmp_CallRecList){
		if($extdisplay !=  $tmp_CallRecList['callrecording_id']){
			$CallRecDesc[] = $tmp_CallRecList['description'];
		}
	}
}
?>
<script>
var description = [];
<?php
if(!empty($CallRecDesc)){
	echo "description = " . json_encode($CallRecDesc) . ";";
}
?>
</script>
<?php
if ($callrecording_mode == "delayed") {
	$callrecording_mode = "yes";
}
if ($callrecording_mode == "") {
	$callrecording_mode = "dontcare";
}
$options = array(_("Force") => "force", _("Yes") => "yes", _("Don't Care") => "dontcare", _("No") => "no", _("Never") => "never");
foreach ($options as $disp => $name) {
	if ($callrecording_mode == $name) {
		$checked = "checked";
	} else {
		$checked = "";
	}
	$ropts .= "<input type='radio' id='record_${name}' name='callrecording_mode' value='$name' $checked><label for='record_${name}'>$disp</label>";
}

if ($extdisplay) {
	$usage_list = framework_display_destination_usage(callrecording_getdest($extdisplay));
	if (!empty($usage_list)) {
		$usagehtml = '<div class="well">';
		$usagehtml .= '<h4>'.$usage_list['text'].'</h4>';
		$usagehtml .= '<p>'. $usage_list['tooltip'].'</p>';
		$usagehtml .= '</div>';
	}
}
echo $usagehtml;
?>
<form name="editCallRecording" class="fpbx-submit" action="?display=callrecording" method="post" onsubmit="return checkCallRecording(editCallRecording);" data-fpbx-delete="?display=callrecording&amp;callrecording_id=<?php echo $extdisplay ?>&amp;action=delete">
<input type="hidden" name="extdisplay" value="<?php echo $extdisplay; ?>">
<input type="hidden" name="callrecording_id" value="<?php echo $extdisplay; ?>">
<input type="hidden" name="action" value="<?php echo ($extdisplay ? 'edit' : 'add'); ?>">
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
						<input type="text" class="form-control" id="description" name="description" value="<?php  echo $description; ?>">
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="description-help" class="help-block fpbx-help-block"><?php echo _("The descriptive name of this call recording instance. For example \"French Main IVR\"")?></span>
		</div>
	</div>
</div>
<!--END Description-->
<div class="well">
	<a href='http://wiki.freepbx.org/display/FPG/Call+Recording+walk+through'>
	<p><?php echo _("Note that the meaning of these options has changed."); ?>
	</a>
</div>
<!--Call Recording Mode-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="callrecording_mode"><?php echo _("Call Recording Mode") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="callrecording_mode"></i>
					</div>
					<div class="col-md-9 radioset">
						<?php echo $ropts ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="callrecording_mode-help" class="help-block fpbx-help-block"><?php echo _("Please read the wiki for futher information on these changes.")?></span>
		</div>
	</div>
</div>
<!--END Call Recording Mode-->
<!--Destination-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="goto0"><?php echo _("Destination") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="goto0"></i>
					</div>
					<div class="col-md-9">
						<?php echo drawselects($dest,0);?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="goto0-help" class="help-block fpbx-help-block"><?php echo _("Where should the call be sent.")?></span>
		</div>
	</div>
</div>
<!--END Destination-->
</form>
