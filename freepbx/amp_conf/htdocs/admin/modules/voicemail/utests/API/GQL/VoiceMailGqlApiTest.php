<?php 

namespace FreepPBX\voicemail\utests;

require_once('../api/utests/ApiBaseTestCase.php');

use FreePBX\modules\voicemail;
use Exception;
use FreePBX\modules\Api\utests\ApiBaseTestCase;

class VoiceMailGqlApiTest extends ApiBaseTestCase {
    protected static $voicemail;
    
    public static function setUpBeforeClass() {
      parent::setUpBeforeClass();
      self::$voicemail = self::$freepbx->Voicemail;
    }
    
    public static function tearDownAfterClass() {
      parent::tearDownAfterClass();
    }
  
   public function testVoiceMailQueryWhenExtensionDoesNotExistsShouldReturnFalse(){
     $invlalidExtensionId = 1800;
      $response = $this->request("query {
        fetchVoiceMail (extensionId : \"$invlalidExtensionId\" ){
          status message
        }
      }");
      
      $json = (string)$response->getBody();

      $this->assertEquals('{"errors":[{"message":"Sorry unable to fetch the status","status":false}]}',$json);

      //status 400 failure check
      $this->assertEquals(400, $response->getStatusCode());
   }

   public function testVoiceMailQueryWhenExtensionExistsShouldReturnTrue(){
      $input['extensionId'] = $extensionId = "18100";
      $input['vm'] = 'enabled';
      $input['vmpwd'] = '123456';
      
      //delete existing 
      self::$voicemail->delMailbox($extensionId);
      self::$voicemail->delUser($extensionId);

      //create new vm for the extension
      self::$voicemail->addMailbox($extensionId,$input);
      
      $response = $this->request("query {
        fetchVoiceMail (extensionId : \"$extensionId\" ){
         status
         message
         password
        }
      }");
      
      $json = (string)$response->getBody();

      $this->assertEquals('{"data":{"fetchVoiceMail":{"status":true,"message":"Voicemail data found successfully","password":"123456"}}}',$json);

      //status 200 success check
      $this->assertEquals(200, $response->getStatusCode());
   }

   public function testEnableMailWhenExtensionPassedWithoutPasswordShouldReturnFalse(){
      $extensionId = "18200";
      $password = '123456';
      
      //delete existing 
      self::$voicemail->delMailbox($extensionId);
      self::$voicemail->delUser($extensionId);
      
      $response = $this->request("mutation {
         enableVoiceMail(input: { extensionId: \"$extensionId\"}) {
            status message 
         }
      }");
      
      $json = (string)$response->getBody();

      $this->assertEquals('{"errors":[{"message":"Field enableVoiceMailInput.password of required type String! was not provided.","status":false}]}',$json);
      
      //status 400 failure check
      $this->assertEquals(400, $response->getStatusCode());
   }

   public function testEnableMailWhenExtensionPassedWithPasswordShouldTrue(){
      $input['extensionId'] = $extensionId = "18300";
      $password = '123456';

      $stubCore = $this->getMockBuilder('\FreePBX\modules\Core')
         ->disableOriginalConstructor()
         ->disableOriginalClone()
         ->setMethods(array('getDevice', 'getUser'))
         ->getMock();

      $stubCore->method('getDevice')
         ->with($extensionId)
         ->willReturn(true);
      $stubCore->method('getUser')
         ->with()
         ->willReturn(true);
      self::$freepbx->Core = $stubCore;

      //delete existing 
      $res = self::$voicemail->delMailbox($extensionId,false);
      $res = self::$voicemail->delUser($extensionId);

      $response = $this->request("mutation {
         enableVoiceMail(input: { extensionId: \"$extensionId\" password: \"$password\"}) {
            status message 
         }
      }");
      
      $json = (string)$response->getBody();

      $this->assertEquals('{"data":{"enableVoiceMail":{"status":true,"message":"Voicemail has been created successfully"}}}',$json);
      
      //status 200 success check
      $this->assertEquals(200, $response->getStatusCode());
   }

   public function testDiableMailWhenExtensionExistsShouldReturnTrue(){
      $input['extensionId'] = $extensionId = "18400";
      $input['vm'] = 'enabled';
      $input['vmpwd'] = '123456';
      
      // delete existing 
      self::$voicemail->delMailbox($extensionId);
      self::$voicemail->delUser($extensionId);

      self::$voicemail->addMailbox($extensionId,$input);

      $response = $this->request("mutation {
         disableVoiceMail(input: { extensionId: \"$extensionId\"}) {
            status message 
         }
      }");
      
      $json = (string)$response->getBody();

      $this->assertEquals('{"data":{"disableVoiceMail":{"status":true,"message":"Voicemail has been disabled"}}}',$json);
   
      //status 200 success check
      $this->assertEquals(200, $response->getStatusCode());
   }
   
   public function testDiableMailWhenExtensionDoesnotExistsShouldReturnFalse(){
      $extensionId = "18500clear";
      
      self::$voicemail->delMailbox($extensionId);
      self::$voicemail->delUser($extensionId);
   
      $response = $this->request("mutation {
         disableVoiceMail(input: { extensionId: \"$extensionId\"}) {
            status message 
         }
      }");
      
      $json = (string)$response->getBody();

      $this->assertEquals('{"errors":[{"message":"Sorry,voicemail does not  exists.","status":false}]}',$json);
      
      //status 400 failure check
      $this->assertEquals(400, $response->getStatusCode());
   }

    public function test_enableVoiceMailWhenPassingExtensionIdDoesNotExit_should_return_false() {
		$stubCore = $this->getMockBuilder('\FreePBX\modules\Core')
         ->disableOriginalConstructor()
		   ->disableOriginalClone()
		   ->setMethods(array('getDevice'))
		   ->getMock();

		$stubCore->method('getDevice')
			->with('1001')
			->willReturn(null);
		 self::$freepbx->Core = $stubCore;

      $response = $this->request("mutation{
         enableVoiceMail(input : {
            extensionId	: \"1001\"
            password: \"123456\"
           }){
           status message
         }
       }");
   
       $json = (string)$response->getBody();
       $this->assertEquals('{"errors":[{"message":"Extension does not exists.","status":false}]}',$json);
       $this->assertEquals(400, $response->getStatusCode());
     }
}