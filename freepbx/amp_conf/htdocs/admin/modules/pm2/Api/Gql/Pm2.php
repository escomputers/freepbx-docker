<?php

namespace FreePBX\modules\Pm2\Api\Gql;

use GraphQLRelay\Relay;
use GraphQL\Type\Definition\Type;
use FreePBX\modules\Api\Gql\Base;

/**
 * Pm2
 */
class Pm2 extends Base {
	protected $module = 'Pm2';
	
	/**
	 * queryCallback
	 *
	 * @return void
	 */
	public function queryCallback() {
		if($this->checkAllReadScope()) {
			return function() {
				return [
					'fetchPm2AppStatus' => [
						'type' => $this->typeContainer->get('pm2')->getConnectionType(),
						'resolve' => function($root, $args) {
							$res = $this->freepbx->pm2->pm2Apps()->getAppStatus();
							if(!empty($res)){
								return ['message' => _('Kindly follow the pm2 apps and their status'), 'status' => true, 'response' => $res];
							}else{
								return ['message' => _('Sorry unable to find the pm2apps status'), 'status' => false, 'response' => array()];
							}
						}
					],
            ];
			};
	   }
	}
	
	/**
	 * initializeTypes
	 *
	 * @return void
	 */
	public function initializeTypes() {
		$pm2 = $this->typeContainer->create('pm2');
		$pm2->setDescription(_('Process management'));

		$pm2->addInterfaceCallback(function() {
			return [$this->getNodeDefinition()['nodeInterface']];
		});

		$pm2->addFieldCallback(function() {
			return [
				'id' => Relay::globalIdField('pm2', function($row) {
					return isset($row['id']) ? $row['id'] : null;
				}),
				'status' =>[
					'type' => Type::boolean(),
					'description' => _('Status of the request'),
				],
				'message' =>[
					'type' => Type::String(),
					'description' => _('Message for the request')
				],
				'name' =>[
				   'type' => Type::string(),
				   'description' => _('Name od the app'),
				],
				'PID' =>[
				   'type' => Type::string(),
				   'description' => _('Process id for the app'),
					'resolve' => function($root, $args) {
						if(isset($root['PID'])){
							return $root['PID'];
						}else{
							return 0;
						}
					}
				],
				'status' =>[
				   'type' => Type::string(),
				   'description' => _('Status of the app'),
					'resolve' => function($root, $args) {
					if(isset($root['status'])){
							return $root['status'];
						}else{
							return null;
						}
					}
				],
				'memory' =>[
				   'type' => Type::string(),
				   'description' => _('Memory used by the app'),
					'resolve' => function($root, $args) {
					if(isset($root['memory'])){
							return $root['memory'];
						}else{
							return null;
						}
					}
				],
				'uptime' =>[
				   'type' => Type::string(),
				   'description' => _('Uptime of the app'),
					'resolve' => function($root, $args) {
					if(isset($root['uptime'])){
							return $root['uptime'];
						}else{
							return null;
						}
					}
				]
			];
		});

		$pm2->setConnectionFields(function() {
			return [
				'apps' => [
					'type' =>  Type::listOf($this->typeContainer->get('pm2')->getObject()),
					'description' => _(''),
					'resolve' => function($root, $args) {
						$data = array_map(function($row){
							return $row;
						},$root['response']);
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
				]
			];
		});
	}
}
