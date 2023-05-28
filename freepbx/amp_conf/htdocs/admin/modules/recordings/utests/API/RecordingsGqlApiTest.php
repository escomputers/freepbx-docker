<?php 
namespace FreepPBX\recordings\utests;

require_once('../api/utests/ApiBaseTestCase.php');

use FreePBX\modules\Recordings;
use Exception;
use FreePBX\modules\Api\utests\ApiBaseTestCase;

class RecordingsGqlApiTest extends ApiBaseTestCase {
    protected static $recordings;
    /**
   * setUpBeforeClass
   *
   * @return void
   */
  public static function setUpBeforeClass() {
    parent::setUpBeforeClass();
    self::$recordings = self::$freepbx->recordings;
  }
        
   /**
   * tearDownAfterClass
   *
   * @return void
   */
  public static function tearDownAfterClass() {
    parent::tearDownAfterClass();
  }

  public function fileReturn()
  {
    return array("en" => array("wav" => "1-yes-2-no.wav"));
  }

  public function recordingGetAll()
  {
    return array(
        "0" => array(
                "id" => 1,
                "displayname" => "rec1",
                "filename" => "1-yes-2-no&Randulo-allison&T-to-disable-ancmnt",
                "description" => "rec",
                "fcode" => 0,
                "fcode_pass" => "",
                "fcode_lang" => "en",
                "files" => array(
                        "1-yes-2-no" => array (
                                "en" => array(
                                        "alaw" => "1-yes-2-no.alaw",
                                        "g722" => "1-yes-2-no.g722",
                                        "sln16" => "1-yes-2-no.sln16",
                                        "ulaw" => "1-yes-2-no.ulaw",
                                        "wav" => "1-yes-2-no.wav"
                                    )
                            ),
                        "Randulo-allison" => array(
                                "en" => array(
                                        "alaw" => "Randulo-allison.alaw",
                                        "g722" => "Randulo-allison.g722",
                                        "sln16" => "Randulo-allison.sln16",
                                        "ulaw" => "Randulo-allison.ulaw",
                                        "wav" => "Randulo-allison.wav"
                                    )
                            ),
                        "T-to-disable-ancmnt" => array(
                                "en" => array(
                                        "alaw" => "T-to-disable-ancmnt.alaw",
                                        "g722" => "T-to-disable-ancmnt.g722",
                                        "sln16" => "T-to-disable-ancmnt.sln16",
                                        "ulaw" => "T-to-disable-ancmnt.ulaw",
                                        "wav" => "T-to-disable-ancmnt.wav"
                                    )
                            )
                    ),
                "languages" => array(
                        "0" => "en"
                    ),
                "missing" => array(
                        "languages" => array(
                            ),
                        "formats" => array(
                            )
                    )
            ),
        "1" => array(
                "id" => 3,
                "displayname" => "rec2",
                "filename" => "1-yes-2-no&SIP_Test_Failure&custom/AirtelNewHiphopRingtone2066788122",
                "description" => "rec2",
                "fcode" => 0,
                "fcode_pass" => "",
                "fcode_lang" => "en",
                "files" => array(
                        "1-yes-2-no" => array(
                                "en" => array(
                                        "alaw" => "1-yes-2-no.alaw",
                                        "g722" => "1-yes-2-no.g722",
                                        "sln16" => "1-yes-2-no.sln16",
                                        "ulaw" => "1-yes-2-no.ulaw",
                                        "wav" => "1-yes-2-no.wav"
                                    )
    
                            ),
                        "SIP_Test_Failure" => array(
                                "en" => array(
                                        "alaw" => "SIP_Test_Failure.alaw",
                                        "g722" => "SIP_Test_Failure.g722",
                                        "sln16" => "SIP_Test_Failure.sln16",
                                        "ulaw" => "SIP_Test_Failure.ulaw",
                                        "wav" => "SIP_Test_Failure.wav"
                                    )
    
                            ),
                        "custom/AirtelNewHiphopRingtone2066788122" => array(
                                "en" => array(
                                        "alaw" => "custom/AirtelNewHiphopRingtone2066788122.alaw",
                                        "g722" => "custom/AirtelNewHiphopRingtone2066788122.g722",
                                        "sln16" => "custom/AirtelNewHiphopRingtone2066788122.sln16",
                                        "ulaw" => "custom/AirtelNewHiphopRingtone2066788122.ulaw",
                                        "wav" => "custom/AirtelNewHiphopRingtone2066788122.wav"
                                    )
    
                            )
                    ),
                "languages" => array(
                        "0" => "en"
                    ),
                "missing" => array(
                        "languages" => array(
                            ),
                        "formats" => array(
                            )
                    )
            )
      );
    
  }

