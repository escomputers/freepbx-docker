<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="voicemailenable"><?php echo _("Enable Voicemail Access") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="voicemailenable"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="voicemail_enable" id="voicemail_enable_yes" value="yes" <?php echo $enable ? 'checked' : ''?>>
						<label for="voicemail_enable_yes"><?php echo _("Yes")?></label>
						<input type="radio" name="voicemail_enable" id="voicemail_enable_no" value="no" <?php echo (!is_null($enable) && !$enable) ? 'checked' : ''?>>
						<label for="voicemail_enable_no"><?php echo _("No")?></label>
						<?php if($mode == "user") {?>
							<input type="radio" id="voicemail_enable_inherit" name="voicemail_enable" value='inherit' <?php echo is_null($enable) ? 'checked' : ''?>>
							<label for="voicemail_enable_inherit"><?php echo _('Inherit')?></label>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="voicemailenable-help" class="help-block fpbx-help-block"><?php echo _("Enable the voicemail Access in UCP for this user")?></span>
		</div>
	</div>
</div>
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="ucp_voicemail"><?php echo _("Allowed Voicemail")?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="ucp_voicemail"></i>
					</div>
					<div class="col-md-9">
						<select data-placeholder="Extensions" id="ucp_voicemail" class="form-control chosenmultiselect ucp-voicemail" name="ucp_voicemail[]" multiple="multiple" <?php echo (!is_null($enable) && !$enable) ? "disabled" : ""?>>
							<?php foreach($ausers as $key => $value) {?>
								<option value="<?php echo $key?>" <?php echo in_array($key,$vmassigned) ? 'selected' : '' ?>><?php echo $value?></option>
							<?php } ?>
						</select>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="ucp_voicemail-help" class="help-block fpbx-help-block"><?php echo _("These are the assigned and active extensions which will show up for this user to control and edit in UCP")?></span>
		</div>
	</div>
</div>
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="voicemailplayback"><?php echo _("Allow Voicemail Playback") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="voicemailplayback"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="voicemail_playback" class="ucp-voicemail" id="voicemail_playback_yes" value="yes" <?php echo $playback ? 'checked' : ''?>>
						<label for="voicemail_playback_yes"><?php echo _("Yes")?></label>
						<input type="radio" name="voicemail_playback" class="ucp-voicemail" id="voicemail_playback_no" value="no" <?php echo (!is_null($playback) && !$playback) ? 'checked' : ''?>>
						<label for="voicemail_playback_no"><?php echo _("No")?></label>
						<?php if($mode == "user") {?>
							<input type="radio" id="voicemail_playback_inherit" class="ucp-voicemail" name="voicemail_playback" value='inherit' <?php echo is_null($playback) ? 'checked' : ''?>>
							<label for="voicemail_playback_inherit"><?php echo _('Inherit')?></label>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="voicemailplayback-help" class="help-block fpbx-help-block"><?php echo _("Enable voicemail playback in UCP for this user")?></span>
		</div>
	</div>
</div>
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="voicemaildownload"><?php echo _("Allow Voicemail Download") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="voicemaildownload"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="voicemail_download" class="ucp-voicemail" id="voicemail_download_yes" value="yes" <?php echo $download ? 'checked' : ''?>>
						<label for="voicemail_download_yes"><?php echo _("Yes")?></label>
						<input type="radio" name="voicemail_download" class="ucp-voicemail" id="voicemail_download_no" value="no" <?php echo (!is_null($download) && !$download) ? 'checked' : ''?>>
						<label for="voicemail_download_no"><?php echo _("No")?></label>
						<?php if($mode == "user") {?>
							<input type="radio" id="voicemail_download_inherit" class="ucp-voicemail" name="voicemail_download" value='inherit' <?php echo is_null($download) ? 'checked' : ''?>>
							<label for="voicemail_download_inherit"><?php echo _('Inherit')?></label>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="voicemaildownload-help" class="help-block fpbx-help-block"><?php echo _("Enable voicemail download in UCP for this user")?></span>
		</div>
	</div>
</div>
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="voicemailsettings"><?php echo _("Allow Voicemail Settings") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="voicemailsettings"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="voicemail_settings" class="ucp-voicemail" id="voicemail_settings_yes" value="yes" <?php echo $settings ? 'checked' : ''?>>
						<label for="voicemail_settings_yes"><?php echo _("Yes")?></label>
						<input type="radio" name="voicemail_settings" class="ucp-voicemail" id="voicemail_settings_no" value="no" <?php echo (!is_null($settings) && !$settings) ? 'checked' : ''?>>
						<label for="voicemail_settings_no"><?php echo _("No")?></label>
						<?php if($mode == "user") {?>
							<input type="radio" id="voicemail_settings_inherit" class="ucp-voicemail" name="voicemail_settings" value='inherit' <?php echo is_null($settings) ? 'checked' : ''?>>
							<label for="voicemail_settings_inherit"><?php echo _('Inherit')?></label>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="voicemailsettings-help" class="help-block fpbx-help-block"><?php echo _("Enable voicemail settings in UCP for this user")?></span>
		</div>
	</div>
</div>
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="voicemailgreetings"><?php echo _("Allow Voicemail Greetings") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="voicemailgreetings"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="voicemail_greetings" class="ucp-voicemail" id="voicemail_greetings_yes" value="yes" <?php echo $greetings ? 'checked' : ''?>>
						<label for="voicemail_greetings_yes"><?php echo _("Yes")?></label>
						<input type="radio" name="voicemail_greetings" class="ucp-voicemail" id="voicemail_greetings_no" value="no" <?php echo (!is_null($greetings) && !$greetings) ? 'checked' : ''?>>
						<label for="voicemail_greetings_no"><?php echo _("No")?></label>
						<?php if($mode == "user") {?>
							<input type="radio" id="voicemail_greetings_inherit" class="ucp-voicemail" name="voicemail_greetings" value='inherit' <?php echo is_null($greetings) ? 'checked' : ''?>>
							<label for="voicemail_greetings_inherit"><?php echo _('Inherit')?></label>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="voicemailgreetings-help" class="help-block fpbx-help-block"><?php echo _("Enable voicemail greetings in UCP for this user")?></span>
		</div>
	</div>
</div>
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="vmxlocater"><?php echo _("Allow VmX Locater Settings") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="vmxlocater"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="vmxlocater" class="ucp-voicemail" id="vmxlocater_yes" value="yes" <?php echo $vmxlocater ? 'checked' : ''?>>
						<label for="vmxlocater_yes"><?php echo _("Yes")?></label>
						<input type="radio" name="vmxlocater" class="ucp-voicemail" id="vmxlocater_no" value="no" <?php echo (!is_null($vmxlocater) && !$vmxlocater) ? 'checked' : ''?>>
						<label for="vmxlocater_no"><?php echo _("No")?></label>
						<?php if($mode == "user") {?>
							<input type="radio" id="vmxlocater_inherit" class="ucp-voicemail" name="vmxlocater" value='inherit' <?php echo is_null($vmxlocater) ? 'checked' : ''?>>
							<label for="vmxlocater_inherit"><?php echo _('Inherit')?></label>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="vmxlocater-help" class="help-block fpbx-help-block"><?php echo _("Enable VmX Locater in UCP for this user")?></span>
		</div>
	</div>
</div>
<script>
	$("input[name=voicemail_enable]").change(function() {
		if($(this).val() == "yes" || $(this).val() == "inherit") {
			$(".ucp-voicemail").prop("disabled",false).trigger("chosen:updated");;
		} else {
			$(".ucp-voicemail").prop("disabled",true).trigger("chosen:updated");;
		}
	});
</script>
