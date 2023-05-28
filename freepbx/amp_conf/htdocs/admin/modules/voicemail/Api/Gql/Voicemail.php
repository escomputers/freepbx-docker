<?php

namespace FreePBX\modules\Voicemail\Api\Gql;

use GraphQLRelay\Relay;
use GraphQL\Type\Definition\Type;
use FreePBX\modules\Api\Gql\Base;

class Voicemail extends Base {
	protected $module = 'voicemail';

	public function mutationCallback() {
		global $astman;
		if($this->checkAllWriteScope()) {
			return function() {
				return [			
					'enableVoiceMail' => Relay::mutationWithClientMutationId([
						'name' => 'enableVoiceMail',
						'description' => _('Create/enable a voicemail account'),
						'inputFields' => $this->getMutationFieldssettings(),
						'outputFields' =>$this->getOutputFields(),
						'mutateAndGetPayload' => function($input){
							return $this->enableVoiceMail($input);
						} 
					]),
					'disableVoiceMail' => Relay::mutationWithClientMutationId([
						'name' => 'disableVoiceMail',
						'description' => _('Delete/disable a voicemail account'),
						'inputFields' => $this->getMutationFieldDisable(),
						'outputFields' => $this->getOutputFields(),
						'mutateAndGetPayload' =>  function($input){
							 return $this->disableVoiceMail($input);
						}
					]),
				];
			};
		}
	}

	public function queryCallback() {
	 if($this->checkReadScope('voicemail')) {
		return function() {
		return [
			'fetchVoiceMail' => [
				'type' => $this->typeContainer->get('voiceMail')->getObject(),
				'description' => _('Return extension details'),
				'args' => [
					'extensionId' => [
						'type' => Type::nonNull(Type::id()),
						'description' => _('The extension ID'),
					]
				],
				'resolve' => function($root, $args) {
					try{
						$res = $this->freepbx->voiceMail->getVoicemailBoxByExtension($args['extensionId']);
						if(isset($res) && $res != null){
							return  ['message' => _('Voicemail data found successfully') ,'response'=> $res, 'status' => true];
						}else{
							return ['message' => _('Sorry unable to fetch the status'), 'status' => false] ;
						}
					}catch(Exception $ex){
						FormattedError::setInternalErrorMessage($ex->getMessage());
					}		
				}]
			];
		};
	}
}

