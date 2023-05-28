<?php
if(empty($display) || $display == 'noaccess') {
	$title = _("Module Not Found");
	$summary = _("We are unable to find any information on the module you are looking for.");
	$links = '';
} else {
	$dis = isset($_REQUEST['display'])?$_REQUEST['display']:false;
	$modinfo = FreePBX::Modules()->getInfo($dis);
	$status = isset($modinfo[$dis]['status'])?$modinfo[$dis]['status']:'';
	switch ($status) {
		case MODULE_STATUS_NOTINSTALLED:
			$title = _('Module Not Installed');
			$summary = _("The module appears to be valid but is not currently installed");
			$links = '<li class="list-group-item"><a href="?display=modules">' . _("Go to Module Admin to install this module"). '</a></li>';
		break;
		case MODULE_STATUS_NEEDUPGRADE:
			$title = _('Module Disabled - Needs Upgrade');
			$summary = _("The module you are trying to access has been disabled pending a version change.");
			$links = '<li class="list-group-item"><a href="?display=modules">' . _("Go to Module Admin to re-enable this module"). '</a></li>';
		break;
		case MODULE_STATUS_DISABLED:
			$title = _('Module Disabled');
			$summary = _("The module you are trying to access has been disabled by an admin.");
			$links = '<li class="list-group-item"><a href="?display=modules">' . _("Go to Module Admin to enable this module"). '</a></li>';

		break;
		case MODULE_STATUS_BROKEN:
			$title = _('Module Broken');
			$summary = _("This module appears to be broken");
			$links = '<li class="list-group-item"><a href="#">'._("This is generally a permissions issue. From a console run 'fwconsole chown'").'</a></li>';
			$links .= '<li class="list-group-item"><a href="#">'._("You may wish to redownload this module if the data is corrupted.").'</a></li>';

		break;
		case MODULE_STATUS_ENABLED:
			$title = _("Module Access Denied");
			$summary = _("This module appears to be installed and enabled but your user does not seem to have permission to access it.");
			$links = '<li class="list-group-item"><a href="?display=ampusers">'. _("Make sure you have access to this item.").'</a></li>';
		break;
		default:
			$title = _("Module Not Found");
			$summary = _("We are unable to find any information on the module you are looking for.");
			$links = '<li class="list-group-item"><a href="#">'._("Make sure you entered the address correctly.").'</a></li>';
			$links .= '<li class="list-group-item"><a href="?display=modules">' . _("Make sure the module is installed and enabled."). '</a></li>';
		break;
	}
}
?>
<div class="container-fluid">
	<h1><?php echo $title?></h1>
	<div class="alert alert-warning">
		<?php echo $summary ?>
	</div>
<?php if(!empty($links)) {?>
<h2><?php echo _("Additional Information")?></h2>
<ul class='list-group'>
	<?php echo $links ?>
</ul>
<?php } ?>
</div>
