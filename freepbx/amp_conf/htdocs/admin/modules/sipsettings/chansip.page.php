<?php /* $Id:$ */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

// Original Release by Philippe Lindheimer
// Copyright Philippe Lindheimer (2009)
// Copyright Bandwidth.com (2009)
/*
		This program is free software: you can redistribute it and/or modify
		it under the terms of the GNU Affero General Public License as
		published by the Free Software Foundation, either version 3 of the
		License, or (at your option) any later version.

		This program is distributed in the hope that it will be useful,
		but WITHOUT ANY WARRANTY; without even the implied warranty of
		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
		GNU Affero General Public License for more details.

		You should have received a copy of the GNU Affero General Public License
		along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

	/* Determines how many columns per row for the codecs and formats the table */
	$cols_per_row   = 4;
	$width          = (100.0 / $cols_per_row);
	$tabindex       = 0;
	$error_displays = array();
	$action                            = isset($_POST['action'])?$_POST['action']:'';
	$sip_settings['nat']               = isset($_POST['nat']) ? $_POST['nat'] : 'yes';
	$sip_settings['nat_mode']          = isset($_POST['nat_mode']) ? $_POST['nat_mode'] : 'externip';
	$sip_settings['externip_val']      = isset($_POST['externip_val']) ? htmlspecialchars($_POST['externip_val']) : '';
	$sip_settings['externhost_val']    = isset($_POST['externhost_val']) ? htmlspecialchars($_POST['externhost_val']) : '';
	$sip_settings['externrefresh']     = isset($_POST['externhostrefresh']) ? htmlspecialchars($_POST['externhostrefresh']) : '120';

	// QaD fix for localization, xgettext does not pickup the localization string in the code
	$add_field 					= _("Add Field");
	$auto_configure 			= _("Auto Configure");
	$add_local_network_field 	= _("Add Local Network Field");
	$submit_changes 			= _("Submit Changes");
	$p_idx 						= 0;
	$n_idx 						= 0;
	while (isset($_POST["localnet_$p_idx"])) {
		if ($_POST["localnet_$p_idx"] != '') {
			$sip_settings["localnet_$n_idx"] = htmlspecialchars($_POST["localnet_$p_idx"]);
			$sip_settings["netmask_$n_idx"]  = htmlspecialchars($_POST["netmask_$p_idx"]);
			$n_idx++;
		}
		$p_idx++;
	}
	$general_sip_settings 				= sipsettings_get();

	// With the new sorting, the vars should come to us in the sorted order so just use that
	//

	$sip_settings['canreinvite']       = isset($_POST['canreinvite']) ? $_POST['canreinvite'] : 'no';

	$sip_settings['checkmwi']          = isset($_POST['checkmwi']) ? htmlspecialchars($_POST['checkmwi']) : '10';
	$sip_settings['notifyringing']     = isset($_POST['notifyringing']) ? $_POST['notifyringing'] : 'yes';
	$sip_settings['notifyhold']        = isset($_POST['notifyhold']) ? $_POST['notifyhold'] : 'yes';

	$sip_settings['registertimeout']   = isset($_POST['registertimeout']) ? htmlspecialchars($_POST['registertimeout']) : '20';
	$sip_settings['registerattempts']  = isset($_POST['registerattempts']) ? htmlspecialchars($_POST['registerattempts']) : '0';
	$sip_settings['maxexpiry']         = isset($_POST['maxexpiry']) ? htmlspecialchars($_POST['maxexpiry']) : '3600';
	$sip_settings['minexpiry']         = isset($_POST['minexpiry']) ? htmlspecialchars($_POST['minexpiry']) : '60';
	$sip_settings['defaultexpiry']     = isset($_POST['defaultexpiry']) ? htmlspecialchars($_POST['defaultexpiry']) : '120';

	$sip_settings['jbenable']          = isset($_POST['jbenable']) ? $_POST['jbenable'] : 'no';
	$sip_settings['jbforce']           = isset($_POST['jbforce']) ? $_POST['jbforce'] : 'no';
	$sip_settings['jbimpl']            = isset($_POST['jbimpl']) ? $_POST['jbimpl'] : 'fixed';
	$sip_settings['jbmaxsize']         = isset($_POST['jbmaxsize']) ? htmlspecialchars($_POST['jbmaxsize']) : '200';
	$sip_settings['jbresyncthreshold'] = isset($_POST['jbresyncthreshold']) ? htmlspecialchars($_POST['jbresyncthreshold']) : '1000';
	$sip_settings['jblog']             = isset($_POST['jblog']) ? $_POST['jblog'] : 'no';

	$sip_settings['context']           = isset($_POST['context']) ? htmlspecialchars($_POST['context']) : '';
	$sip_settings['bindaddr']          = isset($_POST['bindaddr']) ? htmlspecialchars($_POST['bindaddr']) : '';
	$sip_settings['bindport']          = isset($_POST['bindport']) ? htmlspecialchars($_POST['bindport']) : '';
	$sip_settings['allowguest']        = isset($_POST['allowguest']) ? $_POST['allowguest'] : 'no';
	$sip_settings['srvlookup']         = isset($_POST['srvlookup']) ? $_POST['srvlookup'] : 'no';
	$sip_settings['tcpenable']         = isset($_POST['tcpenable']) ? $_POST['tcpenable'] : 'no';
	$sip_settings['callevents']        = isset($_POST['callevents']) ? $_POST['callevents'] : 'no';

	$sip_settings['tlsenable']         = isset($_POST['tlsenable']) ? $_POST['tlsenable'] : 'no';
	$sip_settings['csipcertid']        = isset($_POST['csipcertid']) ? $_POST['csipcertid'] : '';
	$sip_settings['tlsclientmethod']   = isset($_POST['tlsclientmethod']) ? $_POST['tlsclientmethod'] : 'tlsv1';
	$sip_settings['tlsdontverifyserver']        = isset($_POST['tlsdontverifyserver']) ? $_POST['tlsdontverifyserver'] : '';
	$sip_settings['tlsbindaddr']       = isset($_POST['tlsbindaddr']) ? htmlspecialchars($_POST['tlsbindaddr']) : '';
	$sip_settings['tlsbindport']       = isset($_POST['tlsbindport']) ? htmlspecialchars($_POST['tlsbindport']) : '';


	$p_idx = 0;
	$n_idx = 0;
	while (isset($_POST["sip_custom_key_$p_idx"])) {
		if ($_POST["sip_custom_key_$p_idx"] != '') {
			$sip_settings["sip_custom_key_$n_idx"] = htmlspecialchars($_POST["sip_custom_key_$p_idx"]);
			$sip_settings["sip_custom_val_$n_idx"] = htmlspecialchars($_POST["sip_custom_val_$p_idx"]);
			$n_idx++;
		}
		$p_idx++;
	}

	switch ($action) {
		case "edit":  //just delete and re-add
			if (($errors = sipsettings_edit($sip_settings)) !== true) {
				$error_displays = sipsettings_process_errors($errors);
			} else {
				needreload();
				//redirect_standard();
			}
		break;
		default:
			/* only get them if first time load, if they pressed submit, use values from POST */
			$sip_settings = sipsettings_get();
	}
	$error_displays = array_merge($error_displays,sipsettings_check_custom_files());

