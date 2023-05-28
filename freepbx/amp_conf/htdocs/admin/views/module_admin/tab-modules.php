<?php
// vim: :set filetype=php tabstop=4 shiftwidth=4 autoindent smartindent:
if ($activetab == "modules") {
	$c = 'class="tab-pane active"';
} else {
	$c = 'class="tab-pane"';
}
?>
<div role="tabpanel" <?php echo $c; ?> id="modulestab">
<?php

if($online) {
	if(!empty($announcements)) {
		echo "<div class='announcements'>$announcements</div>\n";
	}
	if (!EXTERNAL_PACKAGE_MANAGEMENT) {
		echo $repo_select;
	}
} else {
	if (!EXTERNAL_PACKAGE_MANAGEMENT) {
		echo $repo_select;
	} else {
		echo "| <a href='config.php?display=modules&amp;action=upload'>"._("Upload module")."</a><br />";
	}
}
?>


<div id="module-listing-container">
	<?php if(!empty($broken_module_list) && !$online) {?>
		<div class="alert alert-danger moduleadmin"><?php echo sprintf(_('You have some broken %s Modules. We advise you take care of these as soon as possible: '),$brand)?><a style="cursor:pointer;" onclick="$('#category_Broken').scrollMinimal(true,-250);"><?php echo _('View')?></a></div>
	<?php } ?>
	<?php if(!empty($warning)) {?>
		<div class="alert alert-danger moduleadmin">
			<p><?php echo $warning?></p>
		</div>
	<?php } ?>
