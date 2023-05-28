<?php

namespace FreePBX\modules\Recordings\Api\Gql;

use GraphQLRelay\Relay;
use GraphQL\Type\Definition\Type;
use FreePBX\modules\Api\Gql\Base;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use GraphQL\Error\FormattedError;
class Recordings extends Base {
    protected $module = 'recordings';

	public function mutationCallback() {
		if($this->checkAllWriteScope()) {
			return function() {
                return [
                    'addRecording' => Relay::mutationWithClientMutationId([
						'name' => _('addRecording'),
						'description' => _('Add a new recording to the Sytem Recordings'),
						'inputFields' => $this->getRecodingInputs(),
						'outputFields' => $this->getRecodingOutputs(),
						'mutateAndGetPayload' => function ($input) {
                            return $this->addUpdateRecordings($input);
						}
					]),
                    'updateRecording' => Relay::mutationWithClientMutationId([
						'name' => _('updateRecording'),
						'description' => _('Update a recording in the Sytem Recordings'),
						'inputFields' => $this->getRecodingInputs(),
						'outputFields' => $this->getRecodingOutputs(),
						'mutateAndGetPayload' => function ($input) {
                            return $this->addUpdateRecordings($input);
						}
					]),
                    'deleteRecording' => Relay::mutationWithClientMutationId([
						'name' => _('deleteRecording'),
						'description' => _('Delete a recording from the Sytem Recordings'),
						'inputFields' => [
                            'id' => [
								'type' => Type::nonNull(Type::string()),
								'description' => _('An id used to identify your recording')
							]
                        ],
						'outputFields' => $this->getRecodingOutputs(),
						'mutateAndGetPayload' => function ($input) {
                            if(isset($input['id']) && $input['id'] > 0) {
                                $this->freepbx->recordings->delRecording($input['id']);
                                return ['message' => _("Recording deleted succefully"), 'status' => true, 'id' => $input['id']];
                            } else {
                                return ['message' => _("Please provide a valid id to delete the system recording"), 'status' => false];
                            }
						}
					]),
                    'convertfile' => Relay::mutationWithClientMutationId([
						'name' => _('convertfile'),
						'description' => _('Convert existing file into different formats'),
						'inputFields' => $this->getRecodingInputs(),
						'outputFields' => $this->getRecodingOutputs(),
						'mutateAndGetPayload' => function ($input) {
                            $validate = $this->InputValidate($input);
                            if(!$validate['status']) {
                                return ['message' => $validate['message'], 'status' => false];
                            }
                            $fileDetails = $this->freepbx->recordings->fileStatus($input['name']);
                            if(!$fileDetails) {
                                return ['message' => sprintf(_("File with the name '%s' not found in the system"),$input['name']), 'status' => false];
                            }
                            $input['temporary'] = isset($input['temporary']) ? $input['temporary'] : 0;
                            $result = $this->freepbx->recordings->convertFiles($input);
                            if($result['status']) {
                                return ['message' => (isset($result['message'])) ? $result['message'] : _("File Converted succefully"), 'status' => true];
                            } else {
                                return ['message' => (isset($result['message'])) ? $result['message'] : _("Failed to convert the file"), 'status' => false];
                            }
						}
					]),
                ];
            };
        }
    }

    /**
	 * queryCallback
	 *
	 * @return void
	 */
	public function queryCallback() {
        if($this->checkAllReadScope()) {
			return function() {
				return [
                    'fetchAllRecordings' => [
						'type' => $this->typeContainer->get('recordings')->getConnectionType(),
						'resolve' => function ($root, $args) {
							$res = $this->freepbx->recordings->getAll();
							if (!empty($res)) {
								return ['message' => _("List of system recordings"), 'status' => true, 'response' => $res];
							} else {
								return ['message' => _('Sorry unable to find the system recordings'), 'status' => false];
							}
						}
					],
                    'fetchRecordingFiles' => [
                        'type' => $this->typeContainer->get('recordings')->getConnectionType(),
                        'args' => [
							'search' => [
								'type' => Type::string(),
								'description' => _('File name'),
							]
						],
						'resolve' => function ($root, $args) {
							$allFiles = $this->freepbx->recordings->getSystemRecordings();
                            $search = $args['search'];
                            $res = [];
                            foreach ($allFiles as $file) {
                                if (strpos($file['name'], $search) !== false) {
                                    $res[] = $file['name'];
                                }
                            }
							if (!empty($res)) {
								return ['message' => _("List of system recording files"), 'status' => true, 'response' => $res];
							} else {
								return ['message' => _('Sorry unable to find the system recording files'), 'status' => false];
							}
						}
                    ]
                ];
            };
        }
    }