?>
<div class="alert alert-info"><?php echo _('These settings apply only to SIP Legacy [chan_sip]')?></div>

	<h2><?php echo _("Edit Settings"); ?></h2>

<?php

	/* EXTRACT THE VARIABLE HERE - MAKE SURE THEY ARE ALL MASSAGED ABOVE */
	//
	extract($sip_settings);

?>
	<input type="hidden" name="action" value="edit">
<?php
	/* if there were erros on the submit then create error box */
	if (!empty($error_displays)) {
?>
 <div class="sip-errors">
	 <p><?php echo _("ERRORS") ?></p>
		<ul>
<?php
		foreach ($error_displays as $div_disp) {
			echo "<li>".$div_disp['div']."</li>";
		}
?>
		</ul>
	</div>
<?php
	}
?>
<div class="section-title" data-for="sscsnat">
	<h3><i class="fa fa-minus"></i> <?php echo _("NAT Settings") ?></h3>
</div>
<div class="section" data-id="sscsnat">
	<!--NAT-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="nat"><?php echo _("NAT") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="nat"></i>
						</div>
						<div class="col-md-9 radioset">
							<input id="nat-yes" type="radio" name="nat" value="yes" <?php echo $nat=="yes"?"checked=\"yes\"":""?>/>
							<label for="nat-yes"><?php echo _("yes")?></label>
							<input id="nat-no" type="radio" name="nat" value="no" <?php echo $nat=="no"?"checked=\"no\"":""?>/>
							<label for="nat-no"><?php echo _("no")?></label>
							<input id="nat-never" type="radio" name="nat" value="never" <?php echo $nat=="never"?"checked=\"never\"":""?>/>
							<label for="nat-never"><?php echo _("never")?></label>
							<input id="nat-route" type="radio" name="nat" value="route" <?php echo $nat=="route"?"checked=\"route\"":""?>/>
							<label for="nat-route"><?php echo _("route")?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="nat-help" class="help-block fpbx-help-block"><?php echo _("Asterisk NAT setting:<br /> yes = Always ignore info and assume NAT<br /> no = Use NAT mode only according to RFC3581 <br /> never = Never attempt NAT mode or RFC3581 <br /> route = Assume NAT, don't send rport")?></span>
			</div>
		</div>
	</div>
	<!--END NAT-->
	<!--IP Configuration-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="nat_mode"><?php echo _("IP Configuration") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="nat_mode"></i>
						</div>
						<div class="col-md-9 radioset">
							<input id="nat-none" type="radio" name="nat_mode" value="public" <?php echo $nat_mode=="public"?"checked=\"public\"":""?>/>
							<label for="nat-none"><?php echo _("Public IP") ?></label>
							<input id="externip1" type="radio" name="nat_mode" value="externip" <?php echo $nat_mode=="externip"?"checked=\"externip\"":""?>/>
							<label for="externip1"><?php echo _("Static IP") ?></label>
							<input id="externhost" type="radio" name="nat_mode" value="externhost" <?php echo $nat_mode=="externhost"?"checked=\"externhost\"":""?>/>
							<label for="externhost"><?php echo _("Dynamic IP") ?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="nat_mode-help" class="help-block fpbx-help-block"><?php echo _("Indicate whether the box has a public IP or requires NAT settings.<br/>If the public address is not correctly detected you can supply the external address manually.<br/>If your IP address is not static you can specify a dynamicDNS host name under Dynamic IP.<br/> Automatic configuration of what is often put in sip_nat.conf")?></span>
			</div>
		</div>
	</div>
	<!--END IP Configuration-->
	<div class="nat-settings externip">
		<!--Override External IP-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="externip_val"><?php echo _("Override External IP") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="externip_val"></i>
							</div>
							<div class="col-md-9">
								<?php
									$placeholder = FreePBX::Sipsettings()->getConfig('externip');
									if (!$placeholder) {
										$placeholder = "Enter IP Address";
									}
								?>
								<input type="text" class="form-control" id="externip_val" name="externip_val" value="<?php echo isset($externip_val) ? $externip_val : '' ?>" placeholder="<?php echo $placeholder; ?>">
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="externip_val-help" class="help-block fpbx-help-block"><?php echo _("External Static IP or FQDN as seen on the WAN side of the router. (asterisk: externip)")." &nbsp; "._("Note that this will, by default, inherit the settings from the General page")?></span>
				</div>
			</div>
		</div>
		<!--END Override External IP-->
	</div>
	<div class="nat-settings externhost">
		<!--Dynamic Host-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="externhost_val"><?php echo _("Dynamic Host") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="externhost_val"></i>
							</div>
							<div class="col-md-9">
								<input type="text" class="form-control" id="externhost_val" name="externhost_val" value="<?php echo isset($externhost_val) ? $externhost_val : '' ?>">
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="externhost_val-help" class="help-block fpbx-help-block"><?php echo _("External FQDN as seen on the WAN side of the router and updated dynamically, e.g. mydomain.example.com. (asterisk: externhost)")?></span>
				</div>
			</div>
		</div>
		<!--END Dynamic Host-->
		<!--Dynamic Host Refresh-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="externhostrefresh"><?php echo _("Dynamic Host Refresh") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="externhostrefresh"></i>
							</div>
							<div class="col-md-9">
								<div class="input-group">
									<input type="number" class="form-control" id="externhostrefresh" name="externhostrefresh" value="<?php echo isset($externrefresh) ? $externrefresh : ''?>">
									<span class="input-group-addon"><?php echo _("Seconds")?></span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="externhostrefresh-help" class="help-block fpbx-help-block"><?php echo _("Asterisk: externrefresh. How often to lookup and refresh the External Host FQDN, in seconds.")?></span>
				</div>
			</div>
		</div>
		<!--END Dynamic Host Refresh-->
	</div>
