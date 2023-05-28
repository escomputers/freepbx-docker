<?php

namespace FreePBX\modules\SipSettings\Api\Gql;

use GraphQLRelay\Relay;
use GraphQL\Type\Definition\Type;
use FreePBX\modules\Api\Gql\Base;

class SipSettings extends Base {
	protected $module = 'Sipsettings';
	
	/**
	 * queryCallback
	 *
	 * @return void
	 */
	public function queryCallback() {
		if($this->checkAllReadScope()) {
			return function() {
				return [
					'fetchSipNatNetworkSettings' => [
						'type' => $this->typeContainer->get('sipsettings')->getConnectionType(),
						'resolve' =>  function() {
							return $this->getNetworkSettings();
						}
					],
					'fetchWSSettings' => [
						'type' => $this->typeContainer->get('sipsettings')->getConnectionType(),
						'resolve' =>  function() {
							return $this->getWSSettings();
						}
					]
            ];
			};
	   }
	}
	
	public function mutationCallback() {
		if($this->checkAllWriteScope()) {
			return function() {
				return [
					'addSipNatLocalIp' => Relay::mutationWithClientMutationId([
						'name' => _('addSipNatLocalIp'),
						'description' => _('Adding a Local IP network and mask'),
						'inputFields' => $this->getInputFields(),
						'outputFields' => $this->getOutputFields(),
						'mutateAndGetPayload' => function($input){
							return $this->addLocalIP($input);
						}
					]),
					'updateSipNatExternalIp' => Relay::mutationWithClientMutationId([
						'name' => _('updateSipNatExternalIp'),
						'description' => _('Updating External IP network and mask'),
						'inputFields' => $this->getUpdateField(),
						'outputFields' => $this->getOutputFields(),
						'mutateAndGetPayload' => function($input){
							return $this->updateExternalIP($input);
						}
					]),
					'updateWSSettings' => Relay::mutationWithClientMutationId([
						'name' => _('updateWSSettings'),
						'description' => _('Updating Web Socket Settings'),
						'inputFields' => $this->getWSFields(),
						'outputFields' => $this->getOutputFields(),
						'mutateAndGetPayload' => function($input) {
							$ret = $this->validateWSFields($input);
							if($ret['status'] == 0) {
								return ['message' => $ret['message'], 'status' => false];
							}
							return $this->updateWSSettings($input);
						}
					])
				];
			};
		}
	}
	
