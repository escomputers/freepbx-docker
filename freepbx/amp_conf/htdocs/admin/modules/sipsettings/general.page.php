<?php
// vim: set ai ts=4 sw=4 ft=phtml:
	$localnets 				= $this->getConfig('localnets');
	if (!$localnets) {
		$localnets 			= array();
	}

	$externip = $this->getConfig('externip');
	if (!$externip) {
		$externip 			= ""; // Ensure any failure is always an empty string
	}


	$ice_blacklist 			= $this->getConfig('ice-blacklist');
	$ice_blacklist 			= !empty($ice_blacklist) ? $ice_blacklist : array(array("address" => "","subnet" => ""));
	$ice_host_candidates 	= $this->getConfig('ice-host-candidates');
	$ice_host_candidates 	= !empty($ice_host_candidates) ? $ice_host_candidates : array(array("local" => "","advertised" => ""));

	$add_local_network_field= _("Add Local Network Field");
	$submit_changes 		= _("Submit Changes");

	$sip_settings 			= sipsettings_get();

	// With the new sorting, the vars should come to us in the sorted order so just use that
	//

	$sip_settings['videosupport']     	= isset($_POST['videosupport']) 	? $_POST['videosupport'] 						: $sip_settings['videosupport'];
	$sip_settings['maxcallbitrate']    	= isset($_POST['maxcallbitrate']) 	? htmlspecialchars($_POST['maxcallbitrate']) 	: $sip_settings['maxcallbitrate'];
	$sip_settings['g726nonstandard']   	= isset($_POST['g726nonstandard']) 	? $_POST['g726nonstandard'] 					: $sip_settings['g726nonstandard'];
	$sip_settings['t38pt_udptl']       	= isset($_POST['t38pt_udptl']) 		? $_POST['t38pt_udptl'] 						: $sip_settings['t38pt_udptl'];
	$sip_settings['allowguest']        	= isset($_POST['allowguest']) 		? $_POST['allowguest'] 							: $sip_settings['allowguest'];
	$sip_settings['rtptimeout']        	= isset($_POST['rtptimeout']) 		? htmlspecialchars($_POST['rtptimeout']) 		: $sip_settings['rtptimeout'];
	$sip_settings['rtpholdtimeout']    	= isset($_POST['rtpholdtimeout']) 	? htmlspecialchars($_POST['rtpholdtimeout']) 	: $sip_settings['rtpholdtimeout'];
	$sip_settings['rtpkeepalive']      	= isset($_POST['rtpkeepalive']) 	? htmlspecialchars($_POST['rtpkeepalive']) 		: $sip_settings['rtpkeepalive'];
	$sip_settings['rtpstart']      	    = isset($_POST['rtpstart']) 		? htmlspecialchars($_POST['rtpstart']) 			: '10000';
	$sip_settings['rtpend']             = isset($_POST['rtpend']) 			? htmlspecialchars($_POST['rtpend']) 			: '20000';
	$action 							= isset($_POST['Submit'])			? $_POST['Submit']								: '';
	extract($sip_settings);
	$driverType = $this->FreePBX->Config->get_conf_setting('ASTSIPDRIVER');
?>
<?php if($driverType === 'both') { ?>
	<div class="alert alert-info" role="alert"><?php echo _("These settings apply to both 'SIP Settings [chan_pjsip]' and 'Sip Legacy Settings [chan_sip]'."); ?></div>
<?php } ?>
<input type="hidden" name="category" value="general">
<input type="hidden" name="Submit" value="Submit">
<div class="section-title" data-for="sssecurity">
	<h3><i class="fa fa-minus"></i><?php echo _("Security Settings") ?></h3>