</div>
<?php if(\FreePBX::Modules()->moduleHasMethod("certman","getDefaultCertDetails")) {?>
	<div class="section-title" data-for="csiptls"><h3>
		<i class="fa fa-minus"></i> <?php echo _("TLS/SSL/SRTP Settings")?></h3>
	</div>
	<div class="section" data-id="csiptls">
		<div class="element-container">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="tlsenable"><?php echo _("Enable TLS") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="tlsenable"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="tlsenable" id="tlsenableyes" value="yes" <?php echo ($tlsenable == "yes"?"CHECKED":"") ?>>
						<label for="tlsenableyes"><?php echo _("Yes");?></label>
						<input type="radio" name="tlsenable" id="tlsenableno" value="no" <?php echo ($tlsenable == "no"?"CHECKED":"") ?>>
						<label for="tlsenableno"><?php echo _("No");?></label>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="tlsenable-help" class="help-block fpbx-help-block"><?php echo _("Enable server for incoming TLS (secure) connections.")?></span>
				</div>
			</div>
		</div>
		<div class="element-container">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="csipcalistfile"><?php echo _("Certificate Manager") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="csipcalistfile"></i>
					</div>
					<div class="col-md-9">
						<select class="form-control" id="csipcalistfile" name="csipcertid">
							<option value=""><?php echo "--"._("Select a Certificate")."--"?></option>
							<?php foreach(\FreePBX::Certman()->getAllManagedCertificates() as $cert) { ?>
								<option value="<?php echo $cert['cid']?>" <?php echo $csipcertid == $cert['cid'] ? 'selected' : ''?>><?php echo $cert['basename']?></option>
							<?php } ?>
						</select>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="csipcalistfile-help" class="help-block fpbx-help-block"><?php echo _("Select a certificate to use for the TLS transport. These are configured in the module Certificate Manager")?></span>
				</div>
			</div>
		</div>
		<div class="element-container">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="tlsclientmethod"><?php echo _("SSL Method") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="tlsclientmethod"></i>
					</div>
					<div class="col-md-9 radioset">
						<select class="form-control" id="tlsclientmethod" name="tlsclientmethod">
							<option value="tlsv1" <?php echo ($tlsclientmethod == "tlsv1"?"selected":"") ?>>tlsv1</option>
							<option value="sslv2" <?php echo ($tlsclientmethod == "sslv2"?"selected":"") ?>>sslv2 (<?php echo _('Insecure')?>)</option>
							<option value="sslv3" <?php echo ($tlsclientmethod == "sslv3"?"selected":"") ?>>sslv3 (<?php echo _('Insecure')?>)</option>
						</select>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="tlsclientmethod-help" class="help-block fpbx-help-block"><?php echo _("Method of SSL transport (TLS ONLY). The default is currently tlsv1, but may change with future releases.")?></span>
				</div>
			</div>
		</div>
		<div class="element-container">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="tlsdontverifyserver"><?php echo _("Don't Verify Server") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="tlsdontverifyserver"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="tlsdontverifyserver" id="tlsdontverifyserveryes" value="yes" <?php echo ($tlsdontverifyserver == "yes"?"CHECKED":"") ?>>
						<label for="tlsdontverifyserveryes"><?php echo _("Yes");?></label>
						<input type="radio" name="tlsdontverifyserver" id="tlsdontverifyserverno" value="no" <?php echo ($tlsdontverifyserver == "no"?"CHECKED":"") ?>>
						<label for="tlsdontverifyserverno"><?php echo _("No");?></label>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="tlsdontverifyserver-help" class="help-block fpbx-help-block"><?php echo _("Don't Require verification of server certificate (TLS ONLY).")?></span>
				</div>
			</div>
		</div>
	</div>
<?php } ?>
<div class="section-title" data-for="sscsmedia">
	<h3><i class="fa fa-minus"></i> <?php echo _("MEDIA & RTP Settings")?></h3>