    public function initializeTypes() {
		$user = $this->typeContainer->create('recordings');
		$user->setDescription('Sytem Recordings');

        $user->addInterfaceCallback(function() {
			return [$this->getNodeDefinition()['nodeInterface']];
		});

        $user->addFieldCallback(function() {
			return [
                'id' => [
                    'type' => Type::nonNull(Type::Id()),
                    'description' => _('Id of the Recording')
                ],
                'name' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => _('Name of the Recording')
                ],
                'description' => [
                    'type' => Type::string(),
                    'description' => _('Description of the Recording')
                ],
                'fcode' => [
                    'type' => Type::id(),
                    'description' => _('Feature code')
                ],
                'fcode_pass' => [
                    'type' => Type::string(),
                    'description' => _('Feature code password')
                ],
                'language' => [
                    'type' => Type::string(),
                    'description' => _('Language of recording')
                ],
                'playback' => [
                    'type' => Type::listOf(Type::String()),
                    'description' => _('List of existing playback files in the system')
                ],
                'status' => [
                    'type' => Type::boolean(),
                    'resolve' => function ($payload) {
                        return $payload['status'];
                    }
                ],
                'message' => [
                    'type' => Type::string(),
                    'resolve' => function ($payload) {
                        return $payload['message'];
                    }
                ],
                'languages' => [
                    'type' => Type::listOf(Type::String()),
                    'resolve' => function ($payload) {
                        return $payload['languages'];
                    }
                ]
            ];
        });

        $user->setConnectionResolveNode(function ($edge) {
			return $edge['node'];
		});

