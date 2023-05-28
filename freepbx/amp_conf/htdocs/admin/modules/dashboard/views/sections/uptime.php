<div class="container-fluid">
	<div class='row'>
		<div class='col-sm-12 less-padding'>
			<div class="box">
				<p class='text-center'><strong><?php echo _("System Last Rebooted")?></strong></p>
				<p class='text-center'><small><?php echo sprintf(_('%s ago'),$time) ?></small></p>
			</div>
		</div>
	</div>
	<div class='row'>
		<div class='col-sm-12 less-padding'>
			<div class="box">
				<p class='text-center'><strong><?php echo _("Load Averages")?></strong></p>
				<div class='row'>
					<div class='col-sm-4'>
						<p class='text-center'><small><?php echo $cpu['loadavg']['util1']?><br/><em><?php echo _("1 Minute")?></em></small></p>
					</div>
					<div class='col-sm-4'>
						<p class='text-center'><small><?php echo $cpu['loadavg']['util5']?><br/><em><?php echo _("5 Minutes")?></em></small></p>
					</div>
					<div class='col-sm-4'>
						<p class='text-center'><small><?php echo $cpu['loadavg']['util15']?><br/><em><?php echo _("15 Minutes")?></em></small></p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