</div>
<div class="section" data-id="sscsmedia">
	<!--Non-Standard g726-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="g726nonstandard"><?php echo _("Non-Standard g726") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="g726nonstandard"></i>
						</div>
						<div class="col-md-9 radioset">
							<input id="g726nonstandard-yes" type="radio" name="g726nonstandard" value="yes" <?php echo $g726nonstandard=="yes"?"checked=\"yes\"":""?>/>
							<label for="g726nonstandard-yes"><?php echo _("Yes") ?></label>
							<input id="g726nonstandard-no" type="radio" name="g726nonstandard" value="no" <?php echo $g726nonstandard=="no"?"checked=\"no\"":""?>/>
							<label for="g726nonstandard-no"><?php echo _("No") ?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="g726nonstandard-help" class="help-block fpbx-help-block"><?php echo _("Asterisk: g726nonstandard. If the peer negotiates G726-32 audio, use AAL2 packing order instead of RFC3551 packing order (this is required for Sipura and Grandstream ATAs, among others). This is contrary to the RFC3551 specification, the peer _should_ be negotiating AAL2-G726-32 instead.")?></span>
			</div>
		</div>
	</div>
	<!--END Non-Standard g726-->
	<!--Reinvite Behavior-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="canreinvite"><?php echo _("Reinvite Behavior") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="canreinvite"></i>
						</div>
						<div class="col-md-9 radioset">
							<input id="canreinvite-yes" type="radio" name="canreinvite" value="yes" <?php echo $canreinvite=="yes"?"checked=\"yes\"":""?>/>
							<label for="canreinvite-yes"><?php echo _("yes") ?></label>
							<input id="canreinvite-no" type="radio" name="canreinvite" value="no" <?php echo $canreinvite=="no"?"checked=\"no\"":""?>/>
							<label for="canreinvite-no"><?php echo _("no") ?></label>
							<input id="canreinvite-nonat" type="radio" name="canreinvite" value="nonat" <?php echo $canreinvite=="nonat"?"checked=\"nonat\"":""?>/>
							<label for="canreinvite-nonat">nonat</label>
							<input id="canreinvite-update" type="radio" name="canreinvite" value="update" <?php echo $canreinvite=="update"?"checked=\"update\"":""?>/>
							<label for="canreinvite-update">update</label>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="canreinvite-help" class="help-block fpbx-help-block"><?php echo _("Asterisk: canreinvite. yes: standard reinvites; no: never; nonat: An additional option is to allow media path redirection (reinvite) but only when the peer where the media is being sent is known to not be behind a NAT (as the RTP core can determine it based on the apparent IP address the media arrives from; update: use UPDATE for media path redirection, instead of INVITE. (yes = update + nonat)")?></span>
			</div>
		</div>
	</div>
	<!--END Reinvite Behavior-->
</div>
<div class="section-title" data-for="sscsnotif">
	<h3><i class="fa fa-minus"></i> <?php echo _("Notification & MWI")?></h3>
