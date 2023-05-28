<?php
try {
	$config = FreePBX::ModulesConf()->ProcessedConfig;
	if(in_array('res_digium_phones.so',$config['modules']['noload'])) {
		FreePBX::ModulesConf()->removenoload('res_digium_phones.so');
	}
} catch(\Exception $e) {}