</div>
<div class="section" data-id="sssecurity">
	<!--Allow Anonymous Inbound SIP Calls-->
	<div class="element-container">
		<div class="row">
			<div class="form-group">
				<div class="col-md-3">
					<label class="control-label" for="allowanon"><?php echo _("Allow Anonymous Inbound SIP Calls") ?></label>
					<i class="fa fa-question-circle fpbx-help-icon" data-for="allowanon"></i>
				</div>
				<div class="col-md-9 radioset">
					<input type="radio" name="allowanon" id="allowanonyes" value="Yes" <?php echo ($this->getConfig("allowanon") == "Yes"?"CHECKED":"") ?>>
					<label for="allowanonyes"><?php echo _("Yes");?></label>
					<input type="radio" name="allowanon" id="allowanonno" value="No" <?php echo ($this->getConfig("allowanon") == "Yes"?"":"CHECKED") ?>>
					<label for="allowanonno"><?php echo _("No");?></label>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="allowanon-help" class="help-block fpbx-help-block"><?php echo _("Allowing Inbound Anonymous SIP calls means that you will allow any call coming in form an un-known IP source to be directed to the 'from-pstn' side of your dialplan. This is where inbound calls come in. Although FreePBX severely restricts access to the internal dialplan, allowing Anonymous SIP calls does introduced additional security risks. If you allow SIP URI dialing to your PBX or use services like ENUM, you will be required to set this to Yes for Inbound traffic to work. This is NOT an Asterisk sip.conf setting, it is used in the dialplan in conjuction with the Default Context. If that context is changed above to something custom this setting may be rendered useless as well as if 'Allow SIP Guests' is set to no.")?></span>
			</div>
		</div>
	</div>
	<!--END Allow Anonymous Inbound SIP Calls-->
	<!--Allow SIP Guests-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="allowguest"><?php echo _("Allow SIP Guests") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="allowguest"></i>
						</div>
						<div class="col-md-9 radioset">
							<input id="allowguest-yes" type="radio" name="allowguest" value="yes" <?php echo $allowguest=="yes"?"checked=\"yes\"":""?>/>
							<label for="allowguest-yes"><?php echo _("Yes") ?></label>
							<input id="allowguest-no" type="radio" name="allowguest" value="no" <?php echo $allowguest=="no"?"checked=\"no\"":""?>/>
							<label for="allowguest-no"><?php echo _("No") ?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="allowguest-help" class="help-block fpbx-help-block"><?php echo _("When set Asterisk will allow Guest SIP calls and send them to the Default SIP context. Turning this off will keep anonymous SIP calls from entering the system. Doing such will also stop 'Allow Anonymous Inbound SIP Calls' from functioning. Allowing guest calls but rejecting the Anonymous SIP calls below will enable you to see the call attempts and debug incoming calls that may be mis-configured and appearing as guests.")?></span>
			</div>
		</div>
	</div>
	<!--END Allow SIP Guests-->
	<!-- TLS Port Settings -->
	<div class="element-container">
		<div class="row">
			<div class="form-group">
				<div class="col-md-3">
					<label class="control-label" for="tlsowner"><?php echo _("Default TLS Port Assignment") ?></label>
					<i class="fa fa-question-circle fpbx-help-icon" data-for="tlsowner"></i>
				</div>
				<div class="col-md-9 radioset">
<?php
$tlsowners = array("sip" => _("Chan SIP"), "pjsip" => _("PJSip"));
$owner = $this->getTlsPortOwner();
$binds = $this->getBinds();
foreach ($tlsowners as $chan => $txt) {
	if ($owner === $chan) {
		$checked = "checked";
	} else {
		$checked = "";
	}

	// Is this protocol available?
	if (isset($binds[$chan])) {
		// Is it listening for TLS anywhere?
		$foundtls = false;
		foreach ($binds[$chan] as $protocols) {
			foreach ($protocols as $p => $pport) {
				if ($p == "tls") {
					$foundtls = true;
					break;
				}
			}
		}
		if ($foundtls) {
			$disabled = "";
		} else {
			$disabled = "disabled";
		}
	} else {
		$disabled = "disabled";
	}
	print "<input type='radio' name='tlsportowner' id='tls-$chan' value='$chan' $disabled $checked>\n";
	print "<label for='tls-$chan'>$txt</label>\n";
}
?>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="tlsowner-help" class="help-block fpbx-help-block"><?php echo _("This lets you explicitly control the SIP Protocol that listens on the default SIP TLS port (5061). If an option is not available, it is because that protocol is not enabled, or, that protocol does not have TLS enabled. If you change this, you will have to restart Asterisk"); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="section-title" data-for="ssnat">
	<h3><i class="fa fa-minus"></i><?php echo _("NAT Settings") ?></h3>
