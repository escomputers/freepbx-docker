<?php

$removeSettings = [
	'AST_FUNC_DEVICE_STATE',
	'AST_FUNC_EXTENSION_STATE',
	'AST_FUNC_PRESENCE_STATE',
	'AST_FUNC_SHARED',
	'AST_FUNC_CONNECTEDLINE',
	'AST_FUNC_MASTER_CHANNEL'
];
\FreePBX::Config()->remove_conf_settings($removeSettings);