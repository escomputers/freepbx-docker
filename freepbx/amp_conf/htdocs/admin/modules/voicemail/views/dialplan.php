<div class="section-title" data-for="vmdpgeneral">
	<h3><i class="fa fa-minus"></i><?php echo _("General Dialplan Settings")?></h3>
</div>
<div class="section" data-id="vmdpgeneral">
	<!--Disable Standard Prompt-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="VM_OPTS"><?php echo _("Disable Standard Prompt") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="VM_OPTS"></i>
						</div>
						<div class="col-md-9 radioset">
							<input type="radio" name="VM_OPTS" id="VM_OPTSyes" value="s" <?php echo ($settings['VM_OPTS'] == "s"?"CHECKED":"") ?>>
							<label for="VM_OPTSyes"><?php echo _("Yes");?></label>
							<input type="radio" name="VM_OPTS" id="VM_OPTSno" value="" <?php echo ($settings['VM_OPTS'] == "s"?"":"CHECKED") ?>>
							<label for="VM_OPTSno"><?php echo _("No");?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="VM_OPTS-help" class="help-block fpbx-help-block"><?php echo _("Disable the standard voicemail instructions that follow the user recorded message. These standard instructions tell the caller to leave a message after the beep. This can be individually controlled for users who have VMX locater enabled.")?></span>
			</div>
		</div>
	</div>
	<!--END Disable Standard Prompt-->
	<!--Direct Dial Mode-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="VM_DDTYPE"><?php echo _("Direct Dial Mode") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="VM_DDTYPE"></i>
						</div>
						<div class="col-md-9 radioset">
							<input type="radio" name="VM_DDTYPE" id="VM_DDTYPEu" value="u" <?php echo ($settings['VM_DDTYPE'] == "u"?"CHECKED":"") ?>>
							<label for="VM_DDTYPEu"><?php echo _("Unavailable");?></label>
							<input type="radio" name="VM_DDTYPE" id="VM_DDTYPEb" value="b" <?php echo ($settings['VM_DDTYPE'] == "b"?"CHECKED":"") ?>>
							<label for="VM_DDTYPEb"><?php echo _("Busy");?></label>
							<input type="radio" name="VM_DDTYPE" id="VM_DDTYPEs" value="s" <?php echo ($settings['VM_DDTYPE'] == "s"?"CHECKED":"") ?>>
							<label for="VM_DDTYPEs"><?php echo _("No Message");?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="VM_DDTYPE-help" class="help-block fpbx-help-block"><?php echo _("Whether to play the busy, unavailable or no message when direct dialing voicemail")?></span>
			</div>
		</div>
	</div>
	<!--END Direct Dial Mode-->
	<!--Voicemail Recording Gain-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="VM_GAIN"><?php echo _("Voicemail Recording Gain") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="VM_GAIN"></i>
						</div>
						<div class="col-md-9 radioset">
							<input type="radio" name="VM_GAIN" id="VM_GAIN0" value="" <?php echo ($settings['VM_GAIN'] == ""?"CHECKED":"") ?>>
							<label for="VM_GAIN0"><?php echo _("None");?></label>
							<input type="radio" name="VM_GAIN" id="VM_GAIN3" value="3" <?php echo ($settings['VM_GAIN'] == "3"?"CHECKED":"") ?>>
							<label for="VM_GAIN3"><?php echo _("3 db");?></label>
							<input type="radio" name="VM_GAIN" id="VM_GAIN6" value="6" <?php echo ($settings['VM_GAIN'] == "6"?"CHECKED":"") ?>>
							<label for="VM_GAIN6"><?php echo _("6 db");?></label>
							<input type="radio" name="VM_GAIN" id="VM_GAIN9" value="9" <?php echo ($settings['VM_GAIN'] == "9"?"CHECKED":"") ?>>
							<label for="VM_GAIN9"><?php echo _("9 db");?></label>
							<input type="radio" name="VM_GAIN" id="VM_GAIN12" value="12" <?php echo ($settings['VM_GAIN'] == "12"?"CHECKED":"") ?>>
							<label for="VM_GAIN12"><?php echo _("12 db");?></label>
							<input type="radio" name="VM_GAIN" id="VM_GAIN15" value="15" <?php echo ($settings['VM_GAIN'] == "15"?"CHECKED":"") ?>>
							<label for="VM_GAIN15"><?php echo _("15 db");?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="VM_GAIN-help" class="help-block fpbx-help-block"><?php echo _("The amount of gain to amplify a voicemail message when geing recorded. This is usually set when users are complaining about hard to hear messages on your system, often caused by very quiet analog lines. The gain is in Decibels which doubles for every 3 db.")?></span>
			</div>
		</div>
	</div>
	<!--END Voicemail Recording Gain-->
	<!--Operator Extension-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="OPERATOR_XTN"><?php echo _("Operator Extension") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="OPERATOR_XTN"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="OPERATOR_XTN" name="OPERATOR_XTN" value="<?php echo $settings['OPERATOR_XTN']?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="OPERATOR_XTN-help" class="help-block fpbx-help-block"><?php echo _("Default number to dial when a voicemail user 'zeros out' if enabled. This can be overriden for each extension with the VMX Locater option that is valid even when VMX Locater is not enabled. This can be any number including an external number and there is NO VALIDATION so it should be tested after configuration.")?></span>
			</div>
		</div>
	</div>
	<!--END Operator Extension-->
