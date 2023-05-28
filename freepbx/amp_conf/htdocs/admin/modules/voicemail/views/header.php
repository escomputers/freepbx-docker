<div class="container-fluid">
	<h1><?php echo _('Voicemail')?></h1>
		<div class="row">
			<div class="col-sm-9">
				<div class="fpbx-container">
					<div class="display no-border">
						<div class="tabpanel">
							<ul class="nav nav-tabs">
								<li role="presentation" <?php echo ($action == 'usage')? 'class="active"':''?>><a href="config.php?display=voicemail&amp;action=usage"><?php echo _("Usage")?></a></li>
								<li role="presentation" <?php echo ($action == 'settings')? 'class="active"':''?>><a href="config.php?display=voicemail&amp;action=settings"><?php echo _("Settings")?></a></li>
								<li role="presentation" <?php echo ($action == 'dialplan')? 'class="active"':''?>><a href="config.php?display=voicemail&amp;action=dialplan"><?php echo _("Dialplan Behavior")?></a></li>
								<li role="presentation" <?php echo ($action == 'tz')? 'class="active"':''?>><a href="config.php?display=voicemail&amp;action=tz"><?php echo _("Timezone Definitions")?></a></li>
							</ul>
							<div class="tab-content">
								<div id="<?php echo $action?>" class="tab-pane display active">
									<form class="fpbx-submit" name="frm_voicemail" id="frm_voicemail" action="" method="post" data-fpbx-delete="" role="form">
										<input type='hidden' name='type' id='type' value='<?php echo $type ?>' />
										<input type='hidden' name='display' id='display' value='<?php echo $display ?>' />
										<input type='hidden' name='ext' id='ext' value='<?php echo $extension ?>' />
										<input type='hidden' name='page_type' id='page_type' value='<?php echo $action ?>' />
										<input type='hidden' name='action' id='action' value='Submit' />
