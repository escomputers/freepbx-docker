<div class="container-fluid">
	<h1><?php echo _('Log File Settings')?></h1>
	<div class = "display full-border">
		<div class="row">
			<div class="col-md-12">
				<div class="fpbx-container">
					<div class="display full-border">
						<ul class="nav nav-tabs" role="tablist">
							<li data-name="logfiles_general" class="change-tab active">
								<a href="#logfiles_general" aria-controls="logfiles_general" role="tab" data-toggle="tab"><?php echo _("General Settings")?></a>
							</li>
							<li data-name="logfiles_logfiles" class="change-tab">
								<a href="#logfiles_logfiles" aria-controls="logfiles_logfiles" role="tab" data-toggle="tab"><?php echo _("Log Files")?></a>
							</li>
						</ul>
						<div class="tab-content display">
							<div id="logfiles_general" class="tab-pane active">
								<?php echo load_view(__DIR__."/view.settings.general.php", array("lf" => $lf)); ?>
							</div>
							<div id="logfiles_logfiles" class="tab-pane">
								<?php echo load_view(__DIR__."/view.settings.logfiles.php", array("lf" => $lf)); ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script type="text/javascript" src="modules/logfiles/assets/js/views/global.js"></script>
<script type="text/javascript" src="modules/logfiles/assets/js/views/settings.js"></script>
