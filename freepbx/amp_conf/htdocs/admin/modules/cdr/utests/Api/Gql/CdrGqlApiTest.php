<?php 
namespace FreepPBX\cdr\utests;

require_once('../api/utests/ApiBaseTestCase.php');

use FreePBX\modules\cdr;
use Exception;
use FreePBX\modules\Api\utests\ApiBaseTestCase;

class CdrGqlApiTest extends ApiBaseTestCase {
	protected static $cdr;
		
	/**
	 * setUpBeforeClass
	 *
	 * @return void
	 */
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();
		self::$cdr = self::$freepbx->cdr;
	}
		
	/**
	 * tearDownAfterClass
	 *
	 * @return void
	 */
	public static function tearDownAfterClass() {
		parent::tearDownAfterClass();
	}

	/**
	 * test_fetchAllCdr_all_good_should_return_true
	 *
	 * @return void
	 */
	public function test_fetchAllCdr_all_good_should_return_true(){

		$mockHelper = $this->getMockBuilder(\FreePBX\modules\cdr\Cdr::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->setMethods(array('getGraphQLCalls','getTotal'))
			->getMock();

		$mockHelper->method('getGraphQLCalls')
			->willReturn(array(
								array(
										'calldate' => '2021-06-04 21:12:33',
										'clid' => "WALDROP CONNIE " ,
										'src' => 11111,
										'dst' => 's',
										'dcontext' => 'from-pstn',
										'channel' => 'SIP/fpbx-1-2cSnGYAnRDnB-0000000a',
										'dstchannel' => '',
										'lastapp' => 'Playback',
										'lastdata' => 'ss-noservice',
										'duration' => 6,
										'billsec' => 6,
										'disposition' => 'ANSWERED',
										'amaflags' => 3,
										'accountcode' => '',
										'uniqueid' => 123456,
										'userfield' => '',
										'did' => '',
										'recordingfile' => '',
										'cnum' => '',
										'cnam' => '',
										'outbound_cnum' => '',
										'outbound_cnam' => '',
										'dst_cnam' => '',
										'linkedid' => 123456,
										'peeraccount' => '',
										'sequence' => 10,
										'timestamp' => 1622841153,
									)
								));

		$mockHelper->method('getTotal')
			->willReturn(1);

		self::$freepbx->Cdr = $mockHelper;
		$response = $this->request("query{
										fetchAllCdrs (
											first : 1
											after : 1
											orderby : duration
										)
										{
											status
											message
											totalCount
											cdrs {
												uniqueid
												calldate
												timestamp
												clid
												src
												dst
												dcontext
												channel
												dstchannel
												lastapp
												lastdata
												duration
												billsec
												disposition 
												accountcode
												userfield
												did
												recordingfile
												cnum
												outbound_cnum
												outbound_cnam
												dst_cnam
												linkedid
												peeraccount
												sequence
												amaflags
											}
										}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"data":{"fetchAllCdrs":{"status":true,"message":"CDR data found successfully","totalCount":1,"cdrs":[{"uniqueid":"123456","calldate":"2021-06-04 21:12:33","timestamp":1622841153,"clid":"WALDROP CONNIE ","src":"11111","dst":"s","dcontext":"from-pstn","channel":"SIP\/fpbx-1-2cSnGYAnRDnB-0000000a","dstchannel":"","lastapp":"Playback","lastdata":"ss-noservice","duration":6,"billsec":6,"disposition":"","accountcode":"","userfield":"","did":"","recordingfile":"","cnum":"","outbound_cnum":"","outbound_cnam":"","dst_cnam":"","linkedid":"123456","peeraccount":"","sequence":"10","amaflags":"DOCUMENTATION"}]}}}',$json);
		
		$this->assertEquals(200, $response->getStatusCode());
	}


	/**
	 * test_fetchAllCdr_when_wrong_parameter_sent_should_return_error_and_false
	 *
	 * @return void
	 */
	public function test_fetchAllCdr_when_wrong_parameter_sent_should_return_error_and_false(){

		$response = $this->request("query{
										fetchAllCdrs (
											first : 1
											after : 1
											orderby : duration
										)
										{
											cdrs {
												uniqueid
												lorem
											}
										}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"errors":[{"message":"Cannot query field \"lorem\" on type \"cdr\".","status":false}]}',$json);
		
		$this->assertEquals(400, $response->getStatusCode());
	}

	/**
	 * test_fetchAllCdr_when_invalid_start_date_is_sent_should_return_error_and_false
	 *
	 * @return void
	 */
	public function test_fetchAllCdr_when_invalid_start_date_is_sent_should_return_error_and_false(){

		$response = $this->request("
									query{
									fetchAllCdrs (
										first : 4
										after : 0
										orderby : duration
										startDate:\"123-05-21\"
										endDate:\"2021-05-21\"
									)
									{
										cdrs {
										id
										}
										totalCount
										status
										message
									}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"errors":[{"message":"Invalid Start Date Format(YYYY-MM-DD)","status":false}]}',$json);
		
		$this->assertEquals(400, $response->getStatusCode());
	}

	/**
	 * test_fetchAllCdr_when_invalid_end_date_is_sent_should_return_error_and_false
	 *
	 * @return void
	 */
	public function test_fetchAllCdr_when_invalid_end_date_is_sent_should_return_error_and_false(){

		$response = $this->request("
									query{
									fetchAllCdrs (
										first : 4
										after : 0
										orderby : duration
										startDate:\"2021-05-21\"
										endDate:\"123-05-21\"
									)
									{
										cdrs {
										id
										}
										totalCount
										status
										message
									}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"errors":[{"message":"Invalid End Date Format(YYYY-MM-DD)","status":false}]}',$json);
		
		$this->assertEquals(400, $response->getStatusCode());
	}


	/**
	 * test_fetchAllCdr_when_start_date_is_greater_than_end_date_should_return_error_and_false 
	 *
	 * @return void
	 */
	public function test_fetchAllCdr_when_start_date_is_greater_than_end_date_should_return_error_and_false (){

		$response = $this->request("
									query{
									fetchAllCdrs (
										first : 4
										after : 0
										orderby : duration
										startDate:\"2021-05-26\"
										endDate:\"2021-05-21\"
									)
									{
										cdrs {
										id
										}
										totalCount
										status
										message
									}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"errors":[{"message":"End Date should be greater than Start Date..!!","status":false}]}',$json);
		
		$this->assertEquals(400, $response->getStatusCode());
	}


	/**
	 * test_fetchAllCdr_when_only_start_date_is_given_should_return_error_and_false
	 *
	 * @return void
	 */
	public function test_fetchAllCdr_when_only_start_date_is_given_should_return_error_and_false(){

		$response = $this->request("
									query{
									fetchAllCdrs (
										first : 4
										after : 0
										orderby : duration
										startDate:\"2021-05-26\"
									)
									{
										cdrs {
										id
										}
										totalCount
										status
										message
									}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"errors":[{"message":"End Date is required..!!","status":false}]}',$json);
		
		$this->assertEquals(400, $response->getStatusCode());
	}

	

	/**
	 * test_fetchAllCdr_when_only_end_date_is_given_should_return_error_and_false
	 *
	 * @return void
	 */
	public function test_fetchAllCdr_when_only_end_date_is_given_should_return_error_and_false(){

		$response = $this->request("
									query{
									fetchAllCdrs (
										first : 4
										after : 0
										orderby : duration
										endDate:\"2021-05-26\"
									)
									{
										cdrs {
										id
										}
										totalCount
										status
										message
									}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"errors":[{"message":"Start Date is required..!!","status":false}]}',$json);
		
		$this->assertEquals(400, $response->getStatusCode());
	}

	/**
	 * test_fetchCdr_all_good_should_return_true
	 *
	 * @return void
	 */
	public function test_fetchCdr_all_good_should_return_true(){

		$mockHelper = $this->getMockBuilder(\FreePBX\modules\cdr\Cdr::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->setMethods(array('getGraphQLRecordByID'))
			->getMock();

		$mockHelper->method('getGraphQLRecordByID')
			->willReturn(array(
								'calldate' => '2021-06-04 21:12:33',
								'clid' => "WALDROP CONNIE " ,
								'src' => 11111,
								'dst' => 's',
								'dcontext' => 'from-pstn',
								'channel' => 'SIP/fpbx-1-2cSnGYAnRDnB-0000000a',
								'dstchannel' => '',
								'lastapp' => 'Playback',
								'lastdata' => 'ss-noservice',
								'duration' => 6,
								'billsec' => 6,
								'disposition' => 'ANSWERED',
								'amaflags' => 3,
								'accountcode' => '',
								'uniqueid' => 123456,
								'userfield' => '',
								'did' => '',
								'recordingfile' => '',
								'cnum' => '',
								'cnam' => '',
								'outbound_cnum' => '',
								'outbound_cnam' => '',
								'dst_cnam' => '',
								'linkedid' => 123456,
								'peeraccount' => '',
								'sequence' => 10,
								'timestamp' => 1622841153,
								));

		self::$freepbx->Cdr = $mockHelper;
		$response = $this->request("{
										fetchCdr(id:\"123456\" ) {
											status
											message
											uniqueid
											calldate
											timestamp
											clid
											src
											dst
											dcontext
											channel
											dstchannel
											lastapp
											lastdata
											duration
											billsec
											disposition 
											accountcode
											userfield
											did
											recordingfile
											cnum
											outbound_cnum
											outbound_cnam
											dst_cnam
											linkedid
											peeraccount
											sequence
											amaflags
										}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"data":{"fetchCdr":{"status":true,"message":"CDR data found successfully","uniqueid":"123456","calldate":"2021-06-04 21:12:33","timestamp":1622841153,"clid":"WALDROP CONNIE ","src":"11111","dst":"s","dcontext":"from-pstn","channel":"SIP\/fpbx-1-2cSnGYAnRDnB-0000000a","dstchannel":"","lastapp":"Playback","lastdata":"ss-noservice","duration":6,"billsec":6,"disposition":"","accountcode":"","userfield":"","did":"","recordingfile":"","cnum":"","outbound_cnum":"","outbound_cnam":"","dst_cnam":"","linkedid":"123456","peeraccount":"","sequence":"10","amaflags":"DOCUMENTATION"}}}',$json);
		
		$this->assertEquals(200, $response->getStatusCode());
	}

	/**
	 * test_fetchCdr_on_invalid_id_should_return_false
	 *
	 * @return void
	 */
	public function test_fetchCdr_on_invalid_id_should_return_false(){

		$mockHelper = $this->getMockBuilder(\FreePBX\modules\cdr\Cdr::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->setMethods(array('getGraphQLRecordByID'))
			->getMock();

		$mockHelper->method('getGraphQLRecordByID')
			->willReturn(false);

		self::$freepbx->Cdr = $mockHelper;
		$response = $this->request("{
										fetchCdr(id:\"123456\" ) {
											status
											message
											uniqueid
										}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"errors":[{"message":"CDR data does not exists","status":false}]}',$json);
		
		$this->assertEquals(400, $response->getStatusCode());
	}

	
	/**
	 * test_fetchCdr_on_invalid_query_field_should_return_false
	 *
	 * @return void
	 */
	public function test_fetchCdr_on_invalid_query_field_should_return_false(){

		$mockHelper = $this->getMockBuilder(\FreePBX\modules\cdr\Cdr::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->setMethods(array('getGraphQLRecordByID'))
			->getMock();

		$mockHelper->method('getGraphQLRecordByID')
			->willReturn(false);

		self::$freepbx->Cdr = $mockHelper;
		$response = $this->request("{
										fetchCdr(id:\"123456\" ) {
											lorem
											message
											uniqueid
										}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"errors":[{"message":"Cannot query field \"lorem\" on type \"cdr\".","status":false}]}',$json);
		
		$this->assertEquals(400, $response->getStatusCode());
	}
}