</div>
<div class="section" data-id="sscsnotif">
	<!--MWI Polling Freq-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="checkmwi"><?php echo _("MWI Polling Freq") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="checkmwi"></i>
						</div>
						<div class="col-md-9">
							<input type="number" class="form-control" id="checkmwi" name="checkmwi" value="<?php echo $checkmwi ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="checkmwi-help" class="help-block fpbx-help-block"><?php echo _("Frequency in seconds to check if MWI state has changed and inform peers.")?></span>
			</div>
		</div>
	</div>
	<!--END MWI Polling Freq-->
	<!--Notify Ringing-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="notifyringing"><?php echo _("Notify Ringing") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="notifyringing"></i>
						</div>
						<div class="col-md-9 radioset">
							<input id="notifyringing-yes" type="radio" name="notifyringing" value="yes" <?php echo $notifyringing=="yes"?"checked=\"yes\"":""?>/>
							<label for="notifyringing-yes"><?php echo _("Yes") ?></label>
							<input id="notifyringing-no" type="radio" name="notifyringing" value="no" <?php echo $notifyringing=="no"?"checked=\"no\"":""?>/>
							<label for="notifyringing-no"><?php echo _("No") ?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="notifyringing-help" class="help-block fpbx-help-block"><?php echo _("Control whether subscriptions already INUSE get sent RINGING when another call is sent. Useful when using BLF.")?></span>
			</div>
		</div>
	</div>
	<!--END Notify Ringing-->
	<!--Notify Hold-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="notifyhold"><?php echo _("Notify Hold") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="notifyhold"></i>
						</div>
						<div class="col-md-9 radioset">
							<input id="notifyhold-yes" type="radio" name="notifyhold" value="yes" <?php echo $notifyhold=="yes"?"checked=\"yes\"":""?>/>
							<label for="notifyhold-yes"><?php echo _("Yes") ?></label>
							<input id="notifyhold-no" type="radio" name="notifyhold" value="no" <?php echo $notifyhold=="no"?"checked=\"no\"":""?>/>
							<label for="notifyhold-no"><?php echo _("No") ?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="notifyhold-help" class="help-block fpbx-help-block"><?php echo _("Control whether subscriptions INUSE get sent ONHOLD when call is placed on hold. Useful when using BLF.")?></span>
			</div>
		</div>
	</div>
	<!--END Notify Hold-->
</div>
<div class="section-title" data-for="sscsregist">
	<h3><i class="fa fa-minus"></i> <?php echo _("Registration Settings")?></h3>
</div>
<div class="section" data-id="sscsregist">
	<!--Registration Timeout-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="registertimeout"><?php echo _("Registration Timeout") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="registertimeout"></i>
						</div>
						<div class="col-md-9">
							<input type="number" class="form-control" id="registertimeout" name="registertimeout" value="<?php echo $registertimeout ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="registertimeout-help" class="help-block fpbx-help-block"><?php echo _("Retry registration attempts every registertimeout seconds until successful or until registrationattempts tries have been made.")?></span>
			</div>
		</div>
	</div>
	<!--END Registration Timeout-->
	<!--Registration Attempts-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="registerattempts"><?php echo _("Registration Attempts") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="registerattempts"></i>
						</div>
						<div class="col-md-9">
							<input type="number" class="form-control" id="registerattempts" name="registerattempts" value="<?php echo $registerattempts ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="registerattempts-help" class="help-block fpbx-help-block"><?php echo _("Number of times to try and register before giving up. A value of 0 means keep trying forever. Normally this should be set to 0 so that Asterisk will continue to register until successful in the case of network or gateway outages.")?></span>
			</div>
		</div>
	</div>
	<!--END Registration Attempts-->
	<!--Regitration Minimum Expiry-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="minexpiry"><?php echo _("Registration Minimum Expiry") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="minexpiry"></i>
						</div>
						<div class="col-md-9">
							<input type="number" class="form-control" id="minexpiry" name="minexpiry" value="<?php echo $minexpiry ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="minexpiry-help" class="help-block fpbx-help-block"><?php echo _("Minimum length of registrations/subscriptions.")?></span>
			</div>
		</div>
	</div>
	<!--END Regitration Minimum Expiry-->
	<!--Regitration Maximum Expiry-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="maxexpiry"><?php echo _("Registration Maximum Expiry") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="maxexpiry"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="maxexpiry" name="maxexpiry" value="<?php echo $maxexpiry ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="maxexpiry-help" class="help-block fpbx-help-block"><?php echo _("Maximum allowed time of incoming registrations")?></span>
			</div>
		</div>
	</div>
	<!--END Regitration Maximum Expiry-->
	<!--Registration Default Expiry-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="defaultexpiry"><?php echo _("Registration Default Expiry") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="defaultexpiry"></i>
						</div>
						<div class="col-md-9">
							<input type="number" class="form-control" id="defaultexpiry" name="defaultexpiry" value="<?php echo $defaultexpiry ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="defaultexpiry-help" class="help-block fpbx-help-block"><?php echo _("Default length of incoming and outgoing registrations.")?></span>
			</div>
		</div>
	</div>
	<!--END Registration Default Expiry-->
</div>
<div class="section-title" data-for="sscsjb">
	<h3><i class="fa fa-minus"></i> <?php echo _("Jitter Buffer Settings")?></h3>
