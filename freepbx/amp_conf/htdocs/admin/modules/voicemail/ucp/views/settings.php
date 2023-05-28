<div id="settings">
	<ul class="nav nav-tabs">
		<?php foreach ($tabcontent as $id => $data) { ?>
			<li class="<?php echo $id == 'vmsettings' ? 'active' : ''?>"><a href="#vm-<?php echo $id?>" data-toggle="tab"><?php echo $data['name']?></a></li>
		<?php } ?>
	</ul>
	<div class="tab-content">
		<?php foreach ($tabcontent as $id => $data) { ?>
			<div class="tab-pane fade <?php echo $id == 'vmsettings' ? 'in active' : ''?>" id="vm-<?php echo $id?>">
				<?php echo $data['content']; ?>
			</div>
		<?php } ?>
	</div>
</div>
