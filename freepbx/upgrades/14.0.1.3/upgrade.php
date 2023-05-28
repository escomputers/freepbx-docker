<?php
$config = FreePBX::ModulesConf()->ProcessedConfig;
if(in_array('res_digium_phones.so',$config['modules']['noload']) && !in_array('res_pjsip_endpoint_identifier_dpma.so',$config['modules']['noload'])) {
	FreePBX::ModulesConf()->removenoload('res_digium_phones.so');
}
