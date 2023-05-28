<div class="container-fluid">
	<h1><?php echo $heading?></h1>
		<div class="row">
			<div class="col-sm-12">
				<div class="fpbx-container">
					<div class="display <?php echo (!empty($request["action"]) && !in_array($request["action"], array("addnew","editold"))) ? "full" : "no"?>-border">
						<?php echo $content ?>
					</div>
				</div>
			</div>
		</div>
</div>
</br>
</br>
</br>
