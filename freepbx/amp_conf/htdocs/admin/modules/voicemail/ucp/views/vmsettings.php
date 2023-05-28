<div id="message" class="alert" style="display:none;"></div>
<form role="form">
	<div class="row">
		<div class="col-xs-12">
			<div class="form-group">
				<label for="pwd" class="help"><?php echo _('Voicemail Pin')?> <i class="fa fa-question-circle"></i></label>
				<input name="pwd" type="number" class="form-control" id="pwd" value="<?php echo $settings['pwd']?>" autocapitalize="off" autocorrect="off">
				<span class="help-block help-hidden" data-for="pwd"><?php echo _('Pin Used to Login to Voicemail. This pin can only contain numbers.')?></span>
			</div>
			<div class="form-group">
				<label for="email" class="help"><?php echo _('Email Address')?> <i class="fa fa-question-circle"></i></label>
				<input name="email" type="email" class="form-control" id="email" value="<?php echo $settings['email']?>" placeholder="user@domain.tld" autocapitalize="off" autocorrect="off">
				<span class="help-block help-hidden" data-for="email"><?php echo _('The email address that Voicemails are sent to.')?></span>
			</div>
			<div class="form-group">
				<label for="pager" class="help"><?php echo _('Pager Email Address')?> <i class="fa fa-question-circle"></i></label>
				<input name="pager" type="email" class="form-control" id="pager" value="<?php echo $settings['pager']?>" placeholder="user@domain.tld" autocapitalize="off" autocorrect="off">
				<span class="help-block help-hidden" data-for="pager"><?php echo _('Pager/mobile email address that short Voicemail notifications are sent to. Separated by |')?></span>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-xs-6">
			<div class="form-group">
				<label for="attach-h" class="help"><?php echo _('Email Attachment')?> <i class="fa fa-question-circle"></i></label><br/>
				<input type="checkbox" data-toggle="toggle" data-size="small" name="attach" id="attach" <?php echo ($settings['options']['attach'] == 'yes') ? 'checked' : ''?>>
				<span class="help-block help-hidden" data-for="attach-h"><?php echo _('Controls whether to attach the voicemail to the email')?></span>
			</div>
			<div class="form-group">
				<label for="saycid-h" class="help"><?php echo _('Play CID')?> <i class="fa fa-question-circle"></i></label><br/>
				<input type="checkbox" data-toggle="toggle" data-size="small" name="saycid" id="saycid" <?php echo ($settings['options']['saycid'] == 'yes') ? 'checked' : ''?>>
				<span class="help-block help-hidden" data-for="saycid-h"><?php echo _("Read back caller's telephone number prior to playing the incoming message, and just after announcing the date and time the message was left.")?></span>
			</div>
			<div class="form-group">
				<label for="envelope-h" class="help"><?php echo _('Play Envelope')?> <i class="fa fa-question-circle"></i></label><br/>
				<input type="checkbox" data-toggle="toggle" data-size="small" name="envelope" id="envelope" <?php echo ($settings['options']['envelope'] == 'yes') ? 'checked' : ''?>>
				<span class="help-block help-hidden" data-for="envelope-h"><?php echo _('Envelope controls whether or not the Voicemail system will play the message envelope (date/time) before playing the Voicemail message. This setting does not affect the operation of the envelope option in the advanced Voicemail menu.')?></span>
			</div>
		</div>
		<div class="col-xs-6">
			<div class="form-group">
				<label for="vmdelete-h" class="help"><?php echo _('Delete Voicemail')?> <i class="fa fa-question-circle"></i></label><br/>
				<input type="checkbox" data-toggle="toggle" data-size="small" name="vmdelete" id="vmdelete" <?php echo ($settings['options']['delete'] == 'yes') ? 'checked' : ''?>>
				<span class="help-block help-hidden" data-for="vmdelete-h"><?php echo _('If set to "yes" the message will be deleted from the Voicemailbox (after having been emailed). Provides functionality that allows a user to receive their Voicemail via email alone, rather than having the Voicemail able to be retrieved from the Webinterface or the Extension handset.  CAUTION: MUST HAVE attach Voicemail to email SET TO YES OTHERWISE YOUR MESSAGES WILL BE LOST FOREVER.')?></span>
			</div>
			<div class="form-group">
				<label for="vm-refresh-h" class="help"><?php echo _('Auto-Refresh')?> <i class="fa fa-question-circle"></i></label><br/>
				<input type="checkbox" data-toggle="toggle" data-size="small" id="vm-refresh" <?php echo !isset($_COOKIE['vm-refresh']) || !empty($_COOKIE['vm-refresh']) ? "checked" : ""?>>
				<span class="help-block help-hidden" data-for="vm-refresh-h"><?php echo _('If set to "yes" the Voicemail Pane will refresh automatically when new voicemails are retrieved')?></span>
			</div>
		</div>
	</div>
</form>
