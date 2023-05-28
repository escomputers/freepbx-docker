<br/>
<div class="row">
	<div class="col-sm-6">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title"><?php echo _("Number of Accounts")?></h3>
			</div>
			<div class="panel-body">
				<table class="table table-striped">
					<tr><td><?php echo _("Activated")?></td><td><?php echo $acts_act ?></td></tr>
					<tr><td><?php echo _("Unactivated")?></td><td><?php echo $acts_unact ?></td></tr>
					<tr><td><?php echo _("Disabled")?></td><td><?php echo $disabled_count ?></td></tr>
					<tr><td><?php echo _("Total")?></td><td><?php echo $acts_total?></td></tr>
				</table>
			</div>
		</div>
	</div>
	<div class="col-sm-6">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title"><?php echo _("Storage Usage")?></h3>
			</div>
			<div class="panel-body">
				<div class="well"><?php echo _("Disk space currently in use by Voicemail data")?></div>
				<table class="table"><tr><td><b><?php echo _("Total")?></b></td><td><?php echo $storage?$storage:_("Unknown")?></td></tr></table>
			</div>
		</div>
	</div>
</div>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h3 class="panel-title"><?php echo _("General Usage")?></h3>
		</div>
		<br/>
		<div class="alert alert-danger"><strong><?php echo _("Warning: the actions below are global and will affect ALL users.")?></strong></div>
		<div class="panel-body">
			<!--Number of Messages-->
			<div class="element-container">
				<div class="row">
					<div class="col-md-12">
						<div class="row">
							<div class="form-group">
								<div class="col-md-3">
									<label class="control-label" for="del_msgs"><?php echo _("Number of Messages") ?></label>
									<i class="fa fa-question-circle fpbx-help-icon" data-for="del_msgs"></i>
								</div>
								<div class="col-md-9">
									<table class="table">
										<tr><td><b><?php echo _("Messages in inboxes")?></b></td><td><?php echo $msg_in?></td></tr>
										<tr><td><b><?php echo _("Messages in other folders")?></b></td><td><?php echo $msg_other?></td></tr>
										<tr><td><b><?php echo _("Total")?></b></td><td><?php echo $msg_total?></td></tr>
									</table>
									<span class="radioset">
									<b><?php echo _("Delete:")?>&nbsp;</b>
									<input type="radio" name="del_msgs" id="del_msgsyes" value="true">
									<label for="del_msgsyes"><?php echo _("Yes");?></label>
									<input type="radio" name="del_msgs" id="del_msgsno" value="" CHECKED>
									<label for="del_msgsno"><?php echo _("No");?></label>
									</span>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12">
						<span id="del_msgs-help" class="help-block fpbx-help-block"><?php echo _("Remove all messages")?></span>
					</div>
				</div>
			</div>
			<!--END Number of Messages-->
			<!--Recorded Names-->
			<div class="element-container">
				<div class="row">
					<div class="col-md-12">
						<div class="row">
							<div class="form-group">
								<div class="col-md-3">
									<label class="control-label" for="del_names"><?php echo _("Recorded Names") ?></label>
									<i class="fa fa-question-circle fpbx-help-icon" data-for="del_names"></i>
								</div>
								<div class="col-md-9">
									<table class="table"><tr><td><b><?php echo _("Total")?></b></td><td><?php echo $name?></td></tr></table>
									<span class="radioset">
									<b><?php echo _("Delete:")?>&nbsp;</b>
									<input type="radio" name="del_names" id="del_namesyes" value="true">
									<label for="del_namesyes"><?php echo _("Yes");?></label>
									<input type="radio" name="del_names" id="del_namesno" value="" CHECKED>
									<label for="del_namesno"><?php echo _("No");?></label>
									</span>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12">
						<span id="del_names-help" class="help-block fpbx-help-block"><?php echo _("Delete all Recorded Names")?></span>
					</div>
				</div>
			</div>
			<!--END Recorded Names-->
			<!--Unavailable Greetings-->
			<div class="element-container">
				<div class="row">
					<div class="col-md-12">
						<div class="row">
							<div class="form-group">
								<div class="col-md-3">
									<label class="control-label" for="del_unavail"><?php echo _("Unavailable Greetings") ?></label>
									<i class="fa fa-question-circle fpbx-help-icon" data-for="del_unavail"></i>
								</div>
								<div class="col-md-9">
									<table class="table"><tr><td><b><?php echo _("Total")?></b></td><td><?php echo $unavail?></td></tr></table>
									<span class="radioset">
									<b><?php echo _("Delete:")?>&nbsp;</b>
									<input type="radio" name="del_unavail" id="del_unavailyes" value="true">
									<label for="del_unavailyes"><?php echo _("Yes");?></label>
									<input type="radio" name="del_unavail" id="del_unavailno" value="" CHECKED>
									<label for="del_unavailno"><?php echo _("No");?></label>
									</span>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12">
						<span id="del_unavail-help" class="help-block fpbx-help-block"><?php echo _("Delete All Unavailible Greetings")?></span>
					</div>
				</div>
			</div>
			<!--END Unavailable Greetings-->
			<!--Busy Greetings-->
			<div class="element-container">
				<div class="row">
					<div class="col-md-12">
						<div class="row">
							<div class="form-group">
								<div class="col-md-3">
									<label class="control-label" for="del_busy"><?php echo _("Busy Greetings") ?></label>
									<i class="fa fa-question-circle fpbx-help-icon" data-for="del_busy"></i>
								</div>
								<div class="col-md-9">
									<table class="table"><tr><td><b><?php echo _("Total")?></b></td><td><?php echo $busy?></td></tr></table>
									<span class="radioset">
									<b><?php echo _("Delete:")?>&nbsp;</b>
									<input type="radio" name="del_busy" id="del_busyyes" value="true">
									<label for="del_busyyes"><?php echo _("Yes");?></label>
									<input type="radio" name="del_busy" id="del_busyno" value="" CHECKED>
									<label for="del_busyno"><?php echo _("No");?></label>
									</span>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12">
						<span id="del_busy-help" class="help-block fpbx-help-block"><?php echo _("Delete all Busy Messages")?></span>
					</div>
				</div>
			</div>
			<!--END Busy Greetings-->
			<!--Temporary Greetings-->
			<div class="element-container">
				<div class="row">
					<div class="col-md-12">
						<div class="row">
							<div class="form-group">
								<div class="col-md-3">
									<label class="control-label" for="del_temp"><?php echo _("Temporary Greetings") ?></label>
									<i class="fa fa-question-circle fpbx-help-icon" data-for="del_temp"></i>
								</div>
								<div class="col-md-9">
									<table class="table"><tr><td><b><?php echo _("Total")?></b></td><td><?php echo $temp?></td></tr></table>
									<span class="radioset">
									<b><?php echo _("Delete:")?>&nbsp;</b>
									<input type="radio" name="del_temp" id="del_tempyes" value="true">
									<label for="del_tempyes"><?php echo _("Yes");?></label>
									<input type="radio" name="del_temp" id="del_tempno" value="" CHECKED>
									<label for="del_tempno"><?php echo _("No");?></label>
									</span>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12">
						<span id="del_temp-help" class="help-block fpbx-help-block"><?php echo _("Delete All Temporary Greetings")?></span>
					</div>
				</div>
			</div>
			<!--END Temporary Greetings-->
			<!--Abandoned Greetings-->
			<div class="element-container">
				<div class="row">
					<div class="col-md-12">
						<div class="row">
							<div class="form-group">
								<div class="col-md-3">
									<label class="control-label" for="del_abandoned"><?php echo _("Abandoned Greetings") ?></label>
									<i class="fa fa-question-circle fpbx-help-icon" data-for="del_abandoned"></i>
								</div>
								<div class="col-md-9">
									<table class="table"><tr><td><b><?php echo _("Total")?></b></td><td><?php echo $abandoned?></td></tr></table>
									<span class="radioset">
									<b><?php echo _("Delete:")?>&nbsp;</b>
									<input type="radio" name="del_abandoned" id="del_abandonedyes" value="true">
									<label for="del_abandonedyes"><?php echo _("Yes");?></label>
									<input type="radio" name="del_abandoned" id="del_abandonedno" value="" CHECKED>
									<label for="del_abandonedno"><?php echo _("No");?></label>
									</span>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12">
						<span id="del_abandoned-help" class="help-block fpbx-help-block"><?php echo _("Remove all abandoned greetings (> 1 day old).</br>  Such greetings were recorded by the user but were NOT accepted, so the sound file remains on disk but is not used as a greeting.")?></span>
					</div>
				</div>
			</div>
			<!--END Abandoned Greetings-->
	</div>
</div>
<input type="hidden" name="action" id="action" value="Submit">
<script>

$("[name^='del_']").change(function(){
	if($(this).val() == 'true'){
		alert(_("This will affect all mailboxes on the system."));
	}
});
</script>
