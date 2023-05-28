<div class='container-fluid'>
	<div class='row'>
		<div class='col-sm-12'>
			<div class='text-center h4'>
				<strong>
					<?php echo sprintf(_('Welcome to %s'),$brand)?>
				</strong>
			</div>
		</div>
	</div>
	<div class='row'>
		<div class='col-sm-12'>
			<p class='text-center'>
				<?php echo $brand . " " . $version?>
				<?php echo $idline; ?>
			</p>
		</div>
	</div>
	<div class='row'>
		<div class='col-sm-6'>
			<div class='row'>
				<div class='col-sm-12'>
					<div class="text-center"><?php echo _("Summary")?></div>
					<div class="summary">
						<?php foreach($services as $service) { ?>
							<div class="status-element" data-toggle="tooltip" title="<?php echo $service['tooltip']?>">
								<div class="status-icon"><span class="glyphicon <?php echo $service['glyph-class']?>"></span></div>
								<?php echo $service['title']?>
							</div>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
		<div class='col-sm-6'>
			<div class="text-center"><?php echo sprintf(_('SysInfo updated %s seconds ago'),$since)?></div>
			<br/>
			<div class='alert alert-<?php echo $alerts['state']?> sysalerts'>
				<?php if(!empty($alerts['alerttitle'])) { ?>
					<div class='text-center'><?php echo $alerts['alerttitle'] ?></div>
				<?php } ?>
				<p><?php echo $alerts['text']?></p>
			</div>
		</div>
	</div>
	<div class='row'>
		<div class='col-sm-12'>
			<div class='panel-group' id='notifications_group'>
				<?php foreach($nots as $n) {?>
					<div class="panel panel-default panel-<?php echo $n['level']?> fade in" id="panel_<?php echo $n['id']?>">
						<div class="panel-heading collapsed" data-notid="<?php echo $n['id']?>" data-notmod="<?php echo $n['module']?>" data-toggle="collapse" data-parent="#notifications_group" href="#link_<?php echo $n['id']?>">
							<div class="actions">
								<i class="fa fa-minus-circle" title="<?php echo _('Ignore This')?>"></i>
								<i class="fa fa-times-circle <?php echo !empty($n['candelete']) ? '' : 'hidden'?>" title="<?php echo _('Delete This')?>"></i>
							</div>
							<div class="panel-title"><?php echo $n['title']?></div>
						</div>
						<div id="link_<?php echo $n['id']?>" class="panel-collapse collapse">
							<div class="panel-body">
								<div class="extended_text">
									<?php echo $n['text']?>
								</div>
								<?php if(!empty($n['link'])) { ?>
									<div class="link alert-<?php echo $n['level']?>"><a class="alert-<?php echo $n['level']?>" href="<?php echo $n['link']?>"><?php echo _("Resolve")?></a></div>
								<?php } ?>
								<div class="timestamp alert-<?php echo $n['level']?>"><?php echo sprintf(_('%s ago'),$n['time'])?></div>
							</div>
						</div>
					</div>
				<?php } ?>
			</div>
		</div>
	</div>
	<?php if($showAllMessage) {?>
		<div class="showAll text-center" data-type="all"><h6><?php echo _("Show All")?></h6></div>
	<?php } else { ?>
		<div class="showAll text-center" data-type="new"><h6><?php echo _("Show New")?></h6></div>
	<?php } ?>
</div>
<script>
	$('.status-element').tooltip();
	$('.panel-collapse').on('shown.bs.collapse', function() { $('.page').packery(); });
	$('.panel-collapse').on('hidden.bs.collapse', function() { $('.page').packery(); });
	$('#notifications_group .actions i.fa-minus-circle').click(function(event) {
		event.stopPropagation();
		var id = $(this).parents('.panel-heading').data('notid');
		var raw = $(this).parents('.panel-heading').data('notmod');
		$.ajax({
			url: "ajax.php",
			data: { command: "resetmessage", id: id, module:'dashboard', raw: raw},
			success: function(data) {
				$('#panel_'+id).fadeOut('slow');
				$('.page').packery();
			},
		});
	})
	$('#notifications_group .actions i.fa-times-circle').click(function(event) {
		event.stopPropagation();
		var id = $(this).parents('.panel-heading').data('notid');
		var raw = $(this).parents('.panel-heading').data('notmod');
		$.ajax({
			url: "ajax.php",
			data: { command: "deletemessage", id: id, module:'dashboard', raw: raw},
			success: function(data) {
				$('#panel_'+id).fadeOut('slow');
				$('.page').packery();
			},
		});
	})
	$("#page_Main_Overview_overview .showAll").on("click", function() {
		Cookies.set('dashboardShowAll', ($(this).data("type") == "all"));
		$("#page_Main_Overview_overview .reload").click();
	});
</script>
