<div class="supported-formats"><?php echo sprintf(_("Supported Formats: %s"),implode(", ",$supported['in']))?></div>
<?php $count = 1;?>
<?php foreach($short_greetings as $greeting => $name) {?>
	<div class="row">
		<div class="col-md-12">
			<div id="<?php echo $greeting?>" class="greeting-control">
				<h4><?php echo _($name)?></h4>
				<div id="freepbx_player_<?php echo $greeting?>" data-id="<?php echo $greeting?>" data-container="#jp_container_<?php echo $greeting?>" class="jp-jplayer"></div>
				<div id="jp_container_<?php echo $greeting?>" data-player="freepbx_player_<?php echo $greeting?>" data-type="<?php echo $greeting?>" class="jp-audio-freepbx <?php echo !isset($greetings[$greeting]) ? 'greet-hidden' : ''?>" draggable="true" role="application" aria-label="media player">
					<div class="jp-type-single">
						<div class="jp-gui jp-interface">
							<div class="jp-controls">
								<i class="fa fa-play jp-play"></i>
								<i class="fa fa-circle jp-record" data-id="<?php echo $greeting?>"></i>
							</div>
							<div class="jp-progress">
								<div class="jp-seek-bar progress">
									<div class="jp-current-time" role="timer" aria-label="time">&nbsp;</div>
									<div class="progress-bar progress-bar-striped active" style="width: 100%;"></div>
									<div class="jp-play-bar progress-bar"></div>
									<div class="jp-play-bar">
										<div class="jp-ball"></div>
									</div>
									<div class="jp-duration" role="timer" aria-label="duration">&nbsp;</div>
								</div>
							</div>
							<div class="jp-volume-controls">
								<i class="fa fa-volume-up jp-mute"></i>
								<i class="fa fa-volume-off jp-unmute"></i>
							</div>
						</div>
						<div class="jp-details">
							<div class="jp-title" aria-label="title" data-title="<?php echo _($name)?>"><?php echo _($name)?></div>
						</div>
						<div class="jp-no-solution">
							<span><?php echo _("Update Required")?></span>
							<?php echo sprintf(_("To play the media you will need to either update your browser to a recent version or update your %s"),'<a href="http://get.adobe.com/flashplayer/" target="_blank">Flash plugin</a>')?>
						</div>
					</div>
				</div>

				<div class="file-controls">
					<span class="btn btn-file btn-success btn-xs">
						<i class="fa fa-cloud-upload"></i> <?php echo _('Upload Greeting')?>
						<input type="file" type="file" name="files[]" multiple />
					</span>
					<button class="btn btn-danger btn-xs delete <?php echo !isset($greetings[$greeting]) ? 'greet-hidden' : ''?>" data-id="<?php echo $greeting?>"><i class="fa fa-trash-o fa-lg"></i><?php echo _('Delete')?></button>
					<button class="btn btn-danger record-greeting-btn btn-xs record" data-id="<?php echo $greeting?>"><i class="fa fa-circle"></i><?php echo _("Record Greeting")?></button>
				</div>
				<div class="recording-controls">
					<button class="btn btn-success btn-xs save" data-id="<?php echo $greeting?>"><i class="fa fa-floppy-o"></i> <?php echo _('Save Recording')?></button>
					<button class="btn btn-danger btn-xs delete" data-id="<?php echo $greeting?>"><i class="fa fa-trash-o fa-lg"></i> <?php echo _('Discard Recording')?></button>
				</div>
				<div data-type="<?php echo $greeting?>" class="filedrop hidden-xs hidden-sm">
					<div class="message" data-message="<?php echo _('Upload a New Greeting or Drag an Old Greeting Here')?>"><?php echo _('Upload a New Greeting or Drag an Old Greeting Here')?></div>
					<div class="pbar"></div>
				</div>
			</div>
		</div>
	</div>
	<?php $count++?>
<?php } ?>
