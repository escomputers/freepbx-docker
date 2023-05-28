<?php

namespace FreePBX\modules\Music\Api\Gql;

use GraphQL\Type\Definition\Type;
use FreePBX\modules\Api\Gql\Base;

use GraphQLRelay\Relay;

class Music extends Base {
	protected $module = 'music';

	public function queryCallback() {
		if($this->checkAllReadScope()) {
			return function() {
				return [
					'allMusiconholds' => [
						'type' => $this->typeContainer->get('musiconhold')->getConnectionType(),
						'description' => 'Used to manage a system wide list of blocked callers',
						'args' => Relay::connectionArgs(),
						'resolve' => function($root, $args) {
							return Relay::connectionFromArray($this->freepbx->Music->getCategories(), $args);
						},
					],
					'musiconhold' => [
						'type' => $this->typeContainer->get('musiconhold')->getObject(),
						'args' => [
							'id' => [
								'type' => Type::id(),
								'description' => 'The ID',
							]
						],
						'resolve' => function($root, $args) {
							$item = $this->freepbx->Music->getCategoryByID(Relay::fromGlobalId($args['id'])['id']);
							return isset($item) ? $item : null;
						}
					]
				];
			};
		}
	}

	public function initializeTypes() {
		$user = $this->typeContainer->create('musiconhold');
		$user->setDescription('Used to manage a system wide list of blocked callers');

		$user->addInterfaceCallback(function() {
			return [$this->getNodeDefinition()['nodeInterface']];
		});

		$user->setGetNodeCallback(function($id) {
			$item = $this->freepbx->Music->getCategoryByID($id);
			return isset($item) ? $item : null;
		});

		$user->addFieldCallback(function() {
			return [
				'id' => Relay::globalIdField('musiconhold', function($row) {
					return $row['id'];
				}),
				'category' => [
					'type' => Type::string(),
					'description' => 'Category Name'
				],
				'type' => [
					'type' => Type::string(),
					'description' => 'Type of Music on Hold. If set to "Files" then this category will play the files listed below. If set to "Custom Application" then this category will stream music from the set application'
				],
				'random' => [
					'type' => Type::boolean(),
					'description' => 'Enable random playback of music for this category. If disabled music will play in alphabetical order'
				],
				'application' => [
					'type' => Type::string()
				],
				'format' => [
					'type' => Type::string()
				]
			];
		});

		$user->setConnectionResolveNode(function ($edge) {
			return $edge['node'];
		});

		$user->setConnectionFields(function() {
			return [
				'totalCount' => [
					'type' => Type::int(),
					'resolve' => function($value) {
						return count($this->freepbx->Music->getCategories());
					}
				],
				'musiconholds' => [
					'type' => Type::listOf($this->typeContainer->get('musiconhold')->getObject()),
					'resolve' => function($root, $args) {
						$data = array_map(function($row){
							return $row['node'];
						},$root['edges']);
						return $data;
					}
				]
			];
		});
	}
}
