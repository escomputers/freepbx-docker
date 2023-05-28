<?php
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2015 Sangoma Technologies.
//
$engineinfo = engine_getinfo();
$version =  $engineinfo['version'];
extract($request, EXTR_SKIP);
$extdisplay = isset($account)?$account:$extdisplay;
$confC = FreePBX::Conferences();
$class1370 = version_compare($version, '13.7.0', 'ge')?'':'hidden';
$FORCEALLOWCONFRECORDING = FreePBX::Config()->get('FORCEALLOWCONFRECORDING');
if ($extdisplay != ""){
	//get details for this meetme
	$thisMeetme = $confC->getConference($extdisplay);
	$options     = $thisMeetme['options'];
	$userpin     = $thisMeetme['userpin'];
	$adminpin    = $thisMeetme['adminpin'];
	$description = $thisMeetme['description'];
	$joinmsg_id  = $thisMeetme['joinmsg_id'];
	$music       = $thisMeetme['music'];
	$users       = $thisMeetme['users'];
	$language       = $thisMeetme['language'];
	$timeout       = $thisMeetme['timeout'];
} else {
	$options     = "";
	$userpin     = "";
	$adminpin    = "";
	$description = "";
	$joinmsg_id  = "";
	$music       = "";
	$users	     = "0";
	$language		 = "";
	$timeout = 21600;
}
if ($extdisplay != ""){
	$orig_accounthtml =	'<input type="hidden" name="orig_account" value="'.$extdisplay.'">';
}
if(function_exists('recordings_list')) {
	$tresults = recordings_list();
	$jmopts = '<option value="">'._("None")."</option>";
	if (isset($tresults[0])) {
		foreach ($tresults as $tresult) {
			$jmopts .= '<option value="'.$tresult['id'].'"'.($tresult['id'] == $joinmsg_id ? ' SELECTED' : '').'>'.$tresult['displayname']."</option>\n";
		}
	}
	$jmhtml = '
		<!--Join Message-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="joinmsg_id">'._("Join Message").'</label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="joinmsg_id"></i>
							</div>
							<div class="col-md-9">
								<select class="form-control" id="joinmsg_id" name="joinmsg_id">
									'.$jmopts.'
								</select>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="joinmsg_id-help" class="help-block fpbx-help-block">'._("Message to be played to the caller before joining the conference.<br><br>To add additional recordings please use the \"System Recordings\" MENU above").'</span>
				</div>
			</div>
		</div>
		<!--END Join Message-->
	';
}else{
	$jmhtml = '<input type="hidden" name="joinmsg_id" value="'.$joinmsg_id.'">';
}
if(function_exists('music_list')) {
	$tresults = music_list();
	array_unshift($tresults,'inherit');
	$default = (isset($music) ? $music : 'inherit');
	if (isset($tresults)) {
		foreach ($tresults as $tresult) {
			$searchvalue="$tresult";
			( $tresult == 'inherit' ? $ttext = _("inherit") : $ttext = $tresult );
			( $tresult == 'default' ? $ttext = _("default") : $ttext = $tresult );
			$mohopts .= '<option value="'.$tresult.'" '.($searchvalue == $default ? 'SELECTED' : '').'>'.$ttext;
		}
	}
	$mohhtml = '
		<!--Music on Hold Class-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="music">'. _("Music on Hold Class").'</label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="music"></i>
							</div>
							<div class="col-md-9">
								<select class="form-control" id="music" name="music">
									'.$mohopts.'
								</select>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="music-help" class="help-block fpbx-help-block">'. _("Music (or Commercial) played to the caller while they wait in line for the conference to start. Choose \"inherit\" if you want the MoH class to be what is currently selected, such as by the inbound route.<br><br>  This music is defined in the \"Music on Hold\" above.").'</span>
				</div>
			</div>
		</div>
		<!--END Music on Hold Class-->
	';
}

$usage_list = framework_display_destination_usage(conferences_getdest($extdisplay));
if (!empty($conflict_url)) {
	echo "<h5>"._("Conflicting Extensions")."</h5>";
	echo implode('<br .>',$conflict_url);
}
$module_hook = \moduleHook::create();

?>
<form autocomplete="off" name="editMM" id="editMM" class="fpbx-submit" action="?display=conferences" method="post" onsubmit="return checkConf();" data-fpbx-delete="?display=conferences&amp;action=delete&amp;extdisplay=<?php echo $extdisplay ?>">
<input type="hidden" name="action" id="action" value="<?php echo ($extdisplay != '' ? 'edit' : 'add') ?>">
<input type="hidden" name="options" id="options" value="<?php echo $options; ?>">
<?php echo $orig_accounthtml ?>