</div>
<div class="section" data-id="sscsjb">
	<!--Enable Jitter Buffer-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="jbenable"><?php echo _("Enable Jitter Buffer") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="jbenable"></i>
						</div>
						<div class="col-md-9 radioset">
							<input id="jbenable-yes" type="radio" name="jbenable" value="yes" <?php echo $jbenable=="yes"?"checked=\"yes\"":""?>/>
							<label for="jbenable-yes"><?php echo _("Yes") ?></label>
							<input id="jbenable-no" type="radio" name="jbenable" value="no" <?php echo $jbenable=="no"?"checked=\"no\"":""?>/>
							<label for="jbenable-no"><?php echo _("No") ?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="jbenable-help" class="help-block fpbx-help-block"><?php echo _("Enables the use of a jitterbuffer on the receiving side of a SIP channel. An enabled jitterbuffer will be used only if the sending side can create and the receiving side can not accept jitter. The SIP channel can accept jitter, thus a jitterbuffer on the receive SIP side will be used only if it is forced and enabled. An example is if receiving from a jittery channel to voicemail, the jitter buffer will be used if enabled. However, it will not be used when sending to a SIP endpoint since they usually have their own jitter buffers. See jbforce to force its use always.")?></span>
			</div>
		</div>
	</div>
	<!--END Enable Jitter Buffer-->
	<div class="jitter-buffer">
		<!--Force Jitter Buffer-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="jbforce"><?php echo _("Force Jitter Buffer") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="jbforce"></i>
							</div>
							<div class="col-md-9 radioset">
								<input id="jbforce-yes" type="radio" name="jbforce" value="yes" <?php echo $jbforce=="yes"?"checked=\"yes\"":""?>/>
								<label for="jbforce-yes"><?php echo _("Yes") ?></label>
								<input id="jbforce-no" type="radio" name="jbforce" value="no" <?php echo $jbforce=="no"?"checked=\"no\"":""?>/>
								<label for="jbforce-no"><?php echo _("No") ?></label>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="jbforce-help" class="help-block fpbx-help-block"><?php echo _("Forces the use of a jitterbuffer on the receive side of a SIP channel. Normally the jitter buffer will not be used if receiving a jittery channel but sending it off to another channel such as another SIP channel to an endpoint, since there is typically a jitter buffer at the far end. This will force the use of the jitter buffer before sending the stream on. This is not typically desired as it adds additional latency into the stream.")?></span>
				</div>
			</div>
		</div>
		<!--END Force Jitter Buffer-->
		<!--Implementation-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="jbimpl"><?php echo _("Implementation") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="jbimpl"></i>
							</div>
							<div class="col-md-9 radioset">
								<input id="jbimpl-fixed" type="radio" name="jbimpl" value="fixed" <?php echo $jbimpl=="fixed"?"checked=\"fixed\"":""?>/>
								<label for="jbimpl-fixed"><?php echo _("Fixed") ?></label>
								<input id="jbimpl-adaptive" type="radio" name="jbimpl" value="adaptive" <?php echo $jbimpl=="adaptive"?"checked=\"adaptive\"":""?>/>
								<label for="jbimpl-adaptive"><?php echo _("Adaptive") ?></label>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="jbimpl-help" class="help-block fpbx-help-block"><?php echo _("Jitterbuffer implementation, used on the receiving side of a SIP channel. Two implementations are currently available:<br /> fixed: size always equals to jbmaxsize;<br /> adaptive: with variable size (the new jb of IAX2).")?></span>
				</div>
			</div>
		</div>
		<!--END Implementation-->
		<!--Jitter Buffer Logging-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="jblog"><?php echo _("Jitter Buffer Logging") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="jblog"></i>
							</div>
							<div class="col-md-9 radioset">
								<input id="jblog-yes" type="radio" name="jblog" value="yes" <?php echo $jblog=="yes"?"checked=\"yes\"":""?>/>
								<label for="jblog-yes"><?php echo _("Yes") ?></label>
								<input id="jblog-no" type="radio" name="jblog" value="no" <?php echo $jblog=="no"?"checked=\"no\"":""?>/>
								<label for="jblog-no"><?php echo _("No") ?></label>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="jblog-help" class="help-block fpbx-help-block"><?php echo _("Enables jitter buffer frame logging.")?></span>
				</div>
			</div>
		</div>
		<!--END Jitter Buffer Logging-->
		<!--Jitter Buffer Max Size-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="jbmaxsize"><?php echo _("Jitter Buffer Max Size") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="jbmaxsize"></i>
							</div>
							<div class="col-md-9">
								<input type="text" class="form-control" id="jbmaxsize" name="jbmaxsize" value="<?php echo $jbmaxsize ?>">
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="jbmaxsize-help" class="help-block fpbx-help-block"><?php echo _("Max length of the jitterbuffer in milliseconds.")?></span>
				</div>
			</div>
		</div>
		<!--END Jitter Buffer Max Size-->
		<!--Jitter Buffer Resync Threshold-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="jbresyncthreshold"><?php echo _("Jitter Buffer Resync Threshold") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="jbresyncthreshold"></i>
							</div>
							<div class="col-md-9">
								<input type="number" min="-1" class="form-control" id="jbresyncthreshold" name="jbresyncthreshold" value="<?php echo $jbresyncthreshold ?>">
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="jbresyncthreshold-help" class="help-block fpbx-help-block"><?php echo _("Jump in the frame timestamps over which the jitterbuffer is resynchronized. Useful to improve the quality of the voice, with big jumps in/broken timestamps, usually sent from exotic devices and programs. Can be set to -1 to disable.")?></span>
				</div>
			</div>
		</div>
		<!--END Jitter Buffer Resync Threshold-->
	</div>
</div>
<div class="section-title" data-for="sscsadv">
	<h3><i class="fa fa-minus"></i> <?php echo _("Advanced General Settings")?></h3>