	private function getMutationFieldDisable() {
		return [
			'extensionId' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('The voicemail extensionId')
			]
		];
	}
	private function getMutationFieldssettings() {
		return [
			'extensionId' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('The voicemail extensionId')
			],
			'password' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('The voicemail password/pin')
			],
			'name' => [
				'type' => Type::string(),
				'description' => _('The voicemail name')
			],
			'email' => [
				'type' => Type::string(),
				'description' => _('The voicemail email address')
			],
			'pager' => [
				'type' => Type::string(),
				'description' => _('The voicemail pager number')
			],
			'saycid' => [
				'type' => Type::boolean(),
				'description' => _('Whether to play the CID to the caller ')
			],
			'envelope' => [
				'type' => Type::boolean(),
				'description' => _('Whether to play the envelope to the caller')
			],
			'attach' => [
				'type' => Type::boolean(),
				'description' => _('Whether to attach the voicemail to the outgoing email')
			],
			'delete' => [
				'type' => Type::boolean(),
				'description' => _('Whether to delete the voicemail from local storage')
			],
		];
	}

	protected function reloadVoiceMail() {
		$this->freepbx->astman->Command("voicemail reload");
	}

	public function disableVoiceMail($input) {
		$res = $this->freepbx->Voicemail->delMailbox($input['extensionId']);
		if ($res == true) {
			$this->reloadVoiceMail();
			$this->freepbx->Voicemail->updateMailBoxContext($input['extensionId'], 'novm');
			return ['message' => _('Voicemail has been disabled'),'status' => true];
		} else{
			return ['message' => _('Sorry,voicemail does not  exists.'),'status' => false];
		}
	}

	public function enableVoiceMail($input){
		$input = $this->resolveInputNames($input);
		$extensionExists = $this->freepbx->Core->getDevice($input['extensionId']);
		if (empty($extensionExists)) {
			return ['message' => _('Extension does not exists.'),'status' => false];
		}
		$res = $this->freepbx->Voicemail->addMailbox($input['extensionId'],$input);
		if($res == true){
			$this->reloadVoiceMail();
			$this->freepbx->Voicemail->updateMailBoxContext($input['extensionId'], 'default');
			return ['message' => _('Voicemail has been created successfully'),'status' => true];
		} else{
			return ['message' => _('Sorry,voicemail already exists.'),'status' => false];
		}
	}

	public function getOutputFields(){
	 return [
		'status' => [
			'type' => Type::boolean(),
			'description' => _('Status of the request')
			],
		'message' => [
			'type' => Type::string(),
			'description' => _('Message for the request')
			]
		];
	}

	public function initializeTypes() {
		$voiceMail = $this->typeContainer->create('voiceMail');
		$voiceMail->setDescription(_('Read the Voicemail information'));

		$voiceMail->addInterfaceCallback(function() {
			return [$this->getNodeDefinition()['nodeInterface']];
		});

		$voiceMail->addFieldCallback(function() {
			return [
				'id' => Relay::globalIdField('extension', function($row) {
					return isset($row['id']) ? $row['id'] : null;
				}),
				'status' =>[
					'type' => Type::boolean(),
					'description' => _('Status of the request')
				],
				'message' =>[
					'type' => Type::string(),
					'description' => _('Message for the request')
				],
				'context' => [
					'type' => Type::string(),
					'description' => _('Context for the voice mail Voicemail'),
					'resolve' => function ($payload) {
						return (isset($payload['response']['vmcontext'])) ? $payload['response']['vmcontext'] : null;
					}
				],
				'password' => [
					'type' => Type::string(),
					'description' => _('Password for the voicemail'),
					'resolve' => function ($payload) {
						return (isset($payload['response']['pwd'])) ? $payload['response']['pwd'] : null;
					}
				],
				'name' => [
					'type' => Type::string(),
					'description' => _('Name for the voicemail'),
					'resolve' => function ($payload) {
						return (isset($payload['response']['name'])) ? $payload['response']['name'] : null;
					}
				],
				'email' => [
					'type' => Type::string(),
					'description' => _('Email address for the voicemail'),
					'resolve' => function ($payload) {
						return (isset($payload['response']['email'])) ? $payload['response']['email'] : null;
					}
				],
				'pager' => [
					'type' => Type::string(),
					'description' => _('The voicemail pager number'),
					'resolve' => function ($payload) {
						return (isset($payload['response']['pager'])) ? $payload['response']['pager'] : null;
					}
				],
				'attach' => [
					'type' => Type::string(),
					'description' => _('Attach the voicemail to the outgoing email'),
					'resolve' => function ($payload) {
						return (isset($payload['response']['options']['attach'])) ? $payload['response']['options']['attach'] : null;
					}
				],
				'saycid' => [
					'type' => Type::string(),
					'description' => _('Whether to play the CID to the caller or not'),
					'resolve' => function ($payload) {
						return (isset($payload['response']['options']['saycid'])) ? $payload['response']['options']['saycid'] : null;
					}
				],
				'envelope' => [
					'type' => Type::string(),
					'description' => _('To play the envelope to the caller'),
					'resolve' => function ($payload) {
						return (isset($payload['response']['options']['envelope'])) ? $payload['response']['options']['envelope'] : null;
					}
				],
				'delete' => [
					'type' => Type::string(),
					'description' => _('Delete the voicemail from local storage'),
					'resolve' => function ($payload) {
						return (isset($payload['response']['options']['delete'])) ? $payload['response']['options']['delete'] : null;
					}
				],
			];
		});
	}

	private function resolveInputNames($input){
		$input['vm'] = 'enabled';
		$input['vmpwd'] = $input['password'];

		if(isset($input['saycid'])){
			if($input['saycid'] == true){
				$input['saycid'] = "=yes"; //written in this format as in voicemail class its explode with = 
			}elseif($input['saycid'] == false){
				$input['saycid'] = "=no"; 
			}
		}else{
			$input['saycid'] = "=no"; 
		}
      
		if(isset($input['envelope'])){
			if($input['envelope'] == true){
				$input['envelope'] = "=yes"; //written in this format as in voicemail class its explode with = 
			}elseif($input['envelope'] == false){
				$input['envelope'] = "=no"; 
			}
		}else{
			$input['envelope'] = "=no"; 
		}
      
		if(isset($input['attach'])){
			if($input['attach'] == true){
				$input['attach'] = "=yes"; //written in this format as in voicemail class its explode with = 
			}elseif($input['attach'] == false){
				$input['attach'] = "=no"; 
			}
		}else{
			$input['attach'] = "=no"; 
		}
      
		if(isset($input['delete'])){
			if($input['delete'] == true){
				$input['vmdelete'] = "=yes"; //written in this format as in voicemail class its explode with = 
			}elseif($input['delete'] == false){
				$input['vmdelete'] = "=no"; 
			}
		}else{
			$input['vmdelete'] = "=no"; 
		}
      
		return $input;
	}
}
