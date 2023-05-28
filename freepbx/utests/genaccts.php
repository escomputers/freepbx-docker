<?php

header("Pragma: public"); // required
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-Transfer-Encoding: binary");
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"randomaccounts.csv\";" );


$header = "action,extension,name,cid_masquerade,sipname,outboundcid,ringtimer,callwaiting,call_screen,pinless,password,noanswer_dest,noanswer_cid,busy_dest,busy_cid,chanunavail_dest,chanunavail_cid,emergency_cid,tech,hardware,devinfo_channel,devinfo_secret,devinfo_notransfer,devinfo_dtmfmode,devinfo_canreinvite,devinfo_context,devinfo_immediate,devinfo_signalling,devinfo_echocancel,devinfo_echocancelwhenbrdiged,devinfo_echotraining,devinfo_busydetect,devinfo_busycount,devinfo_callprogress,devinfo_host,devinfo_type,devinfo_nat,devinfo_port,devinfo_qualify,devinfo_callgroup,devinfo_pickupgroup,devinfo_disallow,devinfo_allow,devinfo_dial,devinfo_accountcode,devinfo_mailbox,devinfo_deny,devinfo_permit,devicetype,deviceid,deviceuser,description,dictenabled,dictformat,dictemail,langcode,vm,vmpwd,email,pager,attach,saycid,envelope,delete,options,vmcontext,vmx_state,vmx_unavail_enabled,vmx_busy_enabled,vmx_play_instructions,vmx_option_0_sytem_default,vmx_option_0_number,vmx_option_1_system_default,vmx_option_1_number,vmx_option_2_number,account,ddial,pre_ring,strategy,grptime,grplist,annmsg_id,ringing,grppre,dring,needsconf,remotealert_id,toolate_id,postdest,faxenabled,faxemail,cfringtimer,concurrency_limit,answermode,qnostate,devinfo_trustrpid,devinfo_sendrpid,devinfo_qualifyfreq,devinfo_transport,devinfo_encryption,devinfo_vmexten,cc_agent_policy,cc_monitor_policy,recording_in_external,recording_out_external,recording_in_internal,recording_out_internal,recording_ondemand,recording_priority,add_xactview,xactview_autoanswer,xactview_email,xactview_cell,jabber_host,jabber_domain,jabber_resource,jabber_port,jabber_username,jabber_password,xactview_createprofile,xactview_profilepassword,xmpp_user,xmpp_pass\n";


print $header;

$line = "add,::ext::,::name::,::ext::,,,0,enabled,0,disabled,,,,,,,,,sip,,,::sippw::,,rfc2833,no,from-internal,,,,,,,,,dynamic,friend,yes,5060,yes,,,,,SIP/::ext::,,::ext::@device,0.0.0.0/0.0.0.0,0.0.0.0/0.0.0.0,fixed,,4020,::name::,disabled,ogg,,,enabled,::vmpin::,,,attach=no,saycid=no,envelope=no,delete=no,,default,,,,checked,checked,,,,,,,,,,,,,,,,,,,true,,0,5,disabled,u,yes,no,60,udp,no,,generic,generic,dontcare,dontcare,dontcare,dontcare,disabled,10,1,0,,,,,XactView,5222,,,1,secret,\n";

$make = 500;

$names = file("http://random-name-generator.info/random/?n=${make}&g=1&st=2");
array_splice($names,0,131);
array_splice($names, -15);

foreach ($names as $name) {
	$newline = $line;
	if (preg_match("/\s+(\w+)\s+(\w+)\s+</", $name, $out)) {
		$found = false;
		while (!$found) {
			$e = mt_rand(100,999);
			if (!isset($inuse[$e])) {
				$found = true;
				$inuse[$e] = true;
			}
		}
		$ext['ext'] = $e;
		$ext['name'] = $out[1]." ".$out[2];
		$ext['vmpin'] = mt_rand(1111,9999);
		$ext['sippw'] = hash("sha1", $ext['ext'].$ext['vmpin']);

		foreach ($ext as $key => $val)
			$newline = preg_replace("/::${key}::/", $val, $newline);

		print $newline;
	}
}
