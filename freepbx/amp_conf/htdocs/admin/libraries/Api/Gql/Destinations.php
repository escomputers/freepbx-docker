<?php

namespace FreePBX\Api\Gql;

use GraphQL\Type\Definition\Type;
use FreePBX\modules\Api\Gql\Base;

class Destinations extends Base {
	private $destinations;

	protected static $priority = 100;

	public function initializeTypes() {
		$destination = $this->typeContainer->create('destination','union');
		$destination->setDescription("Destination for a call to hit");
		$destination->addResolveType(function($value, $context, $info) use ($destination) {
			foreach($destination->getResolveTypeCallbacks() as $cb) {
				$out = $cb($value, $context, $info);
				if(!is_null($out)) {
					return $out;
				}
			}
			return $this->typeContainer->get('unknowndestination')->getObject();
		});

		$destination->addTypeCallback(function() {
			return [
				$this->typeContainer->get('unknowndestination')->getObject()
			];
		});

		$unknowndestination = $this->typeContainer->create('unknowndestination');
		$unknowndestination->setDescription('A destination that does not have a GraphQL reference');
		$unknowndestination->addFieldCallback(function() {
			return [
				'id' => [
					'type' => Type::nonNull(Type::id()),
					'description' => 'The unknown destination id',
					'resolve' => function($value, $args, $context, $info) {
						return $value;
					}
				]
			];
		});
	}

	private function getDestinations() {
		if(empty($this->destinations)) {
			$this->destinations = $this->freepbx->Modules->getDestinations();
		}
		return $this->destinations;
	}
}
