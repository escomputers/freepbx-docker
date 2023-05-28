<?php if(!empty($security_issues)) {?>
	<?php foreach($security_issues as $id => $issue) {?>
	<div class="module_security_description" id="security-<?php echo $id ?>" style="display: none;">
		<table>
			<tr>
				<td><?php echo _('ID') ?></td><td><?php echo $id ?></td>
			</tr>
			<tr>
				<td><?php echo _('Type') ?></td><td><?php echo $issue['type'] ?></td>
			</tr>
			<tr>
				<td><?php echo _('Severity') ?></td><td><?php echo $issue['severity'] ?></td>
			</tr>
			<tr>
				<td><?php echo _('Description') ?></td><td><?php echo $issue['description'] ?></td>
			</tr>
			<tr>
				<td><?php echo _('Date Reported') ?></td><td><?php echo $issue['reported'] ?></td>
			</tr>
			<tr>
				<td><?php echo _('Date Fixed') ?></td><td><?php echo $issue['fixed'] ?></td>
			</tr>
			<?php if(!empty($issue['related_urls']['url'])) {?>
				<?php foreach ($issue['related_urls']['url'] as $url) { ?>
					<tr>
						<td><?php echo $related_urls_text ?></td>
						<td><a href="<?php echo $url ?>" target="_security"><?php echo $url ?></a></td>
					</tr>
				<?php } ?>
			<?php } ?>
			<tr>
				<td><?php echo _('Related Tickets') ?></td>
				<td><?php echo $issue['tickets'] ?></td>
			</tr>
		</table>
	</div>
	<?php } ?>
<?php } ?>

</div>

<div class="modal fade" id='updatesmodal' tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document" style="overflow-y: initial !important">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">This should be cleared before being shown</h4>
      </div>
      <div class="modal-body" style="height: calc(80vh - 100px);  overflow-y: auto">
        <p>If you can see this, there is a bug.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<input type = 'hidden' id='phpsession' value = '<?php echo session_id()?>' />