</div>
<div class="section" data-id="sscsadv">
	<!--Default Context-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="context"><?php echo _("Default Context") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="context"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control validate-alphanumeric" id="context" name="context" value="<?php echo $context ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="context-help" class="help-block fpbx-help-block"><?php echo _("Default context for incoming calls if not specified. FreePBX sets this to from-sip-external which is used in conjunction with the Allow Anonymous SIP calls. If you change this you will effect that behavior. It is recommended to leave this blank.")?></span>
			</div>
		</div>
	</div>
	<!--END Default Context-->
	<?php
		$tt = _("Asterisk: bindaddr. The IP address to bind to and listen for calls on the Bind Port. If set to 0.0.0.0 Asterisk will listen on all addresses. It is recommended to leave this blank.");
		$tt .= ' ' . _("Note that chan_sip does not support IPv6 for UDP protocols. An address of '::' will listen on both IPv4 and IPv6, but is not recommended. If you want to use IPv6, it is recommended to use PJSip for those devices or trunks.");
	?>
	<!--Bind Address-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="bindaddr"><?php echo _("Bind Address") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="bindaddr"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control validate-ip" id="bindaddr" name="bindaddr" placeholder='0.0.0.0' value="<?php echo $bindaddr ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="bindaddr-help" class="help-block fpbx-help-block"><?php echo $tt ?></span>
			</div>
		</div>
	</div>
	<!--END Bind Address-->
	<!--Bind Port-->
	<div class="element-container">
		<div class="row">
			<div class="form-group">
				<div class="col-md-3">
					<label class="control-label" for="bindport"><?php echo _("Bind Port") ?></label>
					<i class="fa fa-question-circle fpbx-help-icon" data-for="bindport"></i>
				</div>
				<div class="col-md-9">
					<input type="text" class="form-control validate-ip-port" id="bindport" name="bindport" value="<?php echo $bindport ?>">
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="bindport-help" class="help-block fpbx-help-block"><?php echo _("Local incoming UDP (and TCP if enabled) port that Asterisk will bind to and listen for chan_SIP messages. If left blank, it will default to 5060. Legacy installs (when only a single SIP driver was present) would set the chan_sip bind port to 5060, but new installs set it to 5160.")?></span>
			</div>
		</div>
	</div>
	<!--END Bind Port-->
	<!--Bind Address-->
	<div class="element-container">
		<div class="row">
			<div class="form-group">
				<div class="col-md-3">
					<label class="control-label" for="tlsbindaddr"><?php echo _("TLS Bind Address") ?></label>
					<i class="fa fa-question-circle fpbx-help-icon" data-for="tlsbindaddr"></i>
				</div>
				<div class="col-md-9">
					<input type="text" class="form-control validate-ip" id="tlsbindaddr" name="tlsbindaddr" placeholder='::' value="<?php echo $tlsbindaddr ?>">
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="tlsbindaddr-help" class="help-block fpbx-help-block"><?php echo _("TCP Port to listen on for TLS (Encrypted) connections. Defaults to '::', which listens on all interfaces for both IPv4 and IPv6 connections. Note that PJSip is preferable for all IPv6 communications."); ?></span>
			</div>
		</div>
	</div>
	<!--END Bind Address-->
	<!--Bind Port-->
	<div class="element-container">
		<div class="row">
			<div class="form-group">
				<div class="col-md-3">
					<label class="control-label" for="tlsbindport"><?php echo _("TLS Bind Port") ?></label>
					<i class="fa fa-question-circle fpbx-help-icon" data-for="tlsbindport"></i>
				</div>
				<div class="col-md-9">
					<input type="text" class="form-control validate-ip-port" id="tlsbindport" name="tlsbindport" value="<?php echo $tlsbindport ?>">
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="tlsbindport-help" class="help-block fpbx-help-block"><?php echo _("Local incoming TCP Port that Asterisk will bind to and listen for TLS SIP messages.")?></span>
			</div>
		</div>
	</div>
	<!--END Bind Port-->
	<!--SRV Lookup-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="srvlookup"><?php echo _("Enable SRV Lookup") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="srvlookup"></i>
						</div>
						<div class="col-md-9 radioset">
							<input id="srvlookup-yes" type="radio" name="srvlookup" value="yes" <?php echo $srvlookup=="yes"?"checked=\"yes\"":""?>/>
							<label for="srvlookup-yes"><?php echo _("Yes") ?></label>
							<input id="srvlookup-no" type="radio" name="srvlookup" value="no" <?php echo $srvlookup=="no"?"checked=\"no\"":""?>/>
							<label for="srvlookup-no"><?php echo _("No") ?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="srvlookup-help" class="help-block fpbx-help-block"><?php echo _(" See current version of Asterisk for limitations on SRV functionality.")?></span>
			</div>
		</div>
	</div>
	<!--END SRV Lookup-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="tcpenable"><?php echo _("Enable TCP") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="tcpenable"></i>
						</div>
						<div class="col-md-9 radioset">
							<input id="tcpenable-yes" type="radio" name="tcpenable" value="yes" <?php echo $tcpenable=="yes"?"checked=\"yes\"":""?>/>
							<label for="tcpenable-yes"><?php echo _("Yes") ?></label>
							<input id="tcpenable-no" type="radio" name="tcpenable" value="no" <?php echo (!isset($tcpenable) || $tcpenable=="no")?"checked=\"no\"":""?>/>
							<label for="tcpenable-no"><?php echo _("No") ?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="tcpenable-help" class="help-block fpbx-help-block"><?php echo _("Enable TCP")?></span>
			</div>
		</div>
	</div>
	<!--Call Events-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="callevents"><?php echo _("Call Events") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="callevents"></i>
						</div>
						<div class="col-md-9 radioset">
							<input id="callevents-yes" type="radio" name="callevents" value="yes" <?php echo $callevents=="yes"?"checked=\"yes\"":""?>/>
							<label for="callevents-yes"><?php echo _("Yes") ?></label>
							<input id="callevents-no" type="radio" name="callevents" value="no" <?php echo $callevents=="no"?"checked=\"no\"":""?>/>
							<label for="callevents-no"><?php echo _("No") ?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="callevents-help" class="help-block fpbx-help-block"><?php echo _("Generate manager events when sip ua performs events (e.g. hold).")?></span>
			</div>
		</div>
	</div>
	<!--END Call Events-->
	<?php
	$idx = 1;
	$var_sip_custom_key = "sip_custom_key_$idx";
	$var_sip_custom_val = "sip_custom_val_$idx";
	$csotherinputs = '';
	while (isset($$var_sip_custom_key)) {
		if ($$var_sip_custom_key != '') {
			$csotherinputs .= <<< END
			<div class="form-group form-inline" id="sc_$idx">
				<input type="text" id="sip_custom_key_$idx" name="sip_custom_key_$idx" class="sip-custom" value="{$$var_sip_custom_key}" tabindex="$tabindex"> =
				<input type="text" id="sip_custom_val_$idx" name="sip_custom_val_$idx" value="{$$var_sip_custom_val}" tabindex="$tabindex">
				<a href="config.php?display=sipsettings&action=delete&key={$$var_sip_custom_key}&val={$$var_sip_custom_val}" onclick="return confirm( _('Are you sure you want to delete this item?') );"><i class="fa fa-trash-o" ></i></a>
			</div>
END;
		}
		$idx++;
		$var_sip_custom_key = "sip_custom_key_$idx";
		$var_sip_custom_val = "sip_custom_val_$idx";
	}