<!--Conference Number-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="account"><?php echo _("Conference Number") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="account"></i>
					</div>
					<div class="col-md-9">
						<input type="text" class="form-control" id="account" name="account" value="<?php echo $extdisplay ?>">
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="account-help" class="help-block fpbx-help-block"><?php echo _("Use this number to dial into the conference.")?></span>
		</div>
	</div>
</div>
<!--END Conference Number-->
<!--Conference Name-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="name"><?php echo _("Conference Name") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="name"></i>
					</div>
					<div class="col-md-9">
						<input type="text" class="form-control maxlen" maxlength="50" id="name" name="name" value="<?php echo $description; ?>">
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="name-help" class="help-block fpbx-help-block"><?php echo _("Give this conference a brief name to help you identify it.")?></span>
		</div>
	</div>
</div>
<!--END Conference Name-->
<!--User PIN-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="userpin"><?php echo _("User PIN") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="userpin"></i>
					</div>
					<div class="col-md-9">
						<input pattern="^[0-9]+$" type="text" class="form-control" id="userpin" name="userpin" value="<?php echo $userpin; ?>">
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="userpin-help" class="help-block fpbx-help-block"><?php echo _("You can require callers to enter a password before they can enter this conference.<br><br>This setting is optional.<br><br>If either PIN is entered, the user will be prompted to enter a PIN.<br> This pin should be different than the Admin pin")?></span>
		</div>
	</div>
</div>
<!--END User PIN-->
<!--Admin PIN-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="adminpin"><?php echo _("Admin PIN") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="adminpin"></i>
					</div>
					<div class="col-md-9">
						<input pattern="^[0-9]+$" type="text" class="form-control" id="adminpin" name="adminpin" value="<?php echo $adminpin; ?>">
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="adminpin-help" class="help-block fpbx-help-block"><?php echo _("Enter a PIN number for the admin user.<br><br>This setting is optional unless the 'leader wait' option is in use, then this PIN will identify the leader.<br>This pin should be different than the user pin.")?></span>
		</div>
	</div>