</div>
<div class="section" data-id="ssnat">
	<!--External Address-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="externip"><?php echo _("External Address") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="externip"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control localnet validate=ip" id="externip" name="externip" value="<?php echo $externip ?>">
							<button class='btn btn-default' id='autodetect'><?php echo _("Detect Network Settings")?></button>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="externip-help" class="help-block fpbx-help-block"><?php echo _("This address will be provided to clients if NAT is enabled and detected")?></span>
			</div>
		</div>
	</div>
	<!--END External Address-->
	<?php
	// Are there any MORE nets?
	// Remove the first one that we've displayed
	$localnetstmp = $localnets;
	unset ($localnetstmp[0]);
	// Now loop through any more, if they exist.
	$lnhtm = '';
	foreach ($localnetstmp as $id => $arr) {
		$lnhtm .= '<div class = "lnet form-group form-inline" data-nextid='.($id+1).'>';
		$lnhtm .= '	<input type="text" name="localnets['.$id.'][net]" class="form-control localnet network validate-ip" value="'.$arr['net'].'">/';
		$lnhtm .= '	<input type="text" name="localnets['.$id.'][mask]" class="form-control netmask cidr validate-netmask" value="'.$arr['mask'].'">';
		$lnhtm .= '</div>';
	}
	?>
	<!--Local Networks-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="localbnetsw"><?php echo _("Local Networks") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="localbnetsw"></i>
						</div>
						<div class="col-md-9">
							<div class = "lnet form-group form-inline" data-nextid=1>
								<input type="text" name="localnets[0][net]" class="form-control localnet network validate-ip"  value="<?php echo isset($localnets[0]['net']) ? $localnets[0]['net'] : '' ?>"> /
								<input type="text" name="localnets[0][mask]" class="form-control netmask cidr validate-netmask" value="<?php echo isset($localnets[0]['mask']) ? $localnets[0]['mask'] : ''?>">
							</div>
							<?php echo $lnhtm?>
							<input type="button" id="localnet-add" value="<?php echo $add_local_network_field ?>" />
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="localbnetsw-help" class="help-block fpbx-help-block"><?php echo _("Local network settings in the form of ip/cidr or ip/netmask. For networks with more than 1 LAN subnets, use the Add Local Network Field button for more fields. Blank fields will be ignored.")?></span>
			</div>
		</div>
	</div>
	<!--END Local Networks-->
</div>
<div class="section-title" data-for="ssrtp">
	<h3><i class="fa fa-minus"></i><?php echo _("RTP Settings") ?></h3>
