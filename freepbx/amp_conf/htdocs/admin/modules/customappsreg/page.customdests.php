<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
$allDests = \FreePBX::Customappsreg()->getAllCustomDests();
$helptext = _("Custom Destinations allows you to register your custom destinations that point to custom dialplans and will also 'publish' these destinations as available destinations to other modules. This is an advanced feature and should only be used by knowledgeable users. If you are getting warnings or errors in the notification panel about CUSTOM destinations that are correct, you should include them here. The 'Unknown Destinations' chooser will allow you to choose and insert any such destinations that the registry is not aware of into the Custom Destination field.");
$view = isset($_REQUEST['view'])?$_REQUEST['view']:'';
switch ($view) {
	case 'form':
		$content = load_view(__DIR__.'/views/customdests/form.php', array('allDests' => $allDests));
	break;

	default:
		$content = load_view(__DIR__.'/views/customdests/grid.php');
		$bootnav = '';
	break;
}
?>
<div class="container-fluid">
	<h1><?php echo _('Custom Destinations')?></h1>
	<div class="well well-info">
		<?php echo $helptext?>
	</div>
	<div class="row">
		<div class="col-sm-12">
			<div class="fpbx-container">
				<div class="display <?php echo empty($_REQUEST['view']) ? 'no' : 'full'?>-border">
					<?php echo $content ?>
				</div>
			</div>
		</div>
	</div>
</div>
