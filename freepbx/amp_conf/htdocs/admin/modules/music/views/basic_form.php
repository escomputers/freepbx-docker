<form enctype="multipart/form-data" name="edit" id="moheditform" action="?display=music" method="POST" class="fpbx-submit" data-fpbx-delete="?display=music&amp;action=delete&amp;id=<?php echo $data['id']?>">
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
	<input type="hidden" id="wav" name="codec[]" class="codec" value="wav">
	<input type="hidden" id="id" name="id" value="<?php echo $data['id']?>">
	<input type="hidden" id="action" name="action" value="editold">
	<input type="hidden" id="type" name="type" value="files">
	<input type="hidden" id="application" name="application" value="">
	<input type="hidden" id="format" name="format" value="">
</form>
<script>var display_mode = 'basic';var files = <?php echo json_encode($files)?>; var supportedRegExp = "<?php echo implode("|",array_keys($supported['in']))?>"; var supportedHTML5 = "<?php echo implode(",",FreePBX::Media()->getSupportedHTML5Formats())?>"</script>