	/**
	 * getInputFields
	 *
	 * @return void
	 */
	private function getInputFields(){
		return [
		 	'net' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('The network IP address')
			],
		 	'mask' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('The network mask')
			]
		];
	}
		
	/**
	 * getUpdateField
	 *
	 * @return void
	 */
	private function getUpdateField(){
		return [
		 	'net' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('ertyuiop')
			]
		];
	}
	
	/**
	 * getOutputFields
	 *
	 * @return void
	 */
	private function getOutputFields(){
		return [
			'status' => [
				'type' => Type::boolean(),
				'description' => _('API status')
			],	
			'message' => [
				'type' => Type::string(),
				'description' => _('API message response')
			]		
		];
	}

	/**
	 * initializeTypes
	 *
	 * @return void
	 */
	public function initializeTypes() {
		$sipsettings = $this->typeContainer->create('sipsettings');
		$sipsettings->setDescription(_('Sipsettings management'));

		$sipsettings->addInterfaceCallback(function() {
			return [$this->getNodeDefinition()['nodeInterface']];
		});

	   $sipsettings->addFieldCallback(function() {
		 return [
			'id' => Relay::globalIdField('sipsettings', function($row) {
				return isset($row['id']) ? $row['id'] : null;
			}),
			'status' =>[
				'type' => Type::boolean(),
				'description' => _('Status of the request')
			],
			'message' =>[
				'type' => Type::String(),
				'description' => _('Message for the request')
			],
			'net' =>[
				'type' => Type::String(),
				'description' => _('Returns the network IP')
			],
			'mask' =>[
				'type' => Type::String(),
				'description' => _('Returns the network mask')
			],
			'interface' =>[
				'type' => Type::String(),
				'description' => _('Returns the interface')
			],
			'state' =>[
				'type' => Type::String(),
				'description' => _('Returns the current state')
			]
		];
	});

	$sipsettings->setConnectionFields(function() {
		return [
			'localIP' => [
				'type' =>  Type::listOf($this->typeContainer->get('sipsettings')->getObject()),
				'description' => _('list of local IP saved'),
				'resolve' => function($root, $args) {
					$data = array_map(function($row){
						return $row;
					},isset($root['localIP']) ? $root['localIP'] : []);
						return $data;
					}
				],
			'routes' => [
				'type' =>  Type::listOf($this->typeContainer->get('sipsettings')->getObject()),
				'description' => _('list the route configured'),
				'resolve' => function($root, $args) {
					$data = array_map(function($row){
						return $row;
					},isset($root['routes']) ? $root['routes'] : []);
						return $data;
					}
				],
			'message' =>[
				'type' => Type::string(),
				'description' => _('Message for the request')
				],
			'status' =>[
				'type' => Type::boolean(),
				'description' => _('Status for the request')
				],
			'externIP' =>[
				'type' => Type::String(),
				'description' => _('Lists the External IPs')
			],
			'ws' => [
				'type' =>  Type::listOf($this->typeContainer->get('sipsettings')->getObject()),
				'description' => _('List the WS settings'),
				'resolve' => function($root, $args) {
					$data = [];
					foreach ($root['ws'] as $key => $val) {
						$data[] = [
							'interface' => $key,
							'state' => $val
						];
					}
					return $data;
				}
			],
			'wss' => [
				'type' =>  Type::listOf($this->typeContainer->get('sipsettings')->getObject()),
				'description' => _('List the WSS settings'),
				'resolve' => function($root, $args) {
					$data = [];
					foreach ($root['wss'] as $key => $val) {
						$data[] = [
							'interface' => $key,
							'state' => $val
						];
					}
					return $data;
				}
			],
		   ];
	   });
   }
	
	/**
	 * getNetworkSettings
	 *
	 * @return void
	 */
	public function getNetworkSettings(){
		try {
			$ip = $this->freepbx->sipsettings->getNatObj()->getVisibleIP();
			$routeArr = $this->freepbx->sipsettings->getNatObj()->getRoutes();
			$routes = array();
			foreach($routeArr as $res){
				array_push($routes,array('net' => $res[0],'mask' => $res[1]));
			}
			if($ip['status']) {
				$retarr = array("message" => _("List of External and Local IPs"), "status" => true, "externIP" => $ip['address'], "routes" => $routes,'localIP' => $this->freepbx->sipsettings->getNatObj()->getConfigurations('localnets',$this->freepbx));
			} else {
				$retarr = array("message" => $ip['message'], "status" => true, "externIP" => false, "routes" => $routes , 'localIP' => $this->freepbx->sipsettings->getNatObj()->getConfigurations('localnets',$this->freepbx));
			}
		} catch(\Exception $e) {
			$retarr = array("status" => false, "message" => $e->getMessage());
		}
		return $retarr;
	}
	
	/**
	 * addLocalIP
	 *
	 * @param  mixed $input
	 * @return void
	 */
	private function addLocalIP($input){
		$respose = $this->freepbx->sipsettings->getNatObj()->setConfigurations(array($input),"localnets",$this->freepbx);
		return['message' => _('Local IP has been added successfully'),'status' => true];
	}
	
	/**
	 * updateExternalIP
	 *
	 * @param  mixed $input
	 * @return void
	 */
	private function updateExternalIP($input){
		$respose = $this->freepbx->sipsettings->getNatObj()->setConfigurations(array($input['net']),"externip",$this->freepbx);
		return['message' => _('External IP has been updated successfully'),'status' => true];
	}
	
	/**
	 * getWSSettings
	 *
	 * @return void
	 */
	public function getWSSettings(){
		try {
			$allBinds = $this->freepbx->sipsettings->getConfig('binds');
			$settings = [];
			$types = ['ws', 'wss'];
			if (is_array($allBinds)) {
				foreach ($types as $type) {
					$settings[$type] = isset($allBinds[$type]) ? $allBinds[$type] : [];
				}
			}
			$retarr = array("message" => _("Web Socket Settings"), "status" => true, "ws" => $settings['ws'], "wss" => $settings['wss']);
		} catch(\Exception $e) {
			$retarr = array("status" => false, "message" => $e->getMessage());
		}
		return $retarr;
	}
	
	/**
	 * getWSFields
	 *
	 * @return array
	 */
	private function getWSFields() {
		return [
			'ws' => [
			   'name' => 'ws',
			   'type' => Type::nonNull(Type::string())
		   ],
		 	'wss' => [
				'name' => 'wss',
				'type' => Type::nonNull(Type::string())
			]
		];
	}

	/**
	 * validateWSFields
	 *
	 * @param  array $input
	 * @return array
	 */
	private function validateWSFields($input) {
		$allBinds = $this->freepbx->sipsettings->getConfig('binds');
		$ret = ['status' => 1, 'message' => ''];
		$types = ['ws', 'wss'];
		$modes = ['on', 'off'];
		foreach ($input as $type => $val) {
			if (!in_array($type, $types)) {
				$ret['status'] = 0;
				$ret['message'] = $ret['message']._("Invalid key '" . $type . "'. Only the following are allowed: " . implode(",", $types)) . "\n";
				continue;
			}
			if (!empty($val)) {
				$data = json_decode(str_replace("'", '"', $val), true);
				if (is_array($data)) {
					$onCount = 0;
					foreach ($data as $ip => $state) {
						if (!in_array($state, $modes)) {
							$ret['status'] = 0;
							$ret['message'] = $ret['message']._("Invalid mode '" . $state . "'. Only the following are allowed: " . implode(",", $modes)) . "\n";
							continue;
						}
						if (!isset($allBinds[$type][$ip])) {
							$ret['status'] = 0;
							$ret['message'] = $ret['message']._("Invalid IP value '" . $ip . "'")."\n";
							continue;
						}
						if ($state == "on") {
							$onCount++;
						}
					}
					if ($onCount > 0) {
						if (!isset($data["0.0.0.0"])) {
							if (isset($allBinds[$type]["0.0.0.0"]) && $allBinds[$type]["0.0.0.0"] == "on") {
								$ret['status'] = 0;
								$ret['message'] = $ret['message']._($type . " settings for 'All' (0.0.0.0) must be disabled before enabling any other settings.")."\n";
							}
						} else {
							if ($onCount > 1 && $data["0.0.0.0"] == "on") {
								$ret['status'] = 0;
								$ret['message'] = $ret['message']._("Other " . $type . " settings can not be enabled along with settings for 'All' (0.0.0.0).")."\n";
							}
						}
					}
				}
			}
		}
		return $ret;
	}
	
	/**
	 * updateWSSettings
	 *
	 * @param  array $input
	 * @return array 
	 */
	private function updateWSSettings($input) {
		$allBinds = $this->freepbx->sipsettings->getConfig('binds');
		foreach ($input as $type => $val) {
			if (!empty($val)) {
				$data = json_decode(str_replace("'", '"', $val), true);
				if (is_array($data) && isset($allBinds[$type])) {
					if (isset($data["0.0.0.0"]) && $data["0.0.0.0"] == "on") {
						$allBinds[$type]["0.0.0.0"] = "on";
						foreach ($allBinds[$type] as $ipaddr => $mode) {
							if ($ipaddr != "0.0.0.0") {
								$allBinds[$type][$ipaddr] = 'off';
							}
						}
					} else {
						foreach ($data as $ip => $state) {
							$allBinds[$type][$ip] = $state;
						}
					}
				}
			}
		}

		$this->freepbx->sipsettings->setConfig('binds', $allBinds);
		return['message' => _('Web Socket settings updated successfully'),'status' => true];
	}
}
