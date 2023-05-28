<?php

namespace FreePBX\modules\Cdr\Api\Gql;

use GraphQLRelay\Relay;
use GraphQL\Type\Definition\Type;
use FreePBX\modules\Api\Gql\Base;
use GraphQL\Type\Definition\EnumType;

class Cdr extends Base {
	protected $module = 'cdr';

	public function queryCallback() {
		if($this->checkAllReadScope()) {
			return function() {
				return [
					'fetchAllCdrs' => [
						'type' => $this->typeContainer->get('cdr')->getConnectionType(),
						'description' => _('CDR Reports'),
						'args' => array_merge(
							Relay::forwardConnectionArgs(),
							[
								'first' => [
									'type' => Type::int(),
									'description' => _('Limit value')
								],
								'after' => [
									'type' => Type::int(),
									'description' => _('Offset value')
								],
								'orderby' => [
									'type' => new EnumType([
										'name' => 'cdrOrderBy',
										'description' => _('Dispositions represent the final state of the call from the perspective of Party A'),
										'values' => [
											'duration' => [
												'value' => 'duration',
												'description' => _('The channel was never answered. This is the default disposition for an unanswered channel.')
											],
											'date' => [
												'value' => 'timestamp',
												'description' => _("The channel dialed something that was congested.")
											]
										]
									]),
									'description' => _('The final known disposition of the CDR record'),
									'defaultValue' => 'timestamp'
								],
								'startDate' => [
									'type' => Type::string(),
									'description' => _('Start Date')
								],
								'endDate' => [
									'type' => Type::string(),
									'description' => _('End Date')
								],
							]
						),
						'resolve' => function($root, $args) {
							$after = !empty($args['after']) ? Relay::fromGlobalId($args['after'])['id'] : null;
							$before = !empty($args['before']) ? Relay::fromGlobalId($args['before'])['id'] : null;
							$first = !empty($args['first']) ? $args['first'] : null;
							$last = !empty($args['last']) ? $args['last'] : null;
							// validating dates
							if(isset($args['startDate']) && !empty($args['startDate'])){
								if(!$this->validateDate($args['startDate'])){
									return ['status' => false, 'message' => _('Invalid Start Date Format(YYYY-MM-DD)')];
								}
							}
							if(isset($args['endDate']) && !empty($args['endDate'])){
								if(!$this->validateDate($args['endDate'])){
									return ['status' => false, 'message' => _('Invalid End Date Format(YYYY-MM-DD)')];
								}
							}
							if(isset($args['startDate']) && !isset($args['endDate'])){
									return ['status' => false, 'message' => _('End Date is required..!!')];
							}
							if(!isset($args['startDate']) && isset($args['endDate'])){
									return ['status' => false, 'message' => _('Start Date is required..!!')];
							}
							if(isset($args['startDate']) && isset($args['endDate'])){
								if ($args['endDate'] < $args['startDate']){
									return ['status' => false, 'message' => _('End Date should be greater than Start Date..!!')];
								}
							}
							$res = Relay::connectionFromArraySlice(
								$this->freepbx->Cdr->getGraphQLCalls($after, $first, $before, $last, $args['orderby'],$args['startDate'],$args['endDate']),
								$args,
								[
									'sliceStart' => !empty($after) ? $after : 0,
									'arrayLength' => $this->freepbx->Cdr->getTotal()
								]
							);
							if(count($res['edges']) > 0){
								$message = _('CDR data found successfully');
							}else{
								$message = _('No Data Found');
							}
							return ['response' => $res, 'status' => true, 'message' => $message];

						},
					],
					'fetchCdr' => [
						'type' => $this->typeContainer->get('cdr')->getObject(),
						'args' => [
							'id' => [
								'type' => Type::id(),
								'description' => _('The ID'),
							]
						],
						'resolve' => function($root, $args) {
							$record = $this->freepbx->Cdr->getGraphQLRecordByID($args['id']);
							if (!empty($record)) {
								return ['response' => $record, 'status' => true, 'message' => _('CDR data found successfully')];
							} else {
								return ['status' => false, 'message' => _('CDR data does not exists')];
							}
						}
					]
				];
			};
		}
	}

