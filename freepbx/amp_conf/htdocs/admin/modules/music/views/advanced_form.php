<form enctype="multipart/form-data" name="edit" id="moheditform" action="?display=music" method="POST" class="fpbx-submit" data-fpbx-delete="?display=music&amp;action=delete&amp;id=<?php echo $data['id']?>">
	<input type="hidden" name="id" value="<?php echo $data['id']?>">
	<input type="hidden" name="action" value="editold">
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="type"><?php echo _("Type") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="type"></i>
						</div>
						<div class="col-md-9">
							<select name="type" id="type" class="form-control">
								<option value="files" <?php echo($data['type'] == "files") ? "selected" : ""?>><?php echo _("Files")?></option>
								<option value="custom" <?php echo($data['type'] == "custom") ? "selected" : ""?>><?php echo _("Custom Application")?></option>
							</select>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="type-help" class="help-block fpbx-help-block"><?php echo _('Type of Music on Hold. If set to "Files" then this category will play the files listed below. If set to "Custom Application" then this category will stream music from the set application')?></span>
			</div>
		</div>
	</div>
	<div id="application-container" class="<?php echo($data['type'] == "files") ? "hidden" : ""?>">
		<!--Application-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="application"><?php echo _("Application") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="application"></i>
							</div>
							<div class="col-md-9">
								<input type="text" class="form-control" id="application" name="application" value="<?php echo !empty($data['application']) ? $data['application'] : ""?>">
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="application-help" class="help-block fpbx-help-block"><?php echo _('This is the "application=" line used to provide the streaming details to Asterisk. See information on musiconhold.conf configuration for different audio and Internet streaming source options.')?></span>
				</div>
			</div>
		</div>
		<!--END Application-->
		<!--Optional Format-->
		<!--This should probably be a generated select-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="format"><?php echo _("Optional Format") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="format"></i>
							</div>
							<div class="col-md-9">
								<input type="text" class="form-control" id="format" name="format" value="<?php echo !empty($data['format']) ? $data['format'] : ""?>">
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="format-help" class="help-block fpbx-help-block"><?php echo _('Optional value for "format=" line used to provide the format to Asterisk. This should be a format understood by Asterisk such as ulaw, and is specific to the streaming application you are using. See information on musiconhold.conf configuration for different audio and Internet streaming source options.')?></span>
				</div>
			</div>
		</div>
		<!--END Optional Format-->
	</div>
	<div id="files-container" class="<?php echo($data['type'] == "custom") ? "hidden" : ""?>">
		<!--Enable Random Play-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="erand"><?php echo _("Enable Random Play") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="erand"></i>
							</div>
							<div class="col-md-9 radioset">
								<input type="radio" name="erand" id="erandyes" value="yes" <?php echo ($data['random']?"CHECKED":"") ?>>
								<label for="erandyes"><?php echo _("Yes");?></label>
								<input type="radio" name="erand" id="erandno" value="no" <?php echo ($data['random']?"":"CHECKED") ?>>
								<label for="erandno"><?php echo _("No");?></label>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="erand-help" class="help-block fpbx-help-block"><?php echo _("Enable random playback of music for this category. If disabled music will play in alphabetical order")?></span>
				</div>
			</div>
		</div>
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="fileupload"><?php echo _("Upload Recording")?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="fileupload"></i>
							</div>
							<div class="col-md-9">
								<span class="btn btn-default btn-file">
									<?php echo _("Browse")?>
									<input id="fileupload" type="file" class="form-control" name="files[]" data-url="ajax.php?module=music&amp;command=upload&amp;category=<?php echo $data['category']?>" class="form-control" multiple>
								</span>
								<span class="filename"></span>
								<div id="upload-progress" class="progress">
									<div class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 0%;"></div>
								</div>
								<div id="dropzone">
									<div class="message"><?php echo _("Drop Multiple Files or Archives Here")?></div>
								</div>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-12">
							<span id="fileupload-help" class="help-block fpbx-help-block"><?php echo sprintf(_("Upload files from your local system. Supported upload formats are: %s. This includes archives (that include multiple files) and multiple files"),"<i><strong>".implode(", ",$supported['in'])."</strong></i>")?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!--END Enable Random Play-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="convert"><?php echo _("Convert Upload/Files To")?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="convert"></i>
							</div>
							<div class="col-md-9 text-center">
								<span class="radioset">
									<?php $c=0;foreach($convertto as $k => $v) { ?>
										<?php if(($c % 5) == 0 && $c != 0) { ?></span></br><span class="radioset"><?php } ?>
										<input type="checkbox" id="<?php echo $k?>" name="codec[]" class="codec" value="<?php echo $k?>" <?php echo ($k == 'wav') ? 'CHECKED' : ''?>>
										<label for="<?php echo $k?>"><?php echo $v?></label>
									<?php $c++; } ?>
								</span>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-12">
							<span id="convert-help" class="help-block fpbx-help-block"><?php echo _("Check all file formats you would like the music in this category to be encoded into. This applied to uploaded music and any music currently on the system. It will not overwrite any formats that have been previously processed.")?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
		<table id="musicgrid" data-escape="true" data-url="ajax.php?module=music&amp;command=getJSON&amp;jdata=musiclist&amp;id=<?php echo $data['id']?>" data-cache="false"  data-toggle="table" class="table table-striped">
			<thead>
				<tr>
					<th data-field="name" data-sortable="true" class="col-md-6"><?php echo _("File")?></th>
					<th data-field="formats" data-formatter="formatFormatter" data-sortable="true" class="col-md-1"><?php echo _("Formats")?></th>
					<th data-field="play" data-formatter="playFormatter" class="col-sm-4"><?php echo _("Play") ?></th>
					<th data-field="link" data-formatter="musicFormat" class="col-md-1"><?php echo _("Action")?></th>
				</tr>
			</thead>
		</table>
	</div>
</form>
<script>var display_mode = 'advanced';var files = <?php echo json_encode($files)?>; var supportedRegExp = "<?php echo implode("|",array_keys($supported['in']))?>"; var supportedHTML5 = "<?php echo implode(",",FreePBX::Media()->getSupportedHTML5Formats())?>"</script>
