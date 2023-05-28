<?php if($processed) {?>
	<?php echo $repo_select?>
	<br/>
	<?php if (!empty($res) && is_array($res)) { ?>
		<div class="error">
			<p>
				<?php echo sprintf(_('The following error(s) occurred processing the uploaded file: %s'),
		     		'<ul><li>'.implode('</li><li>',$res).'</li></ul>');?>
				<?php sprintf(_('You should fix the problem or select another file and %s.'),
		     		"<a href='config.php?display=modules'>"._("try again")."</a>");?>
			</p>
		</div>
		<?php } else { ?>
			<p>
				<?php echo sprintf(_("Module uploaded successfully. You need to enable the module using %s to make it available."),
		     "<a href='config.php?display=modules'>"._("local module administration")."</a>");?>
		 	</p>
	<?php } ?>
<?php } else { ?>
	<p><?php echo _('You can upload a tar gzip file containing a FreePBX module from your local system. If a module with the same name already exists, it will be overwritten.')?></p>
	<?php echo $repo_select?>
	<br/>
	<div class="panel panel-default" style="width: 50%;">
		<div class="panel-body">
			<label>Type:
				<select id="local-type">
					<option value="download"><?php echo _('Download (From Web)')?></option>
					<option value="upload"><?php echo _('Upload (From Hard Disk)')?></option>
				</select>
			</label>
			<br>
			<br>
			<form id="modulesGUI-upload" name="modulesGUI-upload" action="config.php" method="post" enctype="multipart/form-data">
				<input type="hidden" name="display" value="modules" />
				<input type="hidden" name="action" value="upload" />
				<span id="download-group">
					<label style="text-decoration:underline"><a href=# class="info"><?php echo _("Download Remote Module")?><span><?php echo _("Typically the direct address of a module tarball where FreePBX will attempt to download remotely and upload locally")?></span></a><br/><input type="text" size="50" name="remotemod" placeholder="http://<path>/<to>/<tarball>" /></label>
					<input id="download" type="submit" value="<?php echo _('Download (From Web)')?>" name="download" />
				</span>
				<span id="upload-group" style="display:none;">
					<label style="text-decoration:underline"><a href=# class="info"><?php echo _("Upload Local Module")?><span><?php echo _("Locally chosen FreePBX module from your system")?></span></a><input type="file" name="uploadmod" /></label>
					<input id="upload" type="submit" value="<?php echo _('Upload (From Hard Disk)')?>" name="upload" />
				</span>
			</form>
		</div>
	</div>
<?php } ?>
