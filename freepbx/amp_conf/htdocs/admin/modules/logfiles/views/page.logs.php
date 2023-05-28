<div class="container-fluid">
	<h1><?php echo _('Asterisk Log Files')?></h1>
	<div class = "display full-border">

		<div class="row logfiles_header">
    		<div class="col-sm-12">
				<?php echo load_view(__DIR__."/view.log.header.php", array("lf" => $lf)); ?>
    		</div>
		</div>
		
		<div class="row">
    		<div class="col-sm-12">
				<?php echo load_view(__DIR__."/view.logs.viewlog.php", array("lf" => $lf)); ?>
    		</div>
		</div>
		
	</div>
</div>
<script type="text/javascript" src="modules/logfiles/vendor/itay-grudev/jquery.text.highlight/jquery.text.highlight.min.js"></script>
<script type="text/javascript" src="modules/logfiles/assets/js/views/global.js"></script>
<script type="text/javascript" src="modules/logfiles/assets/js/views/logs.js"></script>