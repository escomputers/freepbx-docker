<div class="element-container">
	<div class="row">
		<div class="form-group">
			<div class="col-md-3">
				<label class="control-label" for="vm"><?php echo _('Enable Voicemail')?></label>
			</div>
			<div class="col-md-9">
				<span class="radioset">
					<input type="radio" name="vm" id="vm_on" value="yes" checked>
					<label for="vm_on"><?php echo _('Yes')?></label>
					<input type="radio" name="vm" id="vm_off" value="no">
					<label for="vm_off"><?php echo _('No')?></label>
				</span>
			</div>
		</div>
	</div>
</div>
<div class="element-container">
	<div class="row">
		<div class="form-group">
			<div class="col-md-3">
				<label class="control-label" for="vmpwd"><?php echo _('Voicemail PIN')?></label>
				<i class="fa fa-question-circle fpbx-help-icon" data-for="vmpwd"></i>
			</div>
			<div class="col-md-9">
				<input type="text" name="vmpwd" class="form-control confidential" id="vmpwd"/>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="vmpwd-help" class="help-block fpbx-help-block"><?php echo _('This is the PIN used to access the Voicemail system. It should be between 4 and 12 numbers in length.')?></span>
		</div>
	</div>
</div>
<script>
	$("#vm_on").click(function() {
		$("#vmpwd").prop("disabled",false);
		$(".toggle-password[data-id=vmpwd]").prop("disabled",false);
	});
	$("#vm_off").click(function() {
		$("#vmpwd").prop("disabled",true);
		$(".toggle-password[data-id=vmpwd]").prop("disabled",true);
	});
	$("#vmpwd").val($("#extension").val());
	$("#extension").on("propertychange change click keyup input paste", function() {
		$("#vmpwd").val($("#extension").val());
	});
</script>