</div>
<div class="section" data-id="ssrtp">
	<!--RTP Port Ranges-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="rtpw"><?php echo _("RTP Port Ranges") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="rtpw"></i>
						</div>
						<div class="col-md-9">
							<div class="row">
								<div class="col-sm-1">
									<label for="rtpstart"><b><?php echo _("Start").":"?></b></label>
								</div>
								<div class="col-sm-11">
									<input type='number' name='rtpstart' class='form-control validate-int' value='<?php echo $this->getConfig('rtpstart')?>'>
								</div>
							</div>
							<div class="row">
								<div class="col-sm-1">
									<label for="rtpend"><b><?php echo _("End").":"?></b></label>
								</div>
								<div class="col-sm-11">
									<input type='number' name='rtpend'   class='form-control validate-int' value='<?php echo $this->getConfig('rtpend') ?>'>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="rtpw-help" class="help-block fpbx-help-block"><?php echo _("The starting and ending RTP port range")?></span>
			</div>
		</div>
	</div>
	<!--END RTP Port Ranges-->
	<!--RTP Checksums-->
	<div class="element-container">
		<div class="row">
			<div class="form-group">
				<div class="col-md-3">
					<label class="control-label" for="rtpchecksums"><?php echo _("RTP Checksums") ?></label>
					<i class="fa fa-question-circle fpbx-help-icon" data-for="rtpchecksums"></i>
				</div>
				<div class="col-md-9 radioset">
					<input type="radio" name="rtpchecksums" id="rtpchecksumsyes" value="yes" <?php echo (strtolower($this->getConfig("rtpchecksums")) == "yes"?"checked":"") ?>>
					<label for="rtpchecksumsyes"><?php echo _("Yes");?></label>
					<input type="radio" name="rtpchecksums" id="rtpchecksumsno" value="No" <?php echo (strtolower($this->getConfig("rtpchecksums")) != "yes"?"checked":"") ?>>
					<label for="rtpchecksumsno"><?php echo _("No");?></label>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="rtpchecksums-help" class="help-block fpbx-help-block"><?php echo _("Whether to enable or disable UDP checksums on RTP traffic")?></span>
			</div>
		</div>
	</div>
	<!--END RTP Checksums-->
	<!--Strict RTP-->
	<div class="element-container">
		<div class="row">
			<div class="form-group">
				<div class="col-md-3 radioset">
					<label class="control-label" for="strictrtp"><?php echo _("Strict RTP") ?></label>
					<i class="fa fa-question-circle fpbx-help-icon" data-for="strictrtp"></i>
				</div>
				<div class="col-md-9 radioset">
					<input type="radio" name="strictrtp" id="strictrtpyes" value="Yes" <?php echo (strtolower($this->getConfig("strictrtp")) == "yes"?"checked":"") ?>>
					<label for="strictrtpyes"><?php echo _("Yes");?></label>
					<input type="radio" name="strictrtp" id="strictrtpno" value="No" <?php echo (strtolower($this->getConfig("strictrtp")) != "yes"?"checked":"") ?>>
					<label for="strictrtpno"><?php echo _("No");?></label>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="strictrtp-help" class="help-block fpbx-help-block"><?php echo _("This will drop RTP packets that do not come from the source of the RTP stream. It is unusual to turn this off")?></span>
			</div>
		</div>
	</div>
	<!--END Strict RTP-->
	<!--RTP Timeout-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="rtptimeout"><?php echo _("RTP Timeout") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="rtptimeout"></i>
						</div>
						<div class="col-md-9">
							<input type="number" class="form-control" id="rtptimeout" name="rtptimeout" value="<?php echo $rtptimeout ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="rtptimeout-help" class="help-block fpbx-help-block"><?php echo _("Terminate call if rtptimeout seconds of no RTP or RTCP activity on the audio channel when we're not on hold. This is to be able to hangup a call in the case of a phone disappearing from the net, like a powerloss or someone tripping over a cable.")?></span>
			</div>
		</div>
	</div>
	<!--END RTP Timeout-->
	<!--RTP Hold Timeout-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="rtpholdtimeout"><?php echo _("RTP Hold Timeout") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="rtpholdtimeout"></i>
						</div>
						<div class="col-md-9">
							<input type="number" class="form-control" id="rtpholdtimeout" name="rtpholdtimeout" value="<?php echo $rtpholdtimeout ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="rtpholdtimeout-help" class="help-block fpbx-help-block"><?php echo _("Terminate call if rtpholdtimeout seconds of no RTP or RTCP activity on the audio channel when we're on hold (must be > rtptimeout).")?></span>
			</div>
		</div>
	</div>
	<!--END RTP Hold Timeout-->
	<!--RTP Keep Alive-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="rtpkeepalive"><?php echo _("RTP Keep Alive") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="rtpkeepalive"></i>
						</div>
						<div class="col-md-9">
							<input type="number" class="form-control" id="rtpkeepalive" name="rtpkeepalive" value="<?php echo $rtpkeepalive ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="rtpkeepalive-help" class="help-block fpbx-help-block"><?php echo _("Send keepalives in the RTP stream to keep NAT open during periods where no RTP stream may be flowing (like on hold).")?></span>
			</div>
		</div>
	</div>
	<!--END RTP Keep Alive-->
</div>
<div class="section-title" data-for="ssmts">
	<h3><i class="fa fa-minus"></i><?php echo _("Media Transport Settings") ?></h3>