  public function systemRecordings()
  {
    return array(
      "0" => array(
          "name" => "1-for-am-2-for-pm",
          "languages" => array(
                  "en" => "en"
              ),
          "formats" => array(
                  "ulaw" => "ulaw",
                  "g722" => "g722",
                  "sln16" => "sln16",
                  "alaw" => "alaw"
          ),
          "paths" => array(
                  "en" => "en/1-for-am-2-for-pm"
              )
      )
    );
  }

   /***
   * test_addRecording_when_all_good_should_return_true
   */
  public function test_addRecording_when_all_good_should_return_true()
  {
    $mockHelper = $this->getMockBuilder(\FreePBX\modules\recordings\Recordings::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('fileStatus','addRecording','getAll','convertFiles'))
    ->getMock();

    $mockHelper->method('fileStatus')
      ->willReturn($this->fileReturn());
  
    $mockHelper->method('addRecording')
      ->willReturn(2);

    $mockHelper->method('getAll')
      ->willReturn($this->recordingGetAll());

    $mockHelper->method('convertFiles')
      ->willReturn(array("status" => true, "name" => "1-yes-2-no"));

    self::$freepbx->recordings = $mockHelper;

    $response = $this->request("mutation {
                    addRecording(input:{ 
                        name: \"rec3\"
                        description: \"rec3\"
                        fcode: 0
                        fcode_pass: \"\"
                        language: \"en\"
                        playback: [\"1-yes-2-no\"]
                        codecs: [\"wav\"]
                    })
                    {
                        status message id
                    }
                }");
        
     $json = (string)$response->getBody();
     $this->assertEquals('{"data":{"addRecording":{"status":true,"message":"Recording added succefully","id":"2"}}}',$json);
        
     $this->assertEquals(200, $response->getStatusCode());
  }

  /***
   * test_addRecording_when_existing_name_given_should_return_false
   */
  public function test_addRecording_when_existing_name_given_should_return_false()
  {
    $mockHelper = $this->getMockBuilder(\FreePBX\modules\recordings\Recordings::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('fileStatus','addRecording','getAll'))
    ->getMock();

    $mockHelper->method('fileStatus')
      ->willReturn($this->fileReturn());
  
    $mockHelper->method('addRecording')
      ->willReturn(2);

    $mockHelper->method('getAll')
      ->willReturn($this->recordingGetAll());

    $mockHelper->method('convertFiles')
      ->willReturn(array("status" => true, "name" => "1-yes-2-no"));

    self::$freepbx->recordings = $mockHelper;

    $response = $this->request("mutation {
                    addRecording(input:{ 
                        name: \"rec2\"
                        description: \"rec2\"
                        fcode: 0
                        fcode_pass: \"\"
                        language: \"en\"
                        playback: [\"1-yes-2-no\"]
                        codecs: [\"wav\"]
                    })
                    {
                        status message id
                    }
                }");
        
     $json = (string)$response->getBody();
     $this->assertEquals('{"errors":[{"message":"The Name '."'".'rec2'."'".' already used, please use a different name.","status":false}]}',$json);
        
     $this->assertEquals(400, $response->getStatusCode());
  }

  /***
   * test_addRecording_when_provided_file_not_exists_given_should_return_false
   */
  public function test_addRecording_when_provided_file_not_exists_given_should_return_false()
  {
    $mockHelper = $this->getMockBuilder(\FreePBX\modules\recordings\Recordings::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('fileStatus','addRecording','getAll'))
    ->getMock();

    $mockHelper->method('fileStatus')
      ->willReturn([]);
  
    $mockHelper->method('addRecording')
      ->willReturn(2);

    $mockHelper->method('getAll')
      ->willReturn($this->recordingGetAll());

    $mockHelper->method('convertFiles')
      ->willReturn(array("status" => true, "name" => "1-yes-2-no"));

    self::$freepbx->recordings = $mockHelper;

    $response = $this->request("mutation {
                    addRecording(input:{ 
                        name: \"rec4\"
                        description: \"rec2\"
                        fcode: 0
                        fcode_pass: \"\"
                        language: \"en\"
                        playback: [\"1-yes-2-no\"]
                        codecs: [\"wav\"]
                    })
                    {
                        status message id
                    }
                }");
        
     $json = (string)$response->getBody();
     $this->assertEquals('{"errors":[{"message":"Playback file name\/s provided doesn'."'".'t exists","status":false}]}',$json);
        
     $this->assertEquals(400, $response->getStatusCode());
  }

   /***
   * test_fetchAllRecordings_when_all_good_should_list_of_recordings
   */
  public function test_fetchAllRecordings_when_all_good_should_list_of_recordings()
  {
    $mockHelper = $this->getMockBuilder(\FreePBX\modules\recordings\Recordings::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('getAll'))
        ->getMock();

    $mockHelper->method('getAll')
      ->willReturn($this->recordingGetAll());

    self::$freepbx->recordings = $mockHelper;

    $response = $this->request("query{
        fetchAllRecordings{
          status message recordings{
            id
            name
            description
            fcode
            fcode_pass
            languages
            playback
          }
       }
    }");
        
     $json = (string)$response->getBody();
     $this->assertEquals('{"data":{"fetchAllRecordings":{"status":true,"message":"List of system recordings","recordings":[{"id":"1","name":"rec1","description":"rec","fcode":"0","fcode_pass":"","languages":["en"],"playback":["1-yes-2-no","Randulo-allison","T-to-disable-ancmnt"]},{"id":"3","name":"rec2","description":"rec2","fcode":"0","fcode_pass":"","languages":["en"],"playback":["1-yes-2-no","SIP_Test_Failure","custom\/AirtelNewHiphopRingtone2066788122"]}]}}}',$json);
        
     $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * test_updateRecording_when_all_good_should_return_true
   */
  public function test_updateRecording_when_all_good_should_return_true()
  {
    $mockHelper = $this->getMockBuilder(\FreePBX\modules\recordings\Recordings::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('fileStatus','updateRecording','getAll','convertFiles'))
    ->getMock();

    $mockHelper->method('fileStatus')
      ->willReturn($this->fileReturn());
  
    $mockHelper->method('updateRecording')
      ->willReturn(true);

    $mockHelper->method('getAll')
      ->willReturn($this->recordingGetAll());

    $mockHelper->method('convertFiles')
    ->willReturn(array("status" => true, "name" => "1-yes-2-no"));

    self::$freepbx->recordings = $mockHelper;

    $response = $this->request("mutation {
                updateRecording(input:{ 
                    id: 3
                    name: \"rec2\"
                    description: \"rec2\"
                    fcode: 0
                    fcode_pass: \"\"
                    language: \"en\"
                    playback: [
                      \"1-yes-2-no\"
                    ]
                    codecs: [\"wav\"]
                })
                {
                  status
                  message
                  id
                }
              }");
        
     $json = (string)$response->getBody();
     $this->assertEquals('{"data":{"updateRecording":{"status":true,"message":"Recording updated succefully","id":"3"}}}',$json);
        
     $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * test_deleteRecording_should_return_true
   */
  public function test_deleteRecording_should_return_true()
  {
    $mockHelper = $this->getMockBuilder(\FreePBX\modules\recordings\Recordings::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('delRecording'))
    ->getMock();

    $mockHelper->method('delRecording')
      ->willReturn('');
      
    self::$freepbx->recordings = $mockHelper;

    $response = $this->request("mutation {
                              deleteRecording(input: {
                              id: \"2\"
                            }) {
                                  status
                                  message
                              }
                          }");
        
      $json = (string)$response->getBody();
      $this->assertEquals('{"data":{"deleteRecording":{"status":true,"message":"Recording deleted succefully"}}}',$json);
        
      $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * test_fetch_uploaded_recording_files_should_return_list_of_filenames
   */
  public function test_fetch_uploaded_recording_files_should_return_list_of_filenames()
  {
    $mockHelper = $this->getMockBuilder(\FreePBX\modules\recordings\Recordings::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('getSystemRecordings'))
    ->getMock();

    $mockHelper->method('getSystemRecordings')
      ->willReturn($this->systemRecordings());

    self::$freepbx->recordings = $mockHelper;

    $response = $this->request("query{
              fetchRecordingFiles(search: \"1-for-am-2-\"){
                status message recodingFiles
            }
          }");

      $json = (string)$response->getBody();
      $this->assertEquals('{"data":{"fetchRecordingFiles":{"status":true,"message":"List of system recording files","recodingFiles":["1-for-am-2-for-pm"]}}}',$json);
      $this->assertEquals(200, $response->getStatusCode());
  }

}