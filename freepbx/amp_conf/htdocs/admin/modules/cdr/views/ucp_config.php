<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="cdr_enable"><?php echo _("Allow CDR")?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="cdr_enable"></i>
					</div>
					<div class="col-md-9">
						<span class="radioset">
							<input type="radio" name="cdr_enable" id="cdr_enable_yes" value="yes" <?php echo ($enable) ? 'checked' : ''?>>
							<label for="cdr_enable_yes"><?php echo _('Yes')?></label>
							<input type="radio" name="cdr_enable" id="cdr_enable_no" value="no" <?php echo (!is_null($enable) && !$enable) ? 'checked' : ''?>>
							<label for="cdr_enable_no"><?php echo _('No')?></label>
							<?php if($mode == "user") {?>
								<input type="radio" id="cdr_enable_inherit" name="cdr_enable" value='inherit' <?php echo is_null($enable) ? 'checked' : ''?>>
								<label for="cdr_enable_inherit"><?php echo _('Inherit')?></label>
							<?php } ?>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="cdr_enable-help" class="help-block fpbx-help-block"><?php echo _("Enable CDR (Call History) in UCP for this user")?></span>
		</div>
	</div>
</div>
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="ucp_cdr"><?php echo _("CDR Access")?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="ucp_cdr"></i>
					</div>
					<div class="col-md-9">
						<select data-placeholder="Extensions" id="ucp_cdr" class="form-control chosenmultiselect ucp-cdr" name="ucp_cdr[]" multiple="multiple" <?php echo (!is_null($enable) && !$enable) ? "disabled" : ""?>>
							<?php foreach($ausers as $key => $value) {?>
								<option value="<?php echo htmlentities($key,ENT_QUOTES,'UTF-8',FALSE)?>" <?php echo in_array($key,$cdrassigned) ? 'selected' : '' ?>><?php echo htmlentities($value,ENT_QUOTES,'UTF-8',FALSE)?></option>
							<?php } ?>
						</select>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="ucp_cdr-help" class="help-block fpbx-help-block"><?php echo _("These are the assigned and active extensions which will show up for this user to control and edit in UCP")?></span>
		</div>
	</div>
</div>
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="cdr_download"><?php echo _("Allow CDR Downloads")?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="cdr_download"></i>
					</div>
					<div class="col-md-9">
						<span class="radioset">
							<input type="radio" class="ucp-cdr" name="cdr_download" id="cdr_download_yes" value="yes" <?php echo ($download) ? 'checked' : ''?> <?php echo (!is_null($enable) && !$enable) ? "disabled" : ""?>>
							<label for="cdr_download_yes"><?php echo _('Yes')?></label>
							<input type="radio" class="ucp-cdr" name="cdr_download" id="cdr_download_no" value="no" <?php echo (!is_null($download) && !$download) ? 'checked' : ''?> <?php echo (!is_null($enable) && !$enable) ? "disabled" : ""?>>
							<label for="cdr_download_no"><?php echo _('No')?></label>
							<?php if($mode == "user") {?>
								<input type="radio" class="ucp-cdr" id="cdr_download_inherit" name="cdr_download" value='inherit' <?php echo is_null($download) ? 'checked' : ''?> <?php echo (!is_null($enable) && !$enable) ? "disabled" : ""?>>
								<label for="cdr_download_inherit"><?php echo _('Inherit')?></label>
							<?php } ?>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="cdr_download-help" class="help-block fpbx-help-block"><?php echo _("Allow users to download recordings in UCP")?></span>
		</div>
	</div>
</div>
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="cdr_playback"><?php echo _("Allow CDR Playback")?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="cdr_playback"></i>
					</div>
					<div class="col-md-9">
						<span class="radioset">
							<input type="radio" class="ucp-cdr" name="cdr_playback" id="cdr_playback_yes" value="yes" <?php echo ($playback) ? 'checked' : ''?> <?php echo (!is_null($enable) && !$enable) ? "disabled" : ""?>>
							<label for="cdr_playback_yes"><?php echo _('Yes')?></label>
							<input type="radio" class="ucp-cdr" name="cdr_playback" id="cdr_playback_no" value="no" <?php echo (!is_null($playback) && !$playback) ? 'checked' : ''?> <?php echo (!is_null($enable) && !$enable) ? "disabled" : ""?>>
							<label for="cdr_playback_no"><?php echo _('No')?></label>
							<?php if($mode == "user") {?>
								<input type="radio" class="ucp-cdr" id="cdr_playback_inherit" name="cdr_playback" value='inherit' <?php echo is_null($playback) ? 'checked' : ''?> <?php echo (!is_null($enable) && !$enable) ? "disabled" : ""?>>
								<label for="cdr_playback_inherit"><?php echo _('Inherit')?></label>
							<?php } ?>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="cdr_playback-help" class="help-block fpbx-help-block"><?php echo _("Allow this user to playback recordings in UCP")?></span>
		</div>
	</div>
</div>
<script>
	$("input[name=cdr_enable]").change(function() {
		if($(this).val() == "yes" || $(this).val() == "inherit") {
			$(".ucp-cdr").prop("disabled",false).trigger("chosen:updated");;
		} else {
			$(".ucp-cdr").prop("disabled",true).trigger("chosen:updated");;
		}
	});
</script>
