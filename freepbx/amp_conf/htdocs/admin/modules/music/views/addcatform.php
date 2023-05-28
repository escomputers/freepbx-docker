<?php
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2015 Sangoma Technologies.
?>
<form name="localcategory" id="localcategory" action="?display=music" method="post" class="fpbx-submit">
	<input type="hidden" name="display" value="music">
	<input type="hidden" name="action" value="addnew">
	<!--Category Name-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="category"><?php echo _("Category Name (ASCII Only)") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="category"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control name-check" id="category" name="category" value="">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="category-help" class="help-block fpbx-help-block"><?php echo _("Allows you to Set up Different Categories for music on hold.  This is useful if you would like to specify different Hold Music or Commercials. The category name will be converted to ASCII if any UTF8 characters are found")?></span>
			</div>
		</div>
	</div>
	<!--END Category Name-->
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
								<option value="files"><?php echo _("Files")?></option>
								<option value="custom"><?php echo _("Custom Application")?></option>
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
</form>
