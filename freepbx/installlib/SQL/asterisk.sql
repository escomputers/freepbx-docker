INSERT INTO `admin` VALUES ('need_reload','true'),('default_directory','1'),('directory28_migrated','1');
INSERT INTO `cronmanager` VALUES ('module_admin','UPDATES','22',24,0,'/var/lib/asterisk/bin/module_admin listonline > /dev/null 2>&1');
INSERT INTO `featurecodes` VALUES ('core','userlogon','User Logon','','*11',NULL,0,0),('core','userlogoff','User Logoff','','*12',NULL,0,0),('core','zapbarge','ZapBarge','','888',NULL,1,1),('core','chanspy','ChanSpy','','555',NULL,0,0),('core','simu_pstn','Simulate Incoming Call','','7777',NULL,1,1),('core','pickup','Directed Call Pickup','','**',NULL,1,0),('core','pickupexten','Asterisk General Call Pickup','','*8',NULL,1,0),('core','blindxfer','In-Call Asterisk Blind Transfer','','##',NULL,1,0),('core','atxfer','In-Call Asterisk Attended Transfer','','*2',NULL,1,0),('core','automon','In-Call Asterisk Toggle Call Recording','','*1',NULL,1,0),('core','disconnect','In-Call Asterisk Disconnect Code','','**',NULL,1,0),('pbdirectory','app-pbdirectory','Phonebook dial-by-name directory','','411',NULL,1,1),('donotdisturb','dnd_on','DND Activate','','*78',NULL,1,0),('donotdisturb','dnd_off','DND Deactivate','','*79',NULL,1,0),('donotdisturb','dnd_toggle','DND Toggle','','*76',NULL,1,0),('recordings','record_save','Save Recording','','*77',NULL,1,0),('recordings','record_check','Check Recording','','*99',NULL,1,0),('callwaiting','cwon','Call Waiting - Activate','','*70',NULL,1,0),('callwaiting','cwoff','Call Waiting - Deactivate','','*71',NULL,1,0),('voicemail','myvoicemail','My Voicemail','','*97',NULL,1,0),('voicemail','dialvoicemail','Dial Voicemail','','*98',NULL,1,1),('voicemail','directdialvoicemail','Direct Dial Prefix','','*',NULL,1,0),('paging','intercom-prefix','Intercom prefix','','*80',NULL,1,0),('paging','intercom-on','User Intercom Allow','','*54',NULL,1,0),('paging','intercom-off','User Intercom Disallow','','*55',NULL,1,0),('blacklist','blacklist_add','Blacklist a number','','*30',NULL,1,1),('blacklist','blacklist_remove','Remove a number from the blacklist','','*31',NULL,1,1),('blacklist','blacklist_last','Blacklist the last caller','','*32',NULL,1,0),('fax','simu_fax','Dial System FAX','','666',NULL,1,1),('dictate','dodictate','Perform dictation','','*34',NULL,1,0),('dictate','senddictate','Email completed dictation','','*35',NULL,1,0),('findmefollow','fmf_toggle','Findme Follow Toggle','','*21',NULL,1,0),('campon','request','Camp-On Request','','*82',NULL,1,0),('campon','cancel','Camp-On Cancel','','*83',NULL,1,0),('campon','toggle','Camp-On Toggle','','*84',NULL,1,0),('parking','parkedcall','Pickup ParkedCall Prefix','','*85',NULL,1,1),('infoservices','calltrace','Call Trace','','*69',NULL,1,0),('infoservices','echotest','Echo Test','','*43',NULL,1,1),('infoservices','speakingclock','Speaking Clock','','*60',NULL,1,1),('infoservices','speakextennum','Speak Your Exten Number','','*65',NULL,1,0),('callforward','cfon','Call Forward All Activate','','*72',NULL,1,0),('callforward','cfoff','Call Forward All Deactivate','','*73',NULL,1,0),('callforward','cfoff_any','Call Forward All Prompting Deactivate','','*74',NULL,1,0),('callforward','cfbon','Call Forward Busy Activate','','*90',NULL,1,0),('callforward','cfboff','Call Forward Busy Deactivate','','*91',NULL,1,0),('callforward','cfboff_any','Call Forward Busy Prompting Deactivate','','*92',NULL,1,0),('callforward','cfuon','Call Forward No Answer/Unavailable Activate','','*52',NULL,1,0),('callforward','cfuoff','Call Forward No Answer/Unavailable Deactivate','','*53',NULL,1,0),('callforward','cf_toggle','Call Forward Toggle','','*740',NULL,1,0),('queues','que_toggle','Queue Toggle','','*45',NULL,1,0),('queues','que_pause_toggle','Queue Pause Toggle','','*46',NULL,1,0),('speeddial','callspeeddial','Speeddial prefix','','*0',NULL,1,0),('speeddial','setspeeddial','Set user speed dial','','*75',NULL,1,0),('hotelwakeup','hotelwakeup','Wake Up Calls','','*68',NULL,1,0);
UPDATE `freepbx_settings` SET `value`='chan_pjsip' WHERE `keyword`='ASTSIPDRIVER'; 
