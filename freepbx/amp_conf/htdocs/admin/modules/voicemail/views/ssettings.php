<?php if (!empty($extension)) { ?>
<h3><?php echo _("Account View Links:") ?></h3>
<ul class="nav nav-tabs">
        <li role="presentation" <?php echo $action == 'bsettings' ? ' class="active"' : ''?>><a href="config.php?display=voicemail&amp;action=bsettings&amp;ext=<?php echo $extension ?>"><?php echo _("Account Settings") ?></a></li>
        <li role="presentation" <?php echo $action == 'usage' ? ' class="active"' : ''?>><a href="config.php?display=voicemail&amp;action=usage&amp;ext=<?php echo $extension ?>"><?php echo _("Account Usage") ?></a></li>
        <li role="presentation" <?php echo $action == 'settings' ? ' class="active"' : ''?>><a href="config.php?display=voicemail&amp;action=settings&amp;ext=<?php echo $extension ?>"><?php echo _("Account Advanced Settings") ?></a></li>
</ul>

<?php }
?>
<div class="container-fluid">
	<div class="nav-container">
		<div class="scroller scroller-left"><i class="glyphicon glyphicon-chevron-left"></i></div>
		<div class="scroller scroller-right"><i class="glyphicon glyphicon-chevron-right"></i></div>
		<div class="wrapper">
			<ul class="nav nav-tabs list" role="tablist">
			<?php foreach($d as $section => $data) { ?>
				<li data-name="<?php echo $section?>" class="change-tab <?php echo ($section == "general") ? "active" : ""?>"><a href="#<?php echo $section?>" aria-controls="<?php echo $section?>" role="tab" data-toggle="tab"><?php echo $data['name']?></a></li>
			<?php } ?>
			</ul>
		</div>
	</div>
	<div class="tab-content display">
		<?php foreach($d as $section => $data) { ?>
			<div id="<?php echo $section?>" class="tab-pane <?php echo ($section == "general") ? "active" : ""?>">
				<?php if(!empty($data['helptext'])) { ?>
					<div class="alert alert-info"><?php echo $data['helptext'] ?></div>
				<?php } ?>
				<?php foreach($data['settings'] as $key => $items) { ?>
					<div class="element-container">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="<?php echo $id_prefix?>__<?php echo $key?>"><?php echo $items['description']?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="<?php echo $id_prefix?>__<?php echo $key?>"></i>
										</div>
										<div class="col-md-9">
											<?php switch($items['type']) {
														case "number": ?>
														<input type="number" class="form-control" id="<?php echo $id_prefix?>__<?php echo $key?>" name="<?php echo $id_prefix?>__<?php echo $key?>" value="<?php echo !empty($settings[$key]) ? htmlentities($settings[$key], ENT_COMPAT, 'UTF-8') : $items['default'] ?>" <?php if(!empty($items['options'])) {?>min="<?php echo $items['options'][0]?>" max="<?php echo $items['options'][1]?>"<?php } ?>>
												<?php break;
														case "text": ?>
														<input type="text" class="form-control" id="<?php echo $id_prefix?>__<?php echo $key?>" name="<?php echo $id_prefix?>__<?php echo $key?>" value="<?php echo !empty($settings[$key]) ? htmlentities($settings[$key], ENT_COMPAT, 'UTF-8') : $items['default'] ?>">
												<?php break;
														case "textbox":
															$value = !empty($settings[$key]) ?  htmlentities($settings[$key], ENT_COMPAT, 'UTF-8') : $items['default'];
															$value = str_replace(array("\\n","\\t","\\r"),array("\n","\t","\r"),$value);
												?>
														<textarea class="form-control autosize" id="<?php echo $id_prefix?>__<?php echo $key?>" name="<?php echo $id_prefix?>__<?php echo $key?>"><?php echo $value ?></textarea>
												<?php break;
														case "radio": ?>
														<div class="radioset">
															<?php foreach($items['options'] as $k => $v) { ?>
																<input type="radio" class="form-control" id="<?php echo $id_prefix?>__<?php echo $key?>_<?php echo $k?>" name="<?php echo $id_prefix?>__<?php echo $key?>" value="<?php echo $k?>" <?php echo ((!empty($settings[$key]) && $settings[$key] == $k) || ( empty($settings[$key]) && $items['default'] == $k)) ? 'checked' : '' ?>>
																<label for="<?php echo $id_prefix?>__<?php echo $key?>_<?php echo $k?>"><?php echo $v?></label>
															<?php } ?>
														</div>
												<?php break;
											} ?>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="<?php echo $id_prefix?>__<?php echo $key?>-help" class="help-block fpbx-help-block"><?php echo $items['helptext']?></span>
							</div>
						</div>
					</div>
				<?php } ?>
			</div>
		<?php } ?>
	</div>
</div>