</div>
<!--END Admin PIN-->
<!--language-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="language"><?php echo _("Language")?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="language"></i>
					</div>
					<div class="col-md-9">
						<?php if(\FreePBX::Modules()->checkStatus("soundlang")) { ?>
							<select class="form-control" id="language" name="language">
								<option value=""><?php echo _("Inherit")?></option>
								<?php foreach(\FreePBX::Soundlang()->getLanguages() as $key => $value) {
										$selected = ($language == $key)?'SELECTED':'';
								?>
									<option value="<?php echo $key?>" <?php echo $selected;?>><?php echo $value?></option>
								<?php } ?>
							</select>
						<?php } else { ?>
							<input class="form-control" id="language" name="language" value="<?php echo $language?>">
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="language-help" class="help-block fpbx-help-block"><?php echo _("The language for the conference. If set to inherit or blank the language will be inherited from the first person who joins the conference esentially making the language of this conference dynamic. After the first person has joined the language can not be changed until all users have left the conference. If you set a value here the langauge will be forced irregardless of what language users have set")?></span>
		</div>
	</div>
</div>
<!--END language-->
<?php echo $jmhtml ?>
<!--Leader Wait-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="opt_w"><?php echo _("Leader Wait") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="opt_w"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="opt_w" id="opt_wyes" value="w" <?php echo (strpos($options, "w") === false ?"":"CHECKED") ?>>
						<label for="opt_wyes"><?php echo _("Yes");?></label>
						<input type="radio" name="opt_w" id="opt_wno" value="NO" <?php echo (strpos($options, "w") === false ?"CHECKED":"") ?>>
						<label for="opt_wno"><?php echo _("No");?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="opt_w-help" class="help-block fpbx-help-block"><?php echo _("Wait until the conference leader (admin user) arrives before starting the conference")?></span>
		</div>
	</div>
</div>
<!--END Leader Wait-->
<!--Leader Wait-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="opt_x"><?php echo _("Leader Leave") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="opt_x"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="opt_x" id="opt_xyes" value="x" <?php echo (strpos($options, "x") === false ?"":"CHECKED") ?>>
						<label for="opt_xyes"><?php echo _("Yes");?></label>
						<input type="radio" name="opt_x" id="opt_xno" value="NO" <?php echo (strpos($options, "x") === false ?"CHECKED":"") ?>>
						<label for="opt_xno"><?php echo _("No");?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="opt_x-help" class="help-block fpbx-help-block"><?php echo _("When the conference leader (admin user) leaves all users will be kicked from the conference")?></span>
		</div>
	</div>
</div>
<!--END Leader Wait-->
<!--Talker Optimization-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="opt_o"><?php echo _("Talker Optimization") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="opt_o"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="opt_o" id="opt_oyes" value="o" <?php echo (strpos($options, "o") === false ? "":"CHECKED") ?>>
						<label for="opt_oyes"><?php echo _("Yes");?></label>
						<input type="radio" name="opt_o" id="opt_ono" value="NO" <?php echo (strpos($options, "o") === false ? "CHECKED":"") ?>>
						<label for="opt_ono"><?php echo _("No");?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="opt_o-help" class="help-block fpbx-help-block"><?php echo _("Turns on talker optimization. With talker optimization, Asterisk treats talkers who are not speaking as being muted, meaning that no encoding is done on transmission and that received audio that is not registered as talking is omitted, causing no buildup in background noise.")?></span>
		</div>
	</div>
</div>
<!--END Talker Optimization-->
<!--Talker Detection-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="opt_T"><?php echo _("Talker Detection") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="opt_T"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="opt_T" id="opt_Tyes" value="T" <?php echo (strpos($options, "T") === false ? "":"CHECKED") ?>>
						<label for="opt_Tyes"><?php echo _("Yes");?></label>
						<input type="radio" name="opt_T" id="opt_Tno" value="NO" <?php echo (strpos($options, "T") === false ? "CHECKED":"") ?>>
						<label for="opt_Tno"><?php echo _("No");?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="opt_T-help" class="help-block fpbx-help-block"><?php echo _("Sets talker detection. Asterisk will send events on the manager interface identifying the channel that is talking. The talker will also be identified on the output of the conference list CLI command. Note: If Conferences Pro is installed and licensed this will always be enabled")?></span>
		</div>
	</div>
</div>
<!--END Talker Detection-->
<!--Quiet Mode-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="opt_q"><?php echo _("Quiet Mode") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="opt_q"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="opt_q" id="opt_qyes" value="q" <?php echo (strpos($options, "q") === false ? "":"CHECKED") ?>>
						<label for="opt_qyes"><?php echo _("Yes");?></label>
						<input type="radio" name="opt_q" id="opt_qno" value="NO" <?php echo (strpos($options, "q") === false ? "CHECKED":"") ?>>
						<label for="opt_qno"><?php echo _("No");?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="opt_q-help" class="help-block fpbx-help-block"><?php echo _("Quiet mode (do not play enter/leave sounds)")?></span>
		</div>
	</div>
</div>
<!--END Quiet Mode-->
<!--User Count-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="opt_c"><?php echo _("User Count") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="opt_c"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="opt_c" id="opt_cyes" value="c" <?php echo (strpos($options, "c") === false ? "":"CHECKED") ?>>
						<label for="opt_cyes"><?php echo _("Yes");?></label>
						<input type="radio" name="opt_c" id="opt_cno" value="NO" <?php echo (strpos($options, "c") === false ? "CHECKED":"") ?>>
						<label for="opt_cno"><?php echo _("No");?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="opt_c-help" class="help-block fpbx-help-block"><?php echo _("Announce user(s) count on joining conference")?></span>
		</div>
	</div>
</div>
<!--END User Count-->
<!--User join/leave-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="opt_I"><?php echo _("User join/leave") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="opt_I"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="opt_I" id="opt_Iyes" value="I" <?php echo (strpos($options, "I") === false ? "":"CHECKED") ?>>
						<label for="opt_Iyes"><?php echo _("Yes");?></label>
						<input type="radio" name="opt_I" id="opt_Ino" value="NO" <?php echo (strpos($options, "I") === false ? "CHECKED":"") ?>>
						<label for="opt_Ino"><?php echo _("No");?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="opt_I-help" class="help-block fpbx-help-block"><?php echo _("Announce user join/leave. If enabled this will require the user to record their name before joining the conference")?></span>
		</div>
	</div>
</div>
<!--END User join/leave-->
<!--Music on Hold-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="opt_MH"><?php echo _("Music on Hold") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="opt_MH"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="opt_MH" id="opt_MHyes" value="M" <?php echo (strpos($options, "M") === false ? "":"CHECKED") ?>>
						<label for="opt_MHyes"><?php echo _("Yes");?></label>
						<input type="radio" name="opt_MH" id="opt_MHno" value="NO" <?php echo (strpos($options, "M") === false ? "CHECKED":"")?>>
						<label for="opt_MHno"><?php echo _("No");?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="opt_MH-help" class="help-block fpbx-help-block"><?php echo _("Enable Music On Hold when the conference has a single caller")?></span>
		</div>
	</div>
</div>
<!--END Music on Hold-->
<?php echo $mohhtml //If function_exists('music_list')?>
<!--Allow Menu-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="opt_s"><?php echo _("Allow Menu") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="opt_s"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="opt_s" id="opt_syes" value="s" <?php echo (strpos($options, "s") === false ? "":"CHECKED") ?>>
						<label for="opt_syes"><?php echo _("Yes");?></label>
						<input type="radio" name="opt_s" id="opt_sno" value="NO" <?php echo (strpos($options, "s") === false ? "CHECKED":"") ?>>
						<label for="opt_sno"><?php echo _("No");?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="opt_s-help" class="help-block fpbx-help-block"><?php echo _("Present Menu (user or admin) when '*' is received ('send' to menu)")?></span>
		</div>
	</div>
</div>
<!--END Allow Menu-->
<!--Record Conference-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="opt_r"><?php echo _("Record Conference") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="opt_r"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="opt_r" id="opt_ryes" value="r" <?php echo (strpos($options, "r") === false ? "":"CHECKED") ?> <?php echo $FORCEALLOWCONFRECORDING || version_compare($version,"14.0","ge") ? '' : 'disabled'?>>
						<label for="opt_ryes"><?php echo _("Yes");?></label>
						<input type="radio" name="opt_r" id="opt_rno" value="NO" <?php echo (strpos($options, "r") === false ? "CHECKED":"") ?> <?php echo $FORCEALLOWCONFRECORDING || version_compare($version,"14.0","ge") ? '' : 'disabled'?>>
						<label for="opt_rno"><?php echo _("No");?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="opt_r-help" class="help-block fpbx-help-block"><?php echo _("Record the conference call")?>. <?php echo sprintf(_('Record the conference call. Note: This is broken when using %s or lower, and is therefore disabled. Enable "Force allow conference recording" under Advanced settings to override this. The Recording will not be available in either the CDR nor in Call Recordings and has to be downloaded by logging into the backend.'),'Asterisk 13')?></span>
		</div>
	</div>
</div>
<!--END Record Conference-->
<!--Maximum Participants-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="users"><?php echo _("Maximum Participants") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="users"></i>
					</div>
					<div class="col-md-9">
						<input type="number" placeholder="<?php echo _('No Limit')?>" class="form-control" id="users" name="users" value="<?php echo $users ?>">
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="users-help" class="help-block fpbx-help-block"><?php echo _("Maximum Number of users allowed to join this conference.")." "._("Please note depending on hardware and settings a higher limit may cause call quality issues.")?></span>
		</div>
	</div>
</div>
<!--END Maximum Participants-->
<!--Mute on Join-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="opt_m"><?php echo _("Mute on Join") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="opt_m"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="opt_m" id="opt_myes" value="m" <?php echo (strpos($options, "m") === false ? "":"CHECKED") ?>>
						<label for="opt_myes"><?php echo _("Yes");?></label>
						<input type="radio" name="opt_m" id="opt_mno" value="NO" <?php echo (strpos($options, "m") === false ? "CHECKED":"") ?>>
						<label for="opt_mno"><?php echo _("No");?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="opt_m-help" class="help-block fpbx-help-block"><?php echo _("Mute everyone when they initially join the conference. Please note that if you do not have 'Leader Wait' set to yes you must have 'Allow Menu' set to Yes to unmute yourself")?></span>
		</div>
	</div>
</div>
<!--END Mute on Join-->
<!--Member Timeout-->
<div class="element-container <?php echo $class1370?>">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="timeout"><?php echo _("Member Timeout") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="timeout"></i>
					</div>
					<div class="col-md-9">
						<input type="number" min = '0' class="form-control" id="timeout" name="timeout" value="<?php echo isset($timeout)?$timeout:'21600'?>">
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="timeout-help" class="help-block fpbx-help-block"><?php echo _("This specifies the number of seconds that the participant may stay in the conference before being automatically ejected. 0 = disabled, default is 21600 (6 hours)")?></span>
		</div>
	</div>
</div>
<!--END Member Timeout-->
<?php
echo $module_hook->hookHtml;
?>
</form>