</div>
<div class="section-title" data-for="vmdpavmx">
	<h3><i class="fa fa-minus"></i><?php echo _("Advanced VmX Locater Settings")?></h3>
</div>
<div class="section" data-id="vmdpavmx">
	<!--Msg Timeout-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="VMX_TIMEOUT"><?php echo _("Msg Timeout") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="VMX_TIMEOUT"></i>
						</div>
						<div class="col-md-9">
							<div class="input-group">
								<input type="number" min='0' max="15" class="form-control" id="VMX_TIMEOUT" name="VMX_TIMEOUT" value="<?php echo $settings['VMX_TIMEOUT']?>">
								<span class="input-group-addon" id="vmxtolabel"><?php echo _("Second(s)")?></span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="VMX_TIMEOUT-help" class="help-block fpbx-help-block"><?php echo _("Time to wait after message has played to timeout and/or repeat the message if no entry pressed.")?></span>
			</div>
		</div>
	</div>
	<!--END Msg Timeout-->
	<!--Times to Play Message-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="VMX_REPEAT"><?php echo _("Times to Play Message") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="VMX_REPEAT"></i>
						</div>
						<div class="col-md-9">
							<div class="input-group">
								<input type="number" min='1' max='4' class="form-control" id="VMX_REPEAT" name="VMX_REPEAT" value="<?php echo $settings['VMX_REPEAT']?>">
								<span class="input-group-addon" id="vmxreplabel"><?php echo _("Attempt(s)")?></span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="VMX_REPEAT-help" class="help-block fpbx-help-block"><?php echo _("Number of times to play the recorded message if the caller does not press any options and it times out. One attempt means we won't repeat and it will be treated as a timeout. A timeout would be the normal behavior and it is fairly normal to leave this zero and just record a message that tells them to press the various options now and leave enough time in the greeting letting them know it will otherwise go to voicemail as is normal.")?></span>
			</div>
		</div>
	</div>
	<!--END Times to Play Message-->
	<!--Error Re-tries-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="VMX_LOOPS"><?php echo _("Error Re-tries") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="VMX_LOOPS"></i>
						</div>
						<div class="col-md-9">
							<div class="input-group">
								<input type="number" min="1" max="4" class="form-control" id="VMX_LOOPS" name="VMX_LOOPS" value="<?php echo $settings['VMX_LOOPS']?>">
								<span class="input-group-addon" id="vmxlooplabel"><?php echo _("Retries")?></span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="VMX_LOOPS-help" class="help-block fpbx-help-block"><?php echo _("Number of times to play invalid options and repeat the message upon receiving an undefined option. One retry means it will repeat at one time after the intial failure.")?></span>
			</div>
		</div>
	</div>
	<!--END Error Re-tries-->
	<!--Disable Standard Prompt after Max Loops-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="VMX_OPTS_LOOP"><?php echo _("Disable Standard Prompt after Max Loops") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="VMX_OPTS_LOOP"></i>
						</div>
						<div class="col-md-9 radioset">
							<input type="radio" name="VMX_OPTS_LOOP" id="VMX_OPTS_LOOPyes" value="s" <?php echo ($settings['VMX_OPTS_LOOP'] == "s"?"CHECKED":"") ?>>
							<label for="VMX_OPTS_LOOPyes"><?php echo _("Yes");?></label>
							<input type="radio" name="VMX_OPTS_LOOP" id="VMX_OPTS_LOOPno" value="" <?php echo ($settings['VMX_OPTS_LOOP'] == "s"?"":"CHECKED") ?>>
							<label for="VMX_OPTS_LOOPno"><?php echo _("No");?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="VMX_OPTS_LOOP-help" class="help-block fpbx-help-block"><?php echo _("If the Max Loops are reached and the call goes to voicemail, checking this box will disable the standard voicemail prompt prompt that follows the user's recorded greeting. This default can be overriden with a unique ..vmx/vmxopts/loops AstDB entry for the given mode (busy/unavail) and user.")?></span>
			</div>
		</div>
	</div>
	<!--END Disable Standard Prompt after Max Loops-->
	<!--Disable Standard Prompt on 'dovm' Extension-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="VMX_OPTS_DOVM"><?php echo _("Disable Standard Prompt on 'dovm' Extension") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="VMX_OPTS_DOVM"></i>
						</div>
						<div class="col-md-9 radioset">
							<input type="radio" name="VMX_OPTS_DOVM" id="VMX_OPTS_DOVMyes" value="s" <?php echo ($settings['VMX_OPTS_DOVM'] == "s"?"CHECKED":"") ?>>
							<label for="VMX_OPTS_DOVMyes"><?php echo _("Yes");?></label>
							<input type="radio" name="VMX_OPTS_DOVM" id="VMX_OPTS_DOVMno" value="" <?php echo ($settings['VMX_OPTS_DOVM'] == "s"?"":"CHECKED") ?>>
							<label for="VMX_OPTS_DOVMno"><?php echo _("No");?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="VMX_OPTS_DOVM-help" class="help-block fpbx-help-block"><?php echo _("If the special advanced extension of 'dovm' is used, checking this box will disable the standard voicemail prompt prompt that follows the user's recorded greeting. This default can be overriden with a unique ..vmx/vmxopts/dovm AstDB entry for the given mode (busy/unavail) and user.")?></span>
			</div>
		</div>
	</div>
	<!--END Disable Standard Prompt on 'dovm' Extension-->
</div>