        $user->setConnectionFields(function() {
			return [
				'message' =>[
					'type' => Type::string(),
					'description' => _('Message for the request')
				],
				'status' =>[
					'type' => Type::boolean(),
					'description' => _('Status for the request')
				],
                'recordings' => [
					'type' => Type::listOf($this->typeContainer->get('recordings')->getObject()),
					'description' => _('List of system recordings'),
					'resolve' => function ($root, $args) {
						$data = array_map(function ($row) {
                            $row['name'] = $row['displayname'];
                            $row['playback'] = array_keys($row['files']);
							return $row;
						}, isset($root['response']) ? $root['response'] : []);
						return $data;
					}
				],
                'recodingFiles' => [
                    'type' => Type::listOf(Type::string()),
					'description' => _('List of system recording files'),
					'resolve' => function ($root, $args) {
						$data = array_map(function ($row) {
							return $row;
						}, isset($root['response']) ? $root['response'] : []);
						return $data;
					}
                ]
            ];
        });
    }

    /**
     * input fields
     */
    public function getRecodingInputs()
    {
        return [
            'id' => [
				'type' => Type::id(),
				'description' => _('Id of the Recording')
            ],
			'name' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('Name of the Recording')
            ],
            'description' => [
				'type' => Type::string(),
				'description' => _('Description of the Recording')
			],
            'fcode' => [
				'type' => Type::id(),
				'description' => _('Feature code')
			],
            'fcode_pass' => [
				'type' => Type::string(),
				'description' => _('Feature code password')
			],
            'language' => [
				'type' => Type::string(),
				'description' => _('Language of recording file')
			],
            'playback' => [
				'type' => Type::listOf(Type::String()),
				'description' => _('Description of the Recording')
			],
            'file' => [
				'type' => Type::String(),
				'description' => _('File name')
			],
            'codec' => [
				'type' => Type::String(),
				'description' => _('File format to convert')
			],
            'codecs' => [
				'type' => Type::listOf(Type::String()),
				'description' => _('List of allowed formats to which playback files to be converted')
			],
            'lang' => [
				'type' => Type::String(),
				'description' => _('Language of recording file')
			],
            'temporary' => [
				'type' => Type::id(),
				'description' => _('temporary')
			]
		];
    }

     /**
     * output fields
     */
    public function getRecodingOutputs()
    {
        return [
			'status' => [
				'type' => Type::boolean(),
				'resolve' => function ($payload) {
					return $payload['status'];
				}
			],
			'message' => [
				'type' => Type::string(),
				'resolve' => function ($payload) {
					return $payload['message'];
				}
			],
			'id' => [
				'type' => Type::string(),
				'resolve' => function ($payload) {
					return isset($payload['id']) ? $payload['id'] : null;
				}
			],
		];
    }

    public function addUpdateRecordings($input)
    {
        //name mandatory
        if(!isset($input['name']) || empty($input['name'])) {
            return ['message' => _('Please provide a name for system recoding'), 'status' => false];
        }
        //validate record name exist or not
        $all_records = $this->freepbx->recordings->getAll();
        $tmp_id = '';
        if(isset($input['id'])){
            $tmp_id = $input['id'];
        }
        foreach($all_records as $tmp_record) {
            if($tmp_record['id'] != $tmp_id && $tmp_record['displayname'] == $input['name']){
                return ['message' => sprintf(_("The Name '%s' already exists, please provide a different name."),$input['name']), 'status' => false];
            }
        }
        // validate file existis or not
        if(!isset($input['playback']) || !is_array($input['playback']) || empty($input['playback'])) {
            return ['message' => _('Please provide atleast one existing playback file name'), 'status' => false];
        }
        
        //convert file to other specified formats formats
        if(isset($input['codecs'])) {
            foreach ($input['playback'] as $playbck) {
                $file = $input['language']."/".$playbck;
                foreach ($input['codecs'] as $codec) {
                    $fileArr = [
                        'file' => $file,
                        'name' => $playbck,
                        'codec' => $codec,
                        'lang' => $input['language'],
                        'temporary' => 0
                    ];
                    $result = $this->freepbx->recordings->convertFiles($fileArr);
                }
            }
        }

        $existingFiles = [];
        foreach ($input['playback'] as $pfile) {
            $fileDetails = $this->freepbx->recordings->fileStatus($pfile);
            if($fileDetails) {
                $existingFiles[] = $pfile;
            }
        }
        if(!$existingFiles) {
            return ['message' => _("Playback file name/s provided doesn't exists"), 'status' => false];
        }

        if(isset($input['id']) && $input['id'] > 0) {
            $check = "SELECT * FROM recordings WHERE id = ?";
            $sth = $this->freepbx->recordings->db->prepare($check);
            $sth->execute(array($input['id']));
            $rec = $sth->fetchAll(\PDO::FETCH_ASSOC);
            if($rec) {
                $this->freepbx->recordings->updateRecording($input['id'],$input['name'],$input['description'],implode("&",$existingFiles),$input['fcode'],$input['fcode_pass'],$input['language']);
                return ['message' => _("Recording updated succefully"), 'status' => true, 'id' => $input['id']];
            } else {
                return ['message' => _("The id provided is invalid"), 'status' => false, 'id' => $input['id']];
            }
        } else {
            $id = $this->freepbx->recordings->addRecording($input['name'],$input['description'],implode("&",$existingFiles),$input['fcode'],$input['fcode_pass'],$input['language']);
            if($id) {
                return ['message' => _("Recording added succefully"), 'status' => true, 'id' => $id];
            } else {
                return ['message' => _("Sorry unable to add the recording"), 'status' => false];
            }
        }
    }

    /**
     * input validation for file convert
     */
    public function InputValidate($input)
    {
        if(!isset($input['file'])) {
            return ['message' => _("please provide 'file' input field"), 'status' => false];
        }

        if(!isset($input['name'])) {
            return ['message' => _("please provide 'name' input field"), 'status' => false];
        }

        if(!isset($input['codec'])) {
            return ['message' => _("please provide 'codec' input field"), 'status' => false];
        }

        if(!isset($input['lang'])) {
            return ['message' => _("please provide 'lang' input field"), 'status' => false];
        }
        return ['status' => true ,'message' => _('all inputs are provided')];
    }
}