</div>
<div class="section" data-id="ssmts">
	<!--STUN Server Address-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="stunaddr"><?php echo _("STUN Server Address") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="stunaddr"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="stunaddr" name="stunaddr" value="<?php echo $this->getConfig('stunaddr') ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="stunaddr-help" class="help-block fpbx-help-block"><?php echo _("Hostname or address for the STUN server used when determining the external IP address and port an RTP session can be reached at. The port number is optional. If omitted the default value of 3478 will be used. This option is blank by default. (A list of STUN servers: http://wiki.freepbx.org/x/YQCUAg)")?></span>
			</div>
		</div>
	</div>
	<!--END STUN Server Address-->
	<!--TURN Server Address-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="turnaddr"><?php echo _("TURN Server Address") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="turnaddr"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="turnaddr" name="turnaddr" value="<?php echo $this->getConfig('turnaddr') ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="turnaddr-help" class="help-block fpbx-help-block"><?php echo _("Hostname or address for the TURN server to be used as a relay. The port number is optional. If omitted the default value of 3478 will be used. This option is blank by default.")?></span>
			</div>
		</div>
	</div>
	<!--END TURN Server Address-->
	<!--TURN Server Username-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="turnusername"><?php echo _("TURN Server Username") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="turnusername"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="turnusername" name="turnusername" value="<?php echo $this->getConfig('turnusername') ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="turnusername-help" class="help-block fpbx-help-block"><?php echo _("Username used to authenticate with TURN relay server. This option is disabled by default.")?></span>
			</div>
		</div>
	</div>
	<!--END TURN Server Username-->
	<!--TURN Server Password-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="turnpassword"><?php echo _("TURN Server Password") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="turnpassword"></i>
						</div>
						<div class="col-md-9">
							<input type="password" class="form-control clicktoedit" id="turnpassword" name="turnpassword" value="<?php echo $this->getConfig('turnpassword') ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="turnpassword-help" class="help-block fpbx-help-block"><?php echo _("Password used to authenticate with TURN relay server. This option is disabled by default.")?></span>
			</div>
		</div>
	</div>
	<!--END TURN Server Password-->
</div>
<div class="section-title" data-for="ice-blacklist">
	<h3><i class="fa fa-minus"></i><?php echo _("ICE Blacklist") ?></h3>
</div>
<div class="section" data-id="ice-blacklist">
	<div class="panel panel-info">
		<div class="panel-heading">
			<div class="panel-title">
				<a data-toggle="collapse" data-target="#moreinfo-ice-blacklist" style="cursor:pointer;"><i class="glyphicon glyphicon-info-sign"></i></a>&nbsp;&nbsp;&nbsp;<?php echo _("What is ICE Blacklist?")?></div>
		</div>
		<!--At some point we can probably kill this... Maybe make is a 1 time panel that may be dismissed-->
		<div class="panel-body collapse" id="moreinfo-ice-blacklist">
			<p><?php echo _("Subnets to exclude from ICE host, srflx and relay discovery. This is useful to optimize the ICE process where a system has multiple host address ranges and/or physical interfaces and certain of them are not expected to be used for RTP. For example, VPNs and local interconnections may not be suitable or necessary for ICE. Multiple subnets may be listed. If left unconfigured, all discovered host addresses are used.")?></p>
			<p><?php echo _("The format for these overrides is: [address] / [subnet]")?></p>
			<p><?php echo _("This is most commonly used for WebRTC")?></p>
		</div>
	</div>
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for=""><?php echo _("IP Addresses")?></label>
						</div>
						<div class="col-md-9">
							<?php $i = 0;foreach($ice_blacklist as $can) {?>
								<div class="form-group form-inline">
									<input type="hidden" id="ice_blacklist_count" name="ice_blacklist_count[]" value="<?php echo $i?>"><input type="text" id="ice_blacklist_ip_<?php echo $i?>" name="ice_blacklist_ip_<?php echo $i?>" class="form-control ice-blacklist" value="<?php echo $can['address']?>"> /
									<input type="text" id="ice_blacklist_subnet_<?php echo $i?>" name="ice_blacklist_subnet_<?php echo $i?>" class="form-control"   value="<?php echo $can['subnet']?>">
								</div>
							<?php $i++;} ?>
							<div id="ice-blacklist-buttons">
								<div>
									<button id="ice-blacklist-add" class="btn btn-default"><?php echo _("Add Address")?></button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<div class="section-title" data-for="ice-host-candidates">
	<h3><i class="fa fa-minus"></i><?php echo _("ICE Host Candidates") ?></h3>
</div>
<div class="section" data-id="ice-host-candidates">
	<div class="panel panel-info">
		<div class="panel-heading">
			<div class="panel-title">
				<a data-toggle="collapse" data-target="#moreinfo-ice-host-candidates" style="cursor:pointer;"><i class="glyphicon glyphicon-info-sign"></i></a>&nbsp;&nbsp;&nbsp;<?php echo _("What is ICE Host Candidates?")?></div>
		</div>
		<!--At some point we can probably kill this... Maybe make is a 1 time panel that may be dismissed-->
		<div class="panel-body collapse" id="moreinfo-ice-host-candidates">
			<p><?php echo _("When Asterisk is behind a static one-to-one NAT and ICE is in use, ICE will expose the server's internal IP address as one of the host candidates. Although using STUN (see the 'stunaddr' configuration option) will provide a publicly accessible IP, the internal IP will still be sent to the remote peer. To help hide the topology of your internal network, you can override the host candidates that Asterisk will send to the remote peer.")?></p>
			<p><?php echo _("IMPORTANT: Only use this functionality when your Asterisk server is behind a one-to-one NAT and you know what you're doing. If you do define anything here, you almost certainly will NOT want to specify 'stunaddr' or 'turnaddr' above.")?></p>
			<p><?php echo _("The format for these overrides is: [local address] => [advertised address]>")?></p>
			<p><?php echo _("This is most commonly used for WebRTC")?></p>
		</div>
	</div>
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for=""><?php echo _("Candidates")?></label>
						</div>
						<div class="col-md-9">
							<?php $i = 0;foreach($ice_host_candidates as $can) {?>
								<div class="form-group form-inline">
									<input type="hidden" id="ice_host_candidates_count" name="ice_host_candidates_count[]" value="<?php echo $i?>"><input type="text" id="ice_host_candidates_local_<?php echo $i?>" name="ice_host_candidates_local_<?php echo $i?>" class="form-control ice-host-candidate" value="<?php echo $can['local']?>"> => <input type="text" id="ice_host_candidates_advertised_<?php echo $i?>" name="ice_host_candidates_advertised_<?php echo $i?>" class="form-control" value="<?php echo $can['advertised']?>">
								</div>
							<?php } ?>
							<div id="ice-host-candidates-buttons">
								<button id="ice-host-candidates-add" class="btn btn-default"><?php echo _("Add Address")?></button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<div class="section-title" data-for="webrtc">
	<h3><i class="fa fa-minus"></i><?php echo _("WebRTC Settings") ?></h3>
</div>
<div class="section" data-id="webrtc">
	<!--STUN Server Address-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="webrtcstunaddr"><?php echo _("STUN Server Address") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="webrtcstunaddr"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="webrtcstunaddr" name="webrtcstunaddr" value="<?php echo $this->getConfig('webrtcstunaddr') ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="webrtcstunaddr-help" class="help-block fpbx-help-block"><?php echo _("Hostname or address for the STUN server used when determining the external IP address and port an RTP session can be reached at. The port number is optional. If omitted the default value of 3478 will be used. This option is blank by default. (A list of STUN servers: http://wiki.freepbx.org/x/YQCUAg)")?></span>
			</div>
		</div>
	</div>
	<!--END STUN Server Address-->
	<!--TURN Server Address-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="webrtcturnaddr"><?php echo _("TURN Server Address") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="webrtcturnaddr"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="webrtcturnaddr" name="webrtcturnaddr" value="<?php echo $this->getConfig('webrtcturnaddr') ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="webrtcturnaddr-help" class="help-block fpbx-help-block"><?php echo _("Hostname or address for the TURN server to be used as a relay. The port number is optional. If omitted the default value of 3478 will be used. This option is blank by default.")?></span>
			</div>
		</div>
	</div>
	<!--END TURN Server Address-->
	<!--TURN Server Username-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="webrtcturnusername"><?php echo _("TURN Server Username") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="webrtcturnusername"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="webrtcturnusername" name="webrtcturnusername" value="<?php echo $this->getConfig('webrtcturnusername') ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="webrtcturnusername-help" class="help-block fpbx-help-block"><?php echo _("Username used to authenticate with TURN relay server. This option is disabled by default.")?></span>
			</div>
		</div>
	</div>
	<!--END TURN Server Username-->
	<!--TURN Server Password-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="webrtcturnpassword"><?php echo _("TURN Server Password") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="webrtcturnpassword"></i>
						</div>
						<div class="col-md-9">
							<input type="password" class="form-control clicktoedit" id="webrtcturnpassword" name="webrtcturnpassword" value="<?php echo $this->getConfig('webrtcturnpassword') ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="webrtcturnpassword-help" class="help-block fpbx-help-block"><?php echo _("Password used to authenticate with TURN relay server. This option is disabled by default.")?></span>
			</div>
		</div>
	</div>
</div>
<div class="section-title" data-for="sscodecs">
	<h3><i class="fa fa-minus"></i><?php echo _("Audio Codecs") ?></h3>
</div>
<div class="section" data-id="sscodecs">

	<!--T38 Pass-Through-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="t38pt_udptl"><?php echo _("T38 Pass-Through") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="t38pt_udptl"></i>
						</div>
						<div class="col-md-9">
							<select name="t38pt_udptl" class="form-control">
								<option value="no" <?php echo $t38pt_udptl=="no"?"selected":""?>><?php echo _("No")?></option>
								<option value="yes" <?php echo $t38pt_udptl=="yes"?"selected":""?>><?php echo _("Yes")?></option>
								<option value="fec" <?php echo $t38pt_udptl=="fec"?"selected":""?>><?php echo _("Yes with FEC")?></option>
								<option value="redundancy" <?php echo $t38pt_udptl=="redundancy"?"selected":""?>><?php echo _("Yes with Redundancy")?></option>
								<option value="none" <?php echo $t38pt_udptl=="none"?"selected":""?>><?php echo _("Yes with no error correction")?></option>
							</select>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="t38pt_udptl-help" class="help-block fpbx-help-block"><?php echo _("Asterisk: t38pt_udptl. Enables T38 passthrough which makes faxes go through Asterisk without being processed.<ul><li>No - No passthrough</li><li>Yes - Enables T.38 with FEC error correction and overrides the other endpoint's provided value to assume we can send 400 byte T.38 FAX packets to it.</li><li>Yes with FEC - Enables T.38 with FEC error correction</li><li>Yes with Redundancy - Enables T.38 with redundancy error correction</li><li>Yes with no error correction - Enables T.38 with no error correction.</li></ul>")?></span>
			</div>
		</div>
	</div>
	<!--END T38 Pass-Through-->

	<!--Codecs-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="codecw"><?php echo _("Codecs") ?></label>
						</div>
						<div class="col-md-9">
							<?php echo \show_help( _("This is the default Codec setting for new Trunks and Extensions."),_("Helpful Information"),false, true, 'info');
							$seq 			= 1;

							echo '<ul class="sortable">';
							foreach (FreePBX::Sipsettings()->getCodecs('audio',true) as $codec => $codec_state) {
								if($sip_settings["g726nonstandard"] == "no" && $codec == "g726aal2"){
									// Don't display this codec into the list.
								}
								else {
									$codec_trans = _($codec);
									$codec_checked = $codec_state ? 'checked' : '';
									echo '<li><a >'
										. '<img src="assets/sipsettings/images/arrow_up_down.png" height="16" width="16" border="0" alt="move" style="float:none; margin-left:-6px; margin-bottom:-3px;cursor:move" /> '
										. '<input type="checkbox" '
										. ($codec_checked ? 'value="'. $seq++ . '" ' : '')
										. 'name="voicecodecs[' . $codec . ']" '
										. 'id="'. $codec . '" '
										. 'class="audio-codecs" '
										. $codec_checked
										. ' />'
										. '<label for="'. $codec . '"> '
										. '<small>' . $codec_trans . '</small>'
										. " </label></a></li>\n";
								}
							}
							echo '</ul>';
							?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<!--END Codecs-->
</div>
<?php

	/* We massaged these above or they came from sipsettings_get() if this is not
	 * from and edit. So extract them after sorting out the codec sub arrays.
	 */

	$video_codecs 		= FreePBX::Sipsettings()->getCodecs('video',true);
	foreach($video_codecs as $key => $value){
		$v_codecs[$key] = "";
	}
	$post_video_codecs 	= isset($_POST['vcodec']) ? $_POST['vcodec'] : array();
	$seq 				= 1;
	$newvcodecs			= array();
	$vcodec = false;
	foreach ($post_video_codecs as $key => $value) {
		$newvcodecs[$key] = $seq++;
		$vcodec = True;
	}

	$video_codecs		= ($vcodec)? array_merge($v_codecs, $newvcodecs) : $video_codecs ;

	uasort($video_codecs, function($a, $b) {
		if ($a == $b) {
			return 0;
		}
		if ($a == '') {
			return 1;
		} elseif ($b == '') {
			return -1;
		} else {
			return ($a > $b) ? 1 : -1;
		}
	});

	/* EXTRACT THE VARIABLE HERE - MAKE SURE THEY ARE ALL MASSAGED ABOVE */
	//

	switch ($action) {
		case "Submit":  // Save sip settings
			if (($errors = sipsettings_edit($sip_settings)) !== true) {
				$error_displays = sipsettings_process_errors($errors);
			}
		break;
		default:
			/* only get them if first time load, if they pressed submit, use values from POST */
			$sip_settings = sipsettings_get();
			extract($sip_settings);
	}
	unset($_POST['Submit']); 	unset($action);

?>
<div class="section-title" data-for="sscsvcodecs">
	<h3><i class="fa fa-minus"></i> <?php echo _("Video Codecs")?></h3>
</div>
<div class="section" data-id="sscsvcodecs">
	<!--Video Support-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="videosupport"><?php echo _("Video Support") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="videosupport"></i>
						</div>
						<div class="col-md-9 radioset">
							<input id="videosupport-yes" type="radio" name="videosupport" value="yes" <?php echo $videosupport=="yes"?"checked=\"yes\"":""?>/>
							<label for="videosupport-yes"><?php echo _("Enabled") ?></label>
							<input id="videosupport-no" type="radio" name="videosupport" value="no" <?php echo $videosupport=="no"?"checked=\"no\"":""?>/>
							<label for="videosupport-no"><?php echo _("Disabled") ?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="videosupport-help" class="help-block fpbx-help-block"><?php echo _("Check to enable and then choose allowed codecs.")._(" If you clear each codec and then add them one at a time, submitting with each addition, they will be added in order which will effect the codec priority.")?></span>
			</div>
		</div>
	</div>
	<!--END Video Support-->
	<div class="video-codecs">
		<!--Video Codecs-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="vcwrap"><?php echo _("Video Codecs") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="vcwrap"></i>
							</div>
							<div class="col-md-9">
								<?php
								$seq = 0;
								echo '<ul  class="sortable video-codecs">';
									 foreach ($video_codecs as $codec => $codec_state) {
										$tabindex++;
										$codec_trans = _($codec);
										$codec_checked = $codec_state ? 'checked' : '';
										echo '<li><a >'
											. '<img src="assets/sipsettings/images/arrow_up_down.png" height="16" width="16" border="0" alt="move" style="float:none; margin-left:-6px; margin-bottom:-3px;cursor:move" /> '
											. '<input type="checkbox" '
											. ($codec_checked ? 'value="'. $seq++ . '" ' : '')
											. 'name="vcodec[' . $codec . ']" '
											. 'id="'. $codec . '" '
											. 'class="audio-codecs" tabindex="' . $tabindex. '" '
											. $codec_checked
											. ' />'
											. '<label for="'. $codec . '"> '
											. '<small>' . $codec_trans . '</small>'
											. ' </label></a></li>';
									}
									echo '</ul>';

								?>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="vcwrap-help" class="help-block fpbx-help-block"><?php echo _("Video Codecs")?></span>
				</div>
			</div>
		</div>
		<!--END Video Codecs-->
		<!--Max Bit Rate-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="maxcallbitrate"><?php echo _("Max Bit Rate") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="maxcallbitrate"></i>
							</div>
							<div class="col-md-9">
								<div class="input-group">
									<input type="number" class="form-control" id="maxcallbitrate" name="maxcallbitrate" value="<?php echo $maxcallbitrate ?>">
									<span class="input-group-addon"><?php echo _("kb/s") ?></span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="maxcallbitrate-help" class="help-block fpbx-help-block"><?php echo _("Maximum bitrate for video calls in kb/s")?></span>
				</div>
			</div>
		</div>
		<!--END Max Bit Rate-->
	</div>
</div>