?>
	<!--Other SIP Settings-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="otherw"><?php echo _("Other SIP Settings") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="otherw"></i>
						</div>
						<div class="col-md-9">
							<div class="form-group form-inline" id="sc_0">
								<input type="text" id="sip_custom_key_0" name="sip_custom_key_0" class="sip-custom" value="<?php echo $sip_custom_key_0 ?>" > =
								<input type="text" id="sip_custom_val_0" name="sip_custom_val_0" value="<?php echo $sip_custom_val_0 ?>" >
								<?php if(!empty($sip_custom_val_0) && !empty($sip_custom_key_0)){ ?><a href="config.php?display=sipsettings&action=delete&key=<?php echo $sip_custom_key_0 ?>&val=<?php echo $sip_custom_val_0 ?>" onclick="return confirm(_('Are you sure you want to delete this item?'));"><i class="fa fa-trash-o" ></i></a><?php }?>
							</div>
							<?php echo $csotherinputs?>
							<div id="sip-custom-buttons">
								<input type="button" id="sip-custom-add"  value="<?php echo $add_field ?>" />
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="otherw-help" class="help-block fpbx-help-block"><?php echo _("You may set any other SIP settings not present here that are allowed to be configured in the General section of sip.conf. There will be no error checking against these settings so check them carefully. They should be entered as:<br /> [setting] = [value]<br /> in the boxes below. Click the Add Field box to add additional fields. Blank boxes will be deleted when submitted.")?></span>
			</div>
		</div>
	</div>
	<!--END Other SIP Settings-->
</div>

<script language="javascript">
$(document).ready(function(){
	<?php
		/* this will insert the addClass jquery calls to all id's in error */
		if (!empty($error_displays)) {
			foreach ($error_displays as $js_disp) {
				echo "  ".$js_disp['js'];
			}
		}
	?>
});
</script>

<?php

/********** UTILITY FUNCTIONS **********/



function sipsettings_check_custom_files() {
	global $amp_conf;
	$errors = array();

	$custom_files[] = "sip_nat.conf";
	$custom_files[] = "sip_general_custom.conf";
	$custom_files[] = "sip_custom.conf";

	foreach ($custom_files as $file) {
		if (file_exists($amp_conf['ASTETCDIR']."/".$file)) {
			$sip_conf = \FreePBX::LoadConfig()->getConfig($file);
			$sip_conf = is_array($sip_conf) ? $sip_conf : array();
			foreach ($sip_conf as $section => $item) {
				// If setting is an array, then it is a subsection
				//
				if (!is_array($item)) {
					$msg =  sprintf(_("Settings in %s may override these. Those settings should be removed."),"<b>$file</b>");
					$errors[] = array( 'js' => '', 'div' => $msg);
					break;
				}
			}
		}
	}
	return $errors;
}