	public function initializeTypes() {
		$user = $this->typeContainer->create('cdr');
		$user->setDescription('Used to manage a system wide list of blocked callers');

		$user->setGetNodeCallback(function($id) {
			$record = $this->freepbx->Cdr->getRecordByID($id);
			return !empty($record) ? $record : null;
		});

		$user->addInterfaceCallback(function() {
			return [$this->getNodeDefinition()['nodeInterface']];
		});

		$user->addFieldCallback(function() {
			return [
				'id' => Relay::globalIdField('cdr', function($row) {
					if(isset($row['uniqueid'])){
						return $row['uniqueid'];
					}elseif(isset($row['response'])){
						return  $row['response']['uniqueid'];
					}
					return null;
				}),
				'uniqueid' => [
					'type' => Type::string(),
					'description' => _('A unique identifier for the Party A channel'),
					'resolve' => function($row){
						if(isset($row['uniqueid'])){
							return $row['uniqueid'];
						}elseif(isset($row['response'])){
							return  $row['response']['uniqueid'];
						}
						return null;
					}
				],
				'calldate' => [
					'type' => Type::string(),
					'description' => _('The time the CDR was created'),
					'resolve' => function($row){
						if(isset($row['calldate'])){
							return $row['calldate'];
						}elseif(isset($row['response'])){
							return  $row['response']['calldate'];
						}
						return null;
					}
				],
				'timestamp' => [
					'type' => Type::int(),
					'description' => _('The time the CDR was created'),
					'resolve' => function($row){
						if(isset($row['timestamp'])){
							return $row['timestamp'];
						}elseif(isset($row['response'])){
							return  $row['response']['timestamp'];
						}
						return null;
					}
				],
				'clid' => [
					'type' => Type::string(),
					'description' => _('The Caller ID with text'),
					'resolve' => function($row){
						if(isset($row['clid'])){
							return $row['clid'];
						}elseif(isset($row['response'])){
							return  $row['response']['clid'];
						}
						return null;
					}
				],
				'src' => [
					'type' => Type::string(),
					'description' => _('The Caller ID Number'),
					'resolve' => function($row){
						if(isset($row['src'])){
							return $row['src'];
						}elseif(isset($row['response'])){
							return  $row['response']['src'];
						}
						return null;
					}
				],
				'dst' => [
					'type' => Type::string(),
					'description' => _('The destination extension'),
					'resolve' => function($row){
						if(isset($row['dst'])){
							return $row['dst'];
						}elseif(isset($row['response'])){
							return  $row['response']['dst'];
						}
						return null;
					}
				],
				'dcontext' => [
					'type' => Type::string(),
					'description' => _('The destination context'),
					'resolve' => function($row){
						if(isset($row['dcontext'])){
							return $row['dcontext'];
						}elseif(isset($row['response'])){
							return  $row['response']['dcontext'];
						}
						return null;
					}
				],
				'channel' => [
					'type' => Type::string(),
					'description' => _('The name of the Party A channel'),
					'resolve' => function($row){
						if(isset($row['channel'])){
							return $row['channel'];
						}elseif(isset($row['response'])){
							return  $row['response']['channel'];
						}
						return null;
					}
				],
				'dstchannel' => [
					'type' => Type::string(),
					'description' => _('The name of the Party B channel'),
					'resolve' => function($row){
						if(isset($row['dstchannel'])){
							return $row['dstchannel'];
						}elseif(isset($row['response'])){
							return  $row['response']['dstchannel'];
						}
						return null;
					}
				],
				'lastapp' => [
					'type' => Type::string(),
					'description' => _('The last application the Party A channel executed'),
					'resolve' => function($row){
						if(isset($row['lastapp'])){
							return $row['lastapp'];
						}elseif(isset($row['response'])){
							return  $row['response']['lastapp'];
						}
						return null;
					}
				],
				'lastdata' => [
					'type' => Type::string(),
					'description' => _('The application data for the last application the Party A channel executed'),
					'resolve' => function($row){
						if(isset($row['lastdata'])){
							return $row['lastdata'];
						}elseif(isset($row['response'])){
							return  $row['response']['lastdata'];
						}
						return null;
					}
				],
				'duration' => [
					'type' => Type::int(),
					'description' => _('The time in seconds from start until end'),
					'resolve' => function($row){
						if(isset($row['duration'])){
							return $row['duration'];
						}elseif(isset($row['response'])){
							return  $row['response']['duration'];
						}
						return null;
					}
				],
				'billsec' => [
					'type' => Type::int(),
					'description' => _('The time in seconds from answer until end'),
					'resolve' => function($row){
						if(isset($row['billsec'])){
							return $row['billsec'];
						}elseif(isset($row['response'])){
							return  $row['response']['billsec'];
						}
						return null;
					}
				],
				'disposition' => [
					'type' => Type::string(),
					'description' => _('The final known disposition of the CDR record'),
					'resolve' => function($row) {
						$disposition = "";
						if(isset($row['disposition'])){
							$disposition = strtolower($row['disposition']);
						}elseif(isset($row['response'])){
							$disposition =  strtolower($row['response']['disposition']);
						}
						switch ($disposition) {
							case "noanswer":
								return 'NO ANSWER';
							case "no answer":
								return 'NO ANSWER';
							case "congestion":
								return 'CONGESTION';
							case "failed":
								return 'FAILED';
							case "busy":
								return 'BUSY';
							case "answered":
								return 'ANSWERED';
							default:
								return $disposition;
						}
					}
				],
				'amaflags' => [
					'type' => Type::string(),
					'description' => _('A flag specified on the Party A channel. AMA Flags are set on a channel and are conveyed in the CDR. They inform billing systems how to treat the particular CDR. Asterisk provides no additional semantics regarding these flags - they are present simply to help external systems classify CDRs'),
					'resolve' => function($payload) {
						$amaflags = "";
						if(isset($row['amaflags'])){
							$amaflags = $row['amaflags'];
						}elseif(isset($row['response'])){
							$amaflags =  $row['response']['amaflags'];
						}
						switch ($amaflags) {
							case 0:
								return 'DOCUMENTATION';
								break;
							case 1:
								return 'IGNORE';
								break;
							case 2:
								return 'BILLING';
								break;
							case 3:
							default:
								return 'DEFAULT';
						}
					}
				],
				'accountcode' => [
					'type' => Type::string(),
					'description' => _('An account code associated with the Party A channel'),
					'resolve' => function($row){
						if(isset($row['accountcode'])){
							return $row['accountcode'];
						}elseif(isset($row['response'])){
							return  $row['response']['accountcode'];
						}
						return null;
					}
				],
				'userfield' => [
					'type' => Type::string(),
					'description' => _('A user defined field set on the channels. If set on both the Party A and Party B channel, the userfields of both are concatenated and separated by a ;'),
					'resolve' => function($row){
						if(isset($row['userfield'])){
							return $row['userfield'];
						}elseif(isset($row['response'])){
							return  $row['response']['userfield'];
						}
						return null;
					}
				],
				'did' => [
					'type' => Type::string(),
					'description' => _('The DID that was used to reach this destination'),
					'resolve' => function($row){
						if(isset($row['did'])){
							return $row['did'];
						}elseif(isset($row['response'])){
							return  $row['response']['did'];
						}
						return null;
					}
				],
				'recordingfile' => [
					'type' => Type::string(),
					'description' => _('The recording file of this entry'),
					'resolve' => function($row){
						if(isset($row['recordingfile'])){
							return $row['recordingfile'];
						}elseif(isset($row['response'])){
							return  $row['response']['recordingfile'];
						}
						return null;
					}
				],
				'cnum' => [
					'type' => Type::string(),
					'description' => _('The Caller ID Number'),
					'resolve' => function($row){
						if(isset($row['cnum'])){
							return $row['cnum'];
						}elseif(isset($row['response'])){
							return  $row['response']['cnum'];
						}
						return null;
					}
				],
				'outbound_cnum' => [
					'type' => Type::string(),
					'description' => _('The Outbound Caller ID Number'),
					'resolve' => function($row){
						if(isset($row['outbound_cnum'])){
							return $row['outbound_cnum'];
						}elseif(isset($row['response'])){
							return  $row['response']['outbound_cnum'];
						}
						return null;
					}
				],
				'outbound_cnam' => [
					'type' => Type::string(),
					'description' => _('The Outbound Caller ID Name'),
					'resolve' => function($row){
						if(isset($row['outbound_cnam'])){
							return $row['outbound_cnam'];
						}elseif(isset($row['response'])){
							return  $row['response']['outbound_cnam'];
						}
						return null;
					}
				],
				'dst_cnam' => [
					'type' => Type::string(),
					'description' => _('The destination Caller ID Name'),
					'resolve' => function($row){
						if(isset($row['dst_cnam'])){
							return $row['dst_cnam'];
						}elseif(isset($row['response'])){
							return  $row['response']['dst_cnam'];
						}
						return null;
					}
				],
				'linkedid' => [
					'type' => Type::string(),
					'description' => _('Description of the blocked number'),
					'resolve' => function($row){
						if(isset($row['linkedid'])){
							return $row['linkedid'];
						}elseif(isset($row['response'])){
							return  $row['response']['linkedid'];
						}
						return null;
					}
				],
				'peeraccount' => [
					'type' => Type::string(),
					'description' => _('The account code of the Party B channel'),
					'resolve' => function($row){
						if(isset($row['peeraccount'])){
							return $row['peeraccount'];
						}elseif(isset($row['response'])){
							return  $row['response']['peeraccount'];
						}
						return null;
					}
				],
				'sequence' => [
					'type' => Type::string(),
					'description' => _('A numeric value that, combined with uniqueid and linkedid, can be used to uniquely identify a single CDR record'),
					'resolve' => function($row){
						if(isset($row['sequence'])){
							return $row['sequence'];
						}elseif(isset($row['response'])){
							return  $row['response']['sequence'];
						}
						return null;
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
			];
		});

		$user->setConnectionResolveNode(function ($edge) {
			return $edge['node'];
		});

		$user->setConnectionFields(function() {
			return [
				'totalCount' => [
					'type' => Type::int(),
					'description' => _('A count of the total number of objects in this connection, ignoring pagination. This allows a client to fetch the first five objects by passing "5" as the argument to "first", then fetch the total count so it could display "5 of 83", for example.'),
					'resolve' => function($value) {
						return $this->freepbx->Cdr->getTotal();
					}
				],
				'cdrs' => [
					'type' => Type::listOf($this->typeContainer->get('cdr')->getObject()),
					'description' => _('A list of all of the objects returned in the connection. This is a convenience field provided for quickly exploring the API; rather than querying for "{ edges { node } }" when no edge data is needed, this field can be be used instead. Note that when clients like Relay need to fetch the "cursor" field on the edge to enable efficient pagination, this shortcut cannot be used, and the full "{ edges { node } }" version should be used instead.'),
					'resolve' => function($root, $args) {
						if(isset($root['response'])){
							$data = array_map(function($row){
								return $row['node'];
							},$root['response']['edges']);
							return $data;
						}else{
							return null;
						}
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
			];
		});
	}

	private function validateDate($date){
		//format YYYY-mm-dd						
		if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$date)) {
			return 1;
		} else {
			return 0;
		}
	}

}