<form name="modulesGUI" action="config.php?display=modules" method="post">
	<input type="hidden" name="display" value="modules" />
	<input type="hidden" name="online" value="<?php echo $online?>" />
	<input type="hidden" name="action" value="confirm" />

	<div class="modulebuttons">
		<?php if ($online) { ?>
			<a class="btn" href="#" onclick="check_download_all()"><?php echo _("Download all")?></a>
			<a class="btn" href="#" onclick="check_upgrade_all()"><?php echo _("Upgrade all") ?></a>
		<?php } ?>
		<input type="reset" value="<?php echo _("Reset")?>" />
		<input type="submit" value="<?php echo _("Process")?>" name="process" />
	</div>

	<div id="modulelist">
		<?php foreach($module_display as $category) {?>
			<div class="category" id="category_<?php echo prep_id($category['name'])?>">
				<h3><?php echo _($category['name'])?></h3>
				<div id="modulelist-header">
					<span class="modulename"><?php echo _("Module")?></span>
					<span class="moduleversion"><?php echo _("Version")?></span>
					<span class="modulepublisher"><?php echo _("Publisher")?></span>
					<span class="modulelicense"><?php echo _("License")?></span>
					<span class="modulestatus"><?php echo _("Status")?></span>
					<span class="moduletrack"><?php echo _("Track")?></span>
					<span class="clear">&nbsp;</span>
				</div>
				<table class="table modulelist table-striped" width="100%">
					<?php foreach($category['data'] as $module) {
						if($breaking['breaking'] && isset($breaking['issues'][$module['name']])){
							if($module['status'] != MODULE_STATUS_NEEDUPGRADE){
								$module['status'] = MODULE_STATUS_CONFLICT;
							}
							if($module['status'] == MODULE_STATUS_NEEDUPGRADE){
								$module['status'] = MODULE_STATUS_CONFLICT_UPGRADE;
							}
						}
					?>
					</tr>
						<td id="fullmodule_<?php echo prep_id($module['name'])?>" class="fullmodule" data-module="<?php echo prep_id($module['name'])?>">
							<div class="<?php echo $module['mclass']?>" data-module="<?php echo prep_id($module['name'])?>">
							<i id="arrow_<?php echo prep_id($module['name'])?>" class="fa fa-chevron-right"></i>
							<span class="modulename"><?php echo $module['pretty_name']?></span>
							<span class="moduleversion"><?php echo $module['dbversion']?></span>
							<span class="moduletrack <?php echo strtolower($module['track'])?>"><?php echo ucfirst($module['track'])?></span>
							<span class="modulepublisher"><?php echo $module['publisher']?></span>
							<span class="modulelicense"><?php echo (!empty($module['licenselink'])) ? '<a href="'.$module['licenselink'].'" target="_moduleLicenseLink">'.$module['license'].'</a>' : $module['license']?></span>
							<span class="modulestatus">
<?php switch ($module['status']) {
										case MODULE_STATUS_NOTINSTALLED:
											if (!empty($module['raw']['online'])) { ?>
												<span class="notinstalled text"><?php echo sprintf(_('Not Installed (Available online: %s)'), $module['raw']['online']['version'])?></span>
											<?php } else { ?>
												<span class="notinstalled text"><?php echo sprintf(_('Not Installed (Locally available: %s)'),$module['raw']['local']['version'])?></span>
											<?php }
										break;
										case MODULE_STATUS_NEEDUPGRADE:?>
											<?php $uptype = (version_compare_freepbx($module['dbversion'],$module['raw']['local']['version'],'<')) ? _("Upgrade") : _("Downgrade"); ?>
											<span class="alert text"><?php echo sprintf(_('Disabled; Pending %s to %s'),$uptype,$module['raw']['local']['version']);?></span>
										<?php break;
										case MODULE_STATUS_BROKEN:?>
											<span class="alert text"><?php echo _('Broken');?></span>
										<?php break;
										case MODULE_STATUS_CONFLICT:?>
											<span class="alert text"><?php echo _('This module has conflicts that may cause system issues');?></span>
										<?php break;

										case MODULE_STATUS_DISABLED:
										default:
											$disabled = ($module['status'] == MODULE_STATUS_DISABLED) ? _('Disabled') . '; ' : '';
											// check for online upgrade
											if (!empty($module['raw']['online'])) {
												$track = $module['track'];
												$trackinfo = (strtolower($track) == 'stable') ? $module['raw']['online'] : (!empty($module['raw']['online']['releasetracks'][$track]) ? $module['raw']['online']['releasetracks'][$track] : $module['raw']['online']);
												if (!empty($trackinfo['version'])) {
													$vercomp = version_compare_freepbx($module['raw']['local']['version'], $trackinfo['version']);
													if($trackenable && !empty($module['highreleasetracktype']) && ($module['highreleasetracktype'] == 'stable') && ($track != 'stable') && version_compare_freepbx($module['raw']['local']['version'],$module['raw']['online']['version'],"<=")) {
														$vercomp2 = true;
														$trackinfo = $module['raw']['online'];
													} else {
														$vercomp2 = false;
													}
													$tn = ($trackenable && !empty($module['highreleasetracktype'])) ? ucfirst(strtolower($module['highreleasetracktype'])) : '';
													if ($vercomp < 0 || $vercomp2) {?>
														<span class="alert text">
															<?php if($module['track'] != 'stable') { ?>
																<?php echo sprintf(_('%s Online %s upgrade available (%s)'), $disabled, $tn, $trackinfo['version']);?>
															<?php } else { ?>
																<?php echo sprintf(_('%s Online upgrade available (%s)'), $disabled, $trackinfo['version']);?>
																<?php echo ($trackenable && !empty($module['highreleasetrackver']) && version_compare_freepbx($module['highreleasetrackver'],$module['raw']['online']['version'],'>') && version_compare_freepbx($module['highreleasetrackver'],$module['raw']['local']['version'],'>') && $module['track'] != $module['highreleasetracktype']) ? '; ' . sprintf(_('%s Upgrade Available (%s)'),ucfirst($module['highreleasetracktype']),$module['highreleasetrackver']) : ''?>
															<?php } ?>
														</span>
													<?php } elseif ($vercomp > 0) { ?>
														<span class="text"><?php echo sprintf(_($disabled.'Newer than online version (%s)'), $trackinfo['version']);?></span>
													<?php } elseif($module['status'] == MODULE_STATUS_DISABLED) { ?>
														<?php echo ($trackenable && !empty($module['highreleasetrackver']) && version_compare_freepbx($module['highreleasetrackver'],$module['raw']['online']['version'],'>') && version_compare_freepbx($module['highreleasetrackver'],$module['raw']['local']['version'],'>')) ? '<span class="alert text">' . sprintf(_('%s Upgrade Available (%s)'),ucfirst($module['highreleasetracktype']),$module['highreleasetrackver']) . '</span>' : _('Disabled; up to date')?>
													<?php } else { ?>
														<?php echo ($trackenable && !empty($module['highreleasetrackver']) && version_compare_freepbx($module['highreleasetrackver'],$module['raw']['online']['version'],'>') && version_compare_freepbx($module['highreleasetrackver'],$module['raw']['local']['version'],'>')) ? '<span class="alert text">' . sprintf(_('%s Upgrade Available (%s)'),ucfirst($module['highreleasetracktype']),$module['highreleasetrackver']) . '</span>' : _('Enabled and up to date')?>
													<?php }
												}
											} else {
												?>
												<span class="text">
												<?php
													if($online && $module['status'] != MODULE_STATUS_DISABLED) {?>
														<?php echo _('Enabled; Not available online');?>
													<?php } elseif($module['status'] == MODULE_STATUS_DISABLED) { ?>
														<?php echo _('Disabled');?>
													<?php } else { ?>
														<?php echo _('Enabled'); ?>
													<?php } ?>
												</span>
												<?php
											}
										break;
									}?>
									<?php if($module['commercial']['status']) {
										if (function_exists('sysadmin_check_module')) {
											sysadmin_check_module($module);
										}
									} ?>
							</span>
							<?php if ($module['salert']) { ?>
								<span class="modulevul">
									<a class="modulevul_tag" href="#" data-sec='<?php echo json_encode($module['vulnerabilities']['vul'])?>'>
										<img src="images/notify_security.png" alt="" width="16" height="16" border="0" title="<?php echo sprintf(_("Vulnerable to security issues %s"), implode( ', ',$module['vulnerabilities']['vul']))?>" /><?php echo sprintf(_("Vulnerable, Requires: %s"), $module['vulnerabilities']['minver']) ?>
									</a>
								</span>
							<?php } ?>
								<span class="clear">&nbsp;</span>
							</div>
							<div class="moduleinfopane" id="infopane_<?php echo prep_id($module['name'])?>">
								<div class="row">
									<div class="col-sm-2">
										<ul class="nav nav-pills nav-stacked">
											<?php if (!empty($module['attention'])) { ?>
												<li role="presentation" class="active"><a href="#attention_<?php echo prep_id($module['name'])?>" data-toggle="tab"><?php echo _('Attention')?></a></li>
											<?php } ?>
											<li role="presentation" class="active"><a href="#info_<?php echo prep_id($module['name'])?>" data-toggle="tab"><?php echo _("Info")?></a></li>
											<?php if(!empty($module['changelog'])) { ?>
												<li role="presentation"><a href="#changelog_<?php echo prep_id($module['name'])?>" data-toggle="tab"><?php echo _("Changelog")?></a></li>
											<?php } ?>
											<?php if(!empty($module['previous'])) {?>
												<li role="presentation"><a href="#previous_<?php echo prep_id($module['name'])?>" data-toggle="tab"><?php echo _("Previous")?></a></li>
											<?php } ?>
											<?php if($breaking['breaking'] && !empty($breaking['issues'][$module['name']])) {?>
												<li role="presentation"><a href="#breaking_<?php echo prep_id($module['name'])?>" data-toggle="tab"><?php echo _("Conflicts")?></a></li>
											<?php } ?>
											<?php if ($devel) { ?>
												<li role="presentation"><a href="#devel_<?php echo prep_id($module['name'])?>" data-toggle="tab"><?php echo _("Debug")?></a></li>
											<?php } ?>
										</ul>
									</div>
									<div class="col-sm-10">
										<div class="tab-content">
											<?php if (!empty($module['attention'])) { ?>
												<div id="attention_<?php echo prep_id($module['name'])?>" class="tab-pane active">
													<?php echo $module['attention']?>
												</div>
											<?php } ?>
											<div id="info_<?php echo prep_id($module['name'])?>" class="tab-pane active">
												<table class="modulequickinfo">
													<?php if(!empty($module['publisher'])) {?>
													<tr>
														<td><?php echo _("Publisher")?>:</td>
														<td><?php echo $module['publisher']?></td>
													</tr>
													<?php } ?>
                                                    <?php if(!empty($module['repo'])) {?>
													<tr>
														<td><?php echo _("Repository")?>:</td>
														<td><?php echo $module['repo']?></td>
													</tr>
													<?php } ?>	
													<?php if(!empty($module['raw']['online']['packaged'])) {?>
													<tr>
														<td><?php echo _("Packaged (Released)")?>:</td>
														<td class="packagedDate"><?php echo date('m/d/y',$module['raw']['online']['packaged'])?></td>
													</tr>
													<?php } ?>
													<?php if(!empty($module['license'])) {?>
													<tr>
														<td><?php echo _("License")?>:</td>
														<td><?php echo (!empty($module['licenselink'])) ? '<a href="'.$module['licenselink'].'" target="_moduleLicenseLink">'.$module['license'].'</a>' : $module['license']?></td>
													</tr>
													<?php } ?>
													<?php if(!empty($module['signature']['message'])) {?>
														<tr>
															<td><?php echo ("Signature Status")?>:</td>
															<td><?php echo $module['signature']['message']?> <a class="alert-link" href="http://wiki.freepbx.org/display/FPG/Module+Signing" target="_blank"><?php echo _('(What Does this Mean?)') ?></a></td>
														</tr>
														<?php } ?>
													<?php if(!empty($module['salert'])) {?>
													<tr>
														<td><?php echo _("Fixes Vulnerabilities")?>:</td>
														<td><?php echo implode(', ',$module['vulnerabilities']['vul'])?></td>
													</tr>
													<?php } ?>
													<tr>
														<td><?php echo _("Description")?>:</td>
													<?php if(!empty($module['description'])) {?>
														<td><?php echo nl2br(modgettext::_($module['description'], $module['loc_domain']));?></td>
													<?php } else { ?>
														<td><?php echo _("No description is available.") ?></td>
													</tr>
													<?php } ?>
													<tr>
														<td><?php echo _('More info')?>:</td>
														<td>
															<?php if(!empty($module['info'])) {?>
																<a href="<?php echo $module['info'] ?>" target="_new"><?php echo $module['info'] ?></a>
															<?php } else { 
                                                                if (!empty($module['more-info'])){
                                                                    ?> 
                                                                    <a href="<?php echo $module['more-info']?>" target="help"><?php echo sprintf(_("Get help for %s"),$module['pretty_name'])?></a> <?php
                                                                } else {
                                                                    ?>
                                                                    <a href="<?php echo $freepbx_help_url?>&amp;freepbx_module=<?php echo urlencode($module['name'])?>" target="help"><?php echo sprintf(_("Get help for %s"),$module['pretty_name'])?></a> <?php
                                                                }
                                                            }
                                                            ?>
														</td>
													</tr>
													<?php if($module['commercial']['status']) {?>
														<?php if($module['commercial']['sysadmin'] || $module['name'] == 'sysadmin' && $module['status'] == MODULE_STATUS_ENABLED) {?>
															<tr>
																<td><a href="#" class="info"><?php echo _('Commercial Status')?>:<span><?php echo _('Commercial Status of this module. Commercial Modules are maintained and supported through  Sangoma Technologies Inc.')?></span></a></td>
																<td>
																	<?php if(!$module['commercial']['licensed'] && isset($module['commercial']['type'])) { ?>
																		<?php switch($module['commercial']['type']) {
																				case 'upgradeable':?>
																				<a href="<?php echo $module['commercial']['purchaselink']?>" class="btn btn-primary" target="_new"><?php echo _('Upgrade')?></a>
																			<?php break;?>
																			<?php case 'free':?>
																				<a href="<?php echo $module['commercial']['purchaselink']?>" class="btn btn-primary" target="_new"><?php echo _('Obtain Free License')?></a>
																			<?php break;?>
																			<?php case 'paid':?>
																			<?php default:?>
																				<a href="<?php echo $module['commercial']['purchaselink']?>" class="btn btn-primary" target="_new"><?php echo _('Learn More')?></a>

<?php if (\FreePBX::Modules()->checkStatus('sysadmin') && function_exists('sysadmin_get_buyurl')) {
        $buyurl = sysadmin_get_buyurl($module['commercial']['purchaselink']);
} else {
        $buyurl = $module['commercial']['purchaselink'];
}
?>
																				<a href="<?php echo $buyurl; ?>" class="btn fpbx-buy" data-rawname="<?php echo $module['name']?>" target="_new"><?php echo _('Buy')?></a>
																			<?php break;?>
																		<?php } ?>
																	<?php } else { ?>
																		<div class="fpbx-licensed" data-rawname="<?php echo $module['name']?>">
																			<?php echo _('Licensed')?>
																		</div>
																	<?php } ?>
																</td>
															</tr>
														<?php } ?>
													<?php } ?>
												<?php if($module['blocked']['status']) {?>
													<tr>
														<td style="color:red;"><?php echo _('Missing Requirements')?>:</td>
														<td>
															<ul class="modulerequirements">
															<?php foreach($module['blocked']['reasons'] as $mod => $reason) {?>
																<li style="cursor:<?php echo !is_int($mod) ? "pointer" : "default" ?>" onclick="<?php echo !is_int($mod) ? "$('#install_".$mod."').prop('checked',true);navigate_to_module('".$mod."');" : '' ?>"><?php echo $reason?></li>
															<?php } ?>
															</ul>
														</td>
													</tr>
												<?php } ?>
												<?php if($trackenable && $module['status'] >= 0 && !empty($module['tracks']) && ($module['status'] != MODULE_STATUS_NEEDUPGRADE)) {?>
													<tr>
														<td><a href="#" class="info"><?php echo _("Track")?>:<span><?php echo _("Modules can have separate individual repos or tracks, these tracks can determine what type of updates this module receives. A prime example is that of the beta track. You can select the beta track for this module and FreePBX will give you the highest updates in the beta track or stable. Some Modules will only have one track. Tracks can be disabled in Advanced Settings.<br>Tracks can only be changed after checking online")?></span></a></td>
														<td>
															<span class="moduletrackradios">
															<?php
															foreach($module['tracks'] as $track => $checked) {
																if($track != "stable" && empty($module['raw']['online']['releasetracks'][$track])) {
																	continue;
																}
															?>
																<input id="id="version_<?php echo prep_id($module['name'])?>"" type="hidden" name="version[<?php echo prep_id($module['name'])?>]" value="<?php echo $module['version']?>"/>
																<input id="track_<?php echo $track?>_<?php echo prep_id($module['name'])?>" type="radio" name="trackaction[<?php echo prep_id($module['name'])?>]" value="<?php echo $track?>" <?php echo ($checked) ? 'checked' : ''?>/>
																<label for="track_<?php echo $track?>_<?php echo prep_id($module['name'])?>"><?php echo ucfirst($track)?></label>
															<?php } ?>
															</span>
														</td>
													</tr>
												<?php } else { ?>
													<tr>
														<td colspan="2">
															<input id="track_stable_<?php echo prep_id($module['name'])?>" type="hidden" name="trackaction[<?php echo prep_id($module['name'])?>]" value="stable"/>
															<input id="track_version_<?php echo prep_id($module['name'])?>" type="hidden" name="version[<?php echo prep_id($module['name'])?>]" value="<?php echo $module['version']?>"/>
														</td>
													</tr>
												<?php } ?>
													<tr>
														<td><a href="#" class="info"><?php echo _("Action")?>:<span><?php echo _("Actions to preform in regards to this module. This usually contains installation and maintenance operations at an administration level")?></span></a></td>
														<td>
															<span class="modulefunctionradios">
																<input type="radio" checked="CHECKED" id="noaction_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="0" />
																<label for="noaction_<?php echo prep_id($module['name'])?>"><?php echo _('No Action')?></label>
																<?php if((($module['commercial']['status'] && $module['commercial']['sysadmin']) || !$module['commercial']['status'] || $module['name'] == 'sysadmin')) {?>
																	<?php switch ($module['status']) {
																		case MODULE_STATUS_NOTINSTALLED:
																			if (!$module['blocked']['status'] && !EXTERNAL_PACKAGE_MANAGEMENT) {
																				if (!empty($module['raw']['local'])) {?>
																					<input type="radio" id="install_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="<?php echo (!isset($module['raw']['online']['version']) || version_compare_freepbx($module['raw']['local']['version'], $module['raw']['online']['version'], ">")) ? 'install' : 'upgrade'?>" />
																					<label class="installabel" for="install_<?php echo prep_id($module['name'])?>"><?php echo (!isset($module['raw']['online']['version']) || version_compare_freepbx($module['raw']['local']['version'], $module['raw']['online']['version'], ">")) ? _('Install') : sprintf(_('Upgrade to %s and Enable'),$module['raw']['online']['version'])?></label>
																					<input type="radio" id="remove_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="remove" />
																					<label class="removelabel" for="remove_<?php echo prep_id($module['name'])?>"><?php echo _('Remove')?></label>
																				<?php } else { ?>
																					<input type="radio" id="upgrade_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="downloadinstall" />
																					<label class="installabel" for="upgrade_<?php echo prep_id($module['name'])?>"><?php echo _('Download and Install')?></label>
																				<?php } ?>
																		<?php }
																		break;
																		case MODULE_STATUS_DISABLED:?>
																			<?php if(!$module['blocked']['status']) { ?>
																				<input type="radio" id="enable_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="enable" />
																				<label for="enable_<?php echo prep_id($module['name'])?>"><?php echo _('Enable') ?></label>
																			<?php } ?>
																			<?php if (!EXTERNAL_PACKAGE_MANAGEMENT) { ?>
																				<?php if (isset($module['raw']['online']['version'])) {
																					$vercomp = version_compare_freepbx($module['raw']['local']['version'], $module['raw']['online']['version']);
																					if ($vercomp < 0) { ?>
																						<input type="radio" id="upgrade_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="upgrade" />
																						<label for="upgrade_<?php echo prep_id($module['name'])?>"><?php echo sprintf(_('Download %s, keep Disabled'),$module['raw']['online']['version'])?></label>
																					<?php } ?>
																				<?php } ?>
																				<?php if(enable_option($module['name'],'canuninstall')) { ?>
																					<input type="radio" id="uninstall_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="uninstall" />
																					<label for="uninstall_<?php echo prep_id($module['name'])?>"><?php echo _('Uninstall')?></label>
																					<input type="radio" id="remove_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="remove" />
																					<label class="removelabel" for="remove_<?php echo prep_id($module['name'])?>"><?php echo _('Remove')?></label>
																			<?php }
																				}
																		break;
																		case MODULE_STATUS_NEEDUPGRADE:
																			if (!$module['blocked']['status'] && !EXTERNAL_PACKAGE_MANAGEMENT) {?>
																				<?php $uptype = (version_compare_freepbx($module['dbversion'],$module['raw']['local']['version'],'<')) ? _("Upgrade") : _("Downgrade"); ?>
																				<input type="radio" id="install_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="install" />
																				<label class="installabel" for="install_<?php echo prep_id($module['name'])?>"><?php echo sprintf(_('%s to %s and Enable'),$uptype,$module['raw']['local']['version'])?></label>

																				<?php if (isset($module['raw']['online']['version'])) {
																					$vercomp = version_compare_freepbx($module['raw']['local']['version'], $module['raw']['online']['version']);
																					if ($vercomp < 0) {?>
																						<input type="radio" id="upgrade_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="upgrade" />
																						<label class="installabel" for="upgrade_<?php echo prep_id($module['name'])?>"><?php echo sprintf(_('Download and %s to %s'), $uptype, $module['raw']['online']['version'])?></label>
																					<?php } ?>
																				<?php } ?>
																				<input type="radio" id="uninstall_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="uninstall" />
																				<label for="uninstall_<?php echo prep_id($module['name'])?>"><?php echo _('Uninstall')?></label>
																			<?php }
																		break;
																		case MODULE_STATUS_BROKEN:
																			if (!EXTERNAL_PACKAGE_MANAGEMENT) {
																				if (!$module['blocked']['status']) { ?>
																					<input type="radio" id="install_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="install" />
																					<label for="install_<?php echo prep_id($module['name'])?>"><?php echo _('Install')?></label>
																				<?php } ?>
																				<input type="radio" id="uninstall_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="uninstall" />
																				<label for="uninstall_<?php echo prep_id($module['name'])?>"><?php echo _('Uninstall')?></label>
																			<?php }
																		break;
																		case MODULE_STATUS_CONFLICT:
																			if (!EXTERNAL_PACKAGE_MANAGEMENT) {
																				if (!$module['blocked']['status']) { ?>
																					<input type="radio" id="forceinstall_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="installignoreconflicts" />
																					<label for="forceinstall_<?php echo prep_id($module['name'])?>"><?php echo _('Force Install')?></label>
																				<?php } ?>
																			<?php }
																		break;
																		case MODULE_STATUS_CONFLICT_UPGRADE:
																			if (!EXTERNAL_PACKAGE_MANAGEMENT) {
																				if (!$module['blocked']['status']) { ?>
																					<input type="radio" id="forceupgrade_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="upgradeignoreconflicts" />
																					<label for="forceupgrade_<?php echo prep_id($module['name'])?>"><?php echo _('Force Update')?></label>
																					<input type="radio" id="uninstall_<?php echo prep_id($module['name']) ?>" name="moduleaction[<?php echo prep_id($module['name']) ?>]" value="uninstall" />
																					<label for="uninstall_<?php echo prep_id($module['name']) ?>"><?php echo _('Uninstall') ?></label>
																				<?php } ?>
																			<?php }
																		break;
																		default:
																			// check for online upgrade
																			$track = $module['track'];
																			$trackinfo = ($track == 'stable') ? $module['raw']['online'] : (!empty($module['raw']['online']['releasetracks'][$track]) ? $module['raw']['online']['releasetracks'][$track] : array());

																			if (isset($trackinfo['version'])) {
																				$vercomp = version_compare_freepbx($module['raw']['local']['version'], $trackinfo['version']);
																				if (!EXTERNAL_PACKAGE_MANAGEMENT) {
																					if ($vercomp < 0) { ?>
																						<input type="radio" id="upgrade_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="upgrade" />
																						<label class="installabel" for="upgrade_<?php echo prep_id($module['name'])?>"><?php echo sprintf(_('Download and Upgrade to %s'), $trackinfo['version'])?></label>
																					<?php } else {
																						$force_msg = ($vercomp == 0 ? sprintf(_('Force Download and Install %s'), $trackinfo['version']) : sprintf(_('Force Download and Downgrade to %s'), $trackinfo['version'])); ?>
																						<input type="radio" id="force_upgrade_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="force_upgrade" />
																						<label class="installabel" for="force_upgrade_<?php echo prep_id($module['name'])?>"><?php echo $force_msg ?></label>
																					<?php }
																				}
																			} elseif($track != "stable" && isset($module['raw']['online']['version'])) {
																				?>
																				<input type="radio" id="upgrade_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="upgrade" />
																				<label class="installabel" for="upgrade_<?php echo prep_id($module['name'])?>"><?php echo sprintf(_('Switch to Stable and Download and Install %s'), $module['raw']['online']['version'])?></label>
																				<?php
																			}
																			if (enable_option($module['name'],'candisable')) { ?>
																				<input type="radio" id="disable_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="disable" />
																				<label for="disable_<?php echo prep_id($module['name'])?>"><?php echo _('Disable')?></label>
																			<?php }
																			if (!EXTERNAL_PACKAGE_MANAGEMENT && enable_option($module['name'],'canuninstall')) {?>
																				<input type="radio" id="uninstall_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="uninstall" />
																				<label for="uninstall_<?php echo prep_id($module['name'])?>"><?php echo _('Uninstall')?></label>
																				<input type="radio" id="remove_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="remove" />
																				<label class="removelabel" for="remove_<?php echo prep_id($module['name'])?>"><?php echo _('Remove')?></label>
																			<?php }
																			if (!EXTERNAL_PACKAGE_MANAGEMENT && $devel) {?>
																				<input type="radio" id="reinstall_<?php echo prep_id($module['name'])?>" name="moduleaction[<?php echo prep_id($module['name'])?>]" value="reinstall" />
																				<label for="reinstall_<?php echo prep_id($module['name'])?>"><?php echo _('Reinstall')?></label>
																			<?php }
																		break;
																	}?>
																<?php } ?>
															</span>
														</td>
													</tr>
												</table>
											</div>
											<?php if(!empty($module['changelog'])) { ?>
												<div id="changelog_<?php echo prep_id($module['name'])?>" class="limitheight tab-pane">
													<h5><?php echo _("Change Log for version")?>: <?php echo $module['version']?></h5>
													<span><?php echo $module['changelog']?></span>
												</div>
											<?php } ?>
											<?php if(!empty($module['previous'])) {?>
												<div id="previous_<?php echo prep_id($module['name'])?>" class="tab-pane">
													<h5><?php echo _('Previous Releases')?></h5>
													<table class="rollbacklist alt_table">
														<?php foreach($module['previous'] as $release) {?>
															<tr>
																<td>
																	<strong><?php echo $release['version']?></strong>
																</td>
																<td>
																	<?php echo !empty($release['pretty_change']) ? $release['pretty_change'] : _('No Description')?>
																</td>
																<td>
																	<a href="config.php?display=modules&amp;action=confirm&amp;online=1&amp;moduleaction[<?php echo prep_id($module['name'])?>]=rollback&amp;version=<?php echo $release['version']?>" class="btn">Rollback</a>
																</td>
															</tr>
														<?php } ?>
													</table>
												</div>
											<?php } ?>
											<?php if ($breaking['breaking'] && !empty($breaking['issues'][$module['name']])) { ?>
												<div id="breaking_<?php echo prep_id($module['name']) ?>" class="limitheight tab-pane">
													<h5><?php echo _("Conflicts") ?>: <?php echo $module['version'] ?></h5>
													<span><?php echo implode(PHP_EOL, $breaking['issues'][$module['name']]) ?></span>
												</div>
											<?php
												} ?>
											<?php if ($devel) { ?>
												<div id="devel_<?php echo prep_id($module['name'])?> limitheight" class="tab-pane">
													<h5><?php echo $module['name']?></h5>
													<pre>
														<?php print_r($module['raw']['local'])?>
													</pre>
												<?php if ($module['raw']['online']) { ?>
													<h5><?php echo _('Online info')?></h5>
													<pre>
														<?php print_r($module['raw']['online'])?>
													</pre>
												<?php } ?>
													<h5><?php echo _('Combined')?></h5>
													<pre>
														<?php print_r($module)?>
													</pre>
												</div>
											<?php } ?>
										</div>
									</div>
								</div>
							</div>
						</td>
					</tr>
					<?php } ?>
				</table>
			</div>
		<?php } ?>
		<?php echo $end_msg?>
	<div class="modulebuttons">
		<?php if ($online) { ?>
			<a class="btn" href="#" onclick="check_download_all()"><?php echo _("Download all")?></a>
			<a class="btn" href="#" onclick="check_upgrade_all()"><?php echo _("Upgrade all") ?></a>
		<?php } ?>
		<input type="reset" value="<?php echo _("Reset")?>" />
		<input type="submit" value="<?php echo _("Process")?>" name="process" />
	</div>
</form>
<script>
	var modules = <?php echo !empty($finalmods) ? json_encode($finalmods) : '{}'?>;
</script>
</div>
</div>

</div> <!-- tab-pane display active? -->
