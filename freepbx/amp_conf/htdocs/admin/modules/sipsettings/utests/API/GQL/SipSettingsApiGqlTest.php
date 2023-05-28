<?php 

namespace FreepPBX\sipsettings\utests;

require_once('../api/utests/ApiBaseTestCase.php');

use FreePBX\modules\sipsettings;
use Exception;
use FreePBX\modules\Api\utests\ApiBaseTestCase;


/**
 * SipSettingsGqlApiTest
 */
class SipSettingsGqlApiTest extends ApiBaseTestCase {
   protected static $sipSettings;
        
   /**
   * setUpBeforeClass
   *
   * @return void
   */
   public static function setUpBeforeClass() {
      parent::setUpBeforeClass();
      self::$sipSettings = self::$freepbx->SipSettings;
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
    * test_fetchSipNatNetworkSettings_Should_return_sipNatsettings
    *
    * @return void
    */
   public function test_fetchSipNatNetworkSettings_should_return_sipNatsettings(){

   $mockobj = $this->getMockBuilder('FreePBX\Modules\Sipsettings\NatGet')
		->setMethods(array('getVisibleIP','getRoutes','getConfigurations'))
      ->getMock();

	$mockobj->method('getVisibleIP')
		->willReturn(array('status' => true, 'address' => "88.88.80.80"));
   
   $mockobj->method('getRoutes')
		->willReturn(array(array("100.100.100.100","21")));

   $mockobj->method('getConfigurations')
		->willReturn(array(array("net" => "101.101.101.101","mask" => "21"),array("net" => "10.120.102.101","mask" => "22")));
      
   self::$freepbx->sipsettings->setNatObj($mockobj); 

   $response = $this->request("{
      fetchSipNatNetworkSettings{
         status 
         message
         externIP
         localIP{
            net
            mask
         }
         routes{
            net
            mask
         }
      }}");

   $json = (string)$response->getBody();
   $this->assertEquals('{"data":{"fetchSipNatNetworkSettings":{"status":true,"message":"List of External and Local IPs","externIP":"88.88.80.80","localIP":[{"net":"101.101.101.101","mask":"21"},{"net":"10.120.102.101","mask":"22"}],"routes":[{"net":"100.100.100.100","mask":"21"}]}}}',$json);
      
   $this->assertEquals(200, $response->getStatusCode());
   }
      
   /**
    * test_fetchSipNatNetworkSettings_Should_return_false_when_external_ip_return_false
    *
    * @return void
    */
   public function test_fetchSipNatNetworkSettings_Should_return_true_when_external_ip_return_false_with_message(){

   $mockobj = $this->getMockBuilder('FreePBX\Modules\Sipsettings\NatGet')
		->setMethods(array('getVisibleIP','getRoutes','getConfigurations'))
      ->getMock();

	$mockobj->method('getVisibleIP')
		->willReturn(array('status' => false, 'message' => "Something went wrong"));
   
   $mockobj->method('getRoutes')
		->willReturn(array(array("100.100.100.100","21")));

   $mockobj->method('getConfigurations')
		->willReturn(array(array("net" => "101.101.101.101","mask" => "21"),array("net" => "10.120.102.101","mask" => "22")));
      
   self::$freepbx->sipsettings->setNatObj($mockobj); 

   $response = $this->request("{
      fetchSipNatNetworkSettings{
         status 
         message
         externIP
         localIP{
            net
            mask
         }
         routes{
            net
            mask
         }
      }}");

   $json = (string)$response->getBody();
   $this->assertEquals('{"data":{"fetchSipNatNetworkSettings":{"status":true,"message":"Something went wrong","externIP":"false","localIP":[{"net":"101.101.101.101","mask":"21"},{"net":"10.120.102.101","mask":"22"}],"routes":[{"net":"100.100.100.100","mask":"21"}]}}}',$json);
   $this->assertEquals(200, $response->getStatusCode());
}
   
   /**
    * test_addSipNatLocalIp_Should_addSipNat_and_should_return_true
    *
    * @return void
    */
   public function test_addSipNatLocalIp_Should_addSipNat_and_should_return_true(){

      $mockobj = $this->getMockBuilder('FreePBX\Modules\Sipsettings\NatGet')
         ->setMethods(array('setConfigurations'))
         ->getMock();

      $mockobj->method('setConfigurations')
         ->willReturn(true);
         
      self::$freepbx->sipsettings->setNatObj($mockobj); 

      $response = $this->request("mutation {
         addSipNatLocalIp(input:{ net: \"88.88.88.8\", mask : \"22\" }){
            status
            message
         }}");

   $json = (string)$response->getBody();
   $this->assertEquals('{"data":{"addSipNatLocalIp":{"status":true,"message":"Local IP has been added successfully"}}}',$json);
      
   $this->assertEquals(200, $response->getStatusCode());
   }
   
   /**
    * test_updateSipNatExternalIp_Should_addSipNat_and_should_return_true
    *
    * @return void
    */
   public function test_updateSipNatExternalIp_Should_addSipNat_and_should_return_true(){

      $mockobj = $this->getMockBuilder('FreePBX\Modules\Sipsettings\NatGet')
         ->setMethods(array('setConfigurations'))
         ->getMock();

      $mockobj->method('setConfigurations')
         ->willReturn(true);
         
      self::$freepbx->sipsettings->setNatObj($mockobj); 

      $response = $this->request("mutation {
         updateSipNatExternalIp(input:{ net: \"88.88.8.88\"}){
            status
            message
         }}");

      $json = (string)$response->getBody();
      $this->assertEquals('{"data":{"updateSipNatExternalIp":{"status":true,"message":"External IP has been updated successfully"}}}',$json);
         
      $this->assertEquals(200, $response->getStatusCode());
   }
   
   /**
    * test_updateSipNatExternalIp_should_return_fasle_when_paramater_not_sent
    *
    * @return void
    */
   public function test_updateSipNatExternalIp_should_return_fasle_when_paramater_not_sent(){

      $mockobj = $this->getMockBuilder('FreePBX\Modules\Sipsettings\NatGet')
         ->setMethods(array('setConfigurations'))
         ->getMock();

      $mockobj->method('setConfigurations')
         ->willReturn(true);
         
      self::$freepbx->sipsettings->setNatObj($mockobj); 

      $response = $this->request("mutation {
         updateSipNatExternalIp(input:{ }){
            status
            message
         }}");

      $json = (string)$response->getBody();
      $this->assertEquals('{"errors":[{"message":"Field updateSipNatExternalIpInput.net of required type String! was not provided.","status":false}]}',$json);
         
      $this->assertEquals(200, $response->getStatusCode());
   }
   
   /**
    * test_fetchWSSettings_should_return_values
    *
    * @return void
    */
   public function test_fetchWSSettings_should_return_values(){

      $mockobj = $this->getMockBuilder('FreePBX\Modules\Sipsettings')
         ->setMethods(array('getConfig'))
         ->getMock();

      $mockobj->method('getConfig')
         ->with('binds')
         ->willReturn([
            "ws" => [
               "0.0.0.0" => "on",
               "192.168.1.9" => "off"
            ],
            "wss" => [
               "0.0.0.0" => "off",
               "192.168.1.9" => "on"
            ]
         ]);
         
      self::$freepbx->sipsettings = $mockobj; 

      $response = $this->request("query {
         fetchWSSettings {
            status
            message
            ws {
               interface
               state
            }
            wss {
               interface
               state
            }
         }
      }");

      $json = (string)$response->getBody();
      $this->assertEquals('{"data":{"fetchWSSettings":{"status":true,"message":"Web Socket Settings","ws":[{"interface":"0.0.0.0","state":"on"},{"interface":"192.168.1.9","state":"off"}],"wss":[{"interface":"0.0.0.0","state":"off"},{"interface":"192.168.1.9","state":"on"}]}}}',$json);
         
      $this->assertEquals(200, $response->getStatusCode());
   }
   
   /**
    * test_updateWSSettings_with_proper_values_should_return_success
    *
    * @return void
    */
   public function test_updateWSSettings_with_proper_values_should_return_success(){

      $mockobj = $this->getMockBuilder('FreePBX\Modules\Sipsettings')
         ->setMethods(array('getConfig', 'setConfig'))
         ->getMock();

      $mockobj->method('getConfig')
         ->with('binds')
         ->willReturn([
            "ws" => [
               "0.0.0.0" => "on",
               "192.168.1.9" => "off"
            ],
            "wss" => [
               "0.0.0.0" => "off",
               "192.168.1.9" => "on"
            ]
         ]);

      $mockobj->method('setConfig')
         ->willReturn(null);
         
      self::$freepbx->sipsettings = $mockobj; 

      $response = $this->request("mutation {
         updateWSSettings(input: {
            ws: \"{'0.0.0.0': 'off','192.168.1.9':'on'}\"
            wss: \"{'0.0.0.0': 'on','192.168.1.9':'off'}\"
         }) {
            status
            message
         }
       }");

      $json = (string)$response->getBody();
      $this->assertEquals('{"data":{"updateWSSettings":{"status":true,"message":"Web Socket settings updated successfully"}}}',$json);
         
      $this->assertEquals(200, $response->getStatusCode());
   }
   
   /**
    * test_updateWSSettings_with_invalid_values_should_return_error
    *
    * @return void
    */
   public function test_updateWSSettings_with_invalid_values_should_return_error(){

      $mockobj = $this->getMockBuilder('FreePBX\Modules\Sipsettings')
         ->setMethods(array('getConfig', 'setConfig'))
         ->getMock();

      $mockobj->method('getConfig')
         ->with('binds')
         ->willReturn([
            "ws" => [
               "0.0.0.0" => "on",
               "192.168.1.9" => "off"
            ],
            "wss" => [
               "0.0.0.0" => "off",
               "192.168.1.9" => "on"
            ]
         ]);
         
      self::$freepbx->sipsettings = $mockobj; 

      $response = $this->request("mutation {
         updateWSSettings(input: {
            ws: \"{'0.0.0.0': 'on','192.168.1.9':'on'}\"
            wss: \"{'0.0.0.0': 'on','192.168.1.9':'off'}\"
         }) {
            status
            message
         }
       }");

      $json = (string)$response->getBody();
      $this->assertEquals('{"errors":[{"message":"Other ws settings can not be enabled along with settings for \'All\' (0.0.0.0).\n","status":false}]}',$json);
         
      $this->assertEquals(400, $response->getStatusCode());
   }
   
   /**
    * test_updateWSSettings_with_invalid_ips_should_return_error
    *
    * @return void
    */
   public function test_updateWSSettings_with_invalid_ips_should_return_error(){

      $mockobj = $this->getMockBuilder('FreePBX\Modules\Sipsettings')
         ->setMethods(array('getConfig', 'setConfig'))
         ->getMock();

      $mockobj->method('getConfig')
         ->with('binds')
         ->willReturn([
            "ws" => [
               "0.0.0.0" => "on",
               "192.168.1.9" => "off"
            ],
            "wss" => [
               "0.0.0.0" => "off",
               "192.168.1.9" => "on"
            ]
         ]);
         
      self::$freepbx->sipsettings = $mockobj; 

      $response = $this->request("mutation {
         updateWSSettings(input: {
            ws: \"{'0.0.0.0': 'off','192.168.1.9':'on'}\"
            wss: \"{'0.0.0.0': 'on','192.168.1.98':'off'}\"
         }) {
            status
            message
         }
       }");

      $json = (string)$response->getBody();
      $this->assertEquals('{"errors":[{"message":"Invalid IP value \'192.168.1.98\'\n","status":false}]}',$json);
         
      $this->assertEquals(400, $response->getStatusCode());
   }
}