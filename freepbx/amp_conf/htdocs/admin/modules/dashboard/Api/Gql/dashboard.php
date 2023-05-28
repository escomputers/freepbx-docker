<?php
namespace FreePBX\modules\Dashboard\Api\Gql;

use GraphQLRelay\Relay;
use FreePBX\modules\Api\Gql\Base;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\EnumType;

class Dashboard extends Base {
	protected $module = 'dashboard';
	protected $diskspace_array = array();

	public function queryCallback() {
		if ($this->checkAllReadScope()) {
			return function() {
				return [
					'checkdiskspace' => [
						'type' => $this->typeContainer->get('diskspace')->getConnectionType(),
						'description' => _('Use to get all the diskspace'),
						'args' => Relay::connectionArgs(),
						'resolve' => function($root, $args) {
							try {
								$this->diskspace_array = $this->freepbx->Dashboard->getdiskspace();
								if (isset($this->diskspace_array) && $this->diskspace_array != null && count($this->diskspace_array) > 0) {
									$i=1;
									foreach($this->diskspace_array as $k=>$v) {
										$this->diskspace_array[$k]['storage_path'] = $k;
										$this->diskspace_array[$k]['id'] = $i;
										$this->diskspace_array[$i] = $this->diskspace_array[$k];
										unset($this->diskspace_array[$k]);
										$i++;
									}
									$list = Relay::connectionFromArray($this->diskspace_array, $args);
									return ['response'=> $list,'status'=>true,'message'=> _("Successfully found disks space details")];
								} else {
									return ['message'=> _("Sorry, unable to find any diskspace"),'status' => false];
								}
							} catch (\Exception $e) {
								return ['message'=>$e->getMessage().' LINE NUMBER : '.$e->getLine() ,'status' => false];
							}
						}
					]
				];
			};
		}
	}

	public function initializeTypes() {
		$dashboard = $this->typeContainer->create('diskspace');
		$dashboard->setDescription(_('Read the System licence information'));

		$dashboard->addInterfaceCallback(function() {
			return [$this->getNodeDefinition()['nodeInterface']];
		});

		$dashboard->setGetNodeCallback(function() {
			$item = $this->freepbx->Dashboard->get_licencefile_info();
			return isset($item) ? $item : null;
		});

		$dashboard->addFieldCallback(function() {
			return [
				'id' => [
					'type' => Type::nonNull(Type::Id()),
					'description' => _('Returns storage id'),
					'resolve' => function($row) {
						return isset($row['id']) ? (int)$row['id'] : 0;
					}
				],
				'storage_path' => [
					'type' => Type::string(),
					'description' => _('storage path details'),
					'resolve' => function($row) {
						return isset($row['storage_path']) ? $row['storage_path'] : null;
					}
				],
				'available_space' => [
					'type' => Type::string(),
					'description' => _('Available space details'),
					'resolve' => function($row) {
						return isset($row['avail']) ? $row['avail'] : null;
					}
				],
				'used_space' => [
					'type' => Type::string(),
					'description' => _('used space details'),
					'resolve' => function($row) {
						return isset($row['used']) ? $row['used'] : null;
					}
				],
				'total_size' => [
					'type' => Type::string(),
					'description' => _('disk total size details'),
					'resolve' => function($row) {
						return isset($row['size']) ? $row['size'] : null;
					}
				],
				'used_percentage' => [
					'type' => Type::string(),
					'description' => _('disk used percentage'),
					'resolve' => function($row) {
						return isset($row['usepct']) ? $row['usepct'] : null;
					}
				],
				'message' => [
					'type' => Type::string(),
					'description' => _('Message for the request')
				],
				'status' => [
					'type' => Type::boolean(),
					'description' => _('Status for the request')
				]
			];
		});

		$dashboard->setConnectionResolveNode(function ($edge) {
			return $edge['node'];
		});

		$dashboard->setConnectionFields(function() {
			return [
				'totalCount' => [
					'type' => Type::int(),
					'resolve' => function($value) {
						return count($this->diskspace_array);
					}
				],
				'diskspace' => [
					'type' => Type::listOf($this->typeContainer->get('diskspace')->getObject()),
					'resolve' => function($root, $args) {
						$data = array_map(function($row) {
							return $row['node'];
						},isset($root['response']) ? $root['response']['edges'] : []);
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
