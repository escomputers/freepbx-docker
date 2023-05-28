<?php 

namespace FreepPBX\framework\utests;

require_once('../api/utests/ApiBaseTestCase.php');

use FreePBX\modules\framework;
use Exception;
use FreePBX\modules\Api\utests\ApiBaseTestCase;

/**
 * SystemAdminGqlApiTest
 */
class SystemAdminGqlApiTest extends ApiBaseTestCase {
    protected static $sysadmin;
        
    /**
     * setUpBeforeClass
     *
     * @return void
     */
    public static function setUpBeforeClass() {
      parent::setUpBeforeClass();
    }
        
    /**
     * tearDownAfterClass
     *
     * @return void
     */
    public static function tearDownAfterClass() 
    {
      parent::tearDownAfterClass();
    }
      
    /**
     * testaddInitialSetupRequiredParmaterNotSentShouldReturnfalse
     *
     * @return void
     */
    public function testaddInitialSetupRequiredParmaterNotSentShouldReturnfalse(){
      $username = 'test';
      $password = 'test';

      $response = $this->request("mutation {
        addInitialSetup(input: { 
          username: \"{$username}\" 
          password: \"{$password}\" }) 
          { status message }
        }
      ");

		$json = (string)$response->getBody();

      $this->assertEquals('{"errors":[{"message":"Field updateAdminAuthInput.notificationEmail of required type String! was not provided.","status":false}]}', $json);
      
      $this->assertEquals(400, $response->getStatusCode());
    }
    
    /**
     * testaddInitialSetupWhenDuplicateEntriesShouldReturnfalse
     *
     * @return void
     */
    public function testaddInitialSetupWhenDuplicateEntriesShouldReturnfalse(){
      $db = self::$freepbx->Database();
      $sql = $db->prepare("DELETE FROM `ampusers` where username like ?");
		  $sql->execute(array("%test%"));
      $pass = rand();

      $sql = $db->prepare("INSERT INTO `ampusers` (`username`, `password_sha1`, `sections`) VALUES ( ?, ?, '*')");
	  	$sql->execute(array("test", $pass));

      $response = $this->request("mutation {
        addInitialSetup(input: { 
         username: \"test\" 
         password: \"{$pass}\" 
         notificationEmail: \"test@gmail.com\"
         systemIdentifier: \"VOIP Server\"
         autoModuleUpdate: \"enabled\"
         autoModuleSecurityUpdate: \"enabled\"
         securityEmailUnsignedModules: \"test@test.com\"
         updateDay: \"monday\"
         updatePeriod: \"0to4\"

         }) { status message }
        }
      ");

		$json = (string)$response->getBody();

    $this->assertEquals('{"data":{"addInitialSetup":{"status":true,"message":"Admin user already exists, updating other parameters"}}}', $json);
    $this->assertEquals(200, $response->getStatusCode());
    }
   
   /**
    * testaddInitialSetAllGoodShouldReturnfalse
    *
    * @return void
    */
   public function testaddInitialSetAllGoodShouldReturnfalse(){
      $db = self::$freepbx->Database();
      $sql = $db->prepare("DELETE FROM `ampusers` where username like ?");
      $sql->execute(array("%test%"));
      $pass = rand();

      $response = $this->request("mutation {
        addInitialSetup(input: { 
         username: \"test\" 
         password: \"{$pass}\"
         notificationEmail: \"test@gmail.com\"
         systemIdentifier: \"VOIP Server\"
         autoModuleUpdate: \"enabled\"
         autoModuleSecurityUpdate: \"enabled\"
         securityEmailUnsignedModules: \"test@test.com\"
         updateDay: \"monday\"
         updatePeriod: \"0to4\"

         }) { status message }
        }
      ");

		  $json = (string)$response->getBody();
      $this->assertEquals('{"data":{"addInitialSetup":{"status":true,"message":"Initial Setup is completed"}}}', $json);
      $this->assertEquals(200, $response->getStatusCode());

      $sth = $db->prepare("DELETE FROM `ampusers` where username like ?");
		  $sql->execute(array("%test%"));
   }
  
  /**
   * test_fetchDBStatus_when_true_should_return_true
   *
   * @return void
   */
  public function test_fetchDBStatus_when_true_should_return_true(){
     $default = $this->getMockBuilder(\FreePBX\modules\framework\Monitoring::class)
     ->disableOriginalConstructor()
			->setMethods(array('dbStatus'))
      ->getMock();  

    $default->method('dbStatus')->willReturn(true);
    self::$freepbx->Framework->setMonitoringObj($default);

    $response = $this->request("query{
      fetchDBStatus{
      status
      message
      dbStatus
    }}");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"data":{"fetchDBStatus":{"status":true,"message":"Database Status","dbStatus":"Connected"}}}',$json);
    $this->assertEquals(200, $response->getStatusCode());
   }
   
   /**
    * test_fetchDBStatus_when_DB_false_should_return_false
    *
    * @return void
    */
   public function test_fetchDBStatus_when_DB_false_should_return_false(){
     $default = $this->getMockBuilder(\FreePBX\modules\framework\Monitoring::class)
     ->disableOriginalConstructor()
			->setMethods(array('dbStatus'))
      ->getMock();  

    $default->method('dbStatus')->willReturn(false);
    self::$freepbx->Framework->setMonitoringObj($default);

    $response = $this->request("query{
      fetchDBStatus{
      status
      message
      dbStatus
    }}");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"data":{"fetchDBStatus":{"status":true,"message":"Database Status","dbStatus":"Not Connected"}}}',$json);
    $this->assertEquals(200, $response->getStatusCode());
   }
  
  /**
   * test_fetchSetupWizard_when_all_good_should_return_true
   *
   * @return void
   */
  public function test_fetchSetupWizard_when_all_good_should_return_true(){
     $default = $this->getMockBuilder(\FreePBX\modules\framework\Monitoring::class)
     ->disableOriginalConstructor()
			->setMethods(array('setupWizardDetails'))
      ->getMock();  

    $default->method('setupWizardDetails')->willReturn(array('0'=>array('val'=>'{"framework":"framework"}'
  )));
    self::$freepbx->Framework->setMonitoringObj($default);

    $response = $this->request("query{
      fetchSetupWizard{
        status
        message
        autoupdates{
          modules
        }
      }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"data":{"fetchSetupWizard":{"status":true,"message":"List up moduels setup wizard is run for","autoupdates":[{"modules":"{\"framework\":\"framework\"}"}]}}}',$json);
    $this->assertEquals(200, $response->getStatusCode());
  }
   
   /**
    * test_fetchGUIMode_when_all_good_should_return_true
    *
    * @return void
    */
   public function test_fetchGUIMode_when_advanced_should_return_advanced(){
     $default = $this->getMockBuilder(\FreePBX\modules\framework\Monitoring::class)
     ->disableOriginalConstructor()
			->setMethods(array('GUIMode'))
      ->getMock();  

    $default->method('GUIMode')->willReturn('advanced');
    self::$freepbx->Framework->setMonitoringObj($default);

    $response = $this->request("query{
      fetchGUIMode{
      status
      message
      guiMode
    }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"data":{"fetchGUIMode":{"status":true,"message":"GUI Mode details","guiMode":"advanced"}}}',$json);
    $this->assertEquals(200, $response->getStatusCode());
   }
   
   /**
    * test_fetchGUIMode_when_return_false_should_return_false
    *
    * @return void
    */
   public function test_fetchGUIMode_when_basic_false_should_return_basic(){
     $default = $this->getMockBuilder(\FreePBX\modules\framework\Monitoring::class)
     ->disableOriginalConstructor()
			->setMethods(array('GUIMode'))
      ->getMock();  

    $default->method('GUIMode')->willReturn('basic');
    self::$freepbx->Framework->setMonitoringObj($default);

    $response = $this->request("query{
      fetchGUIMode{
      status
      message
      guiMode
    }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"data":{"fetchGUIMode":{"status":true,"message":"GUI Mode details","guiMode":"basic"}}}',$json);
    $this->assertEquals(200, $response->getStatusCode());
   }
  
  /**
   * test_fetchAutomaticUpdate_when_all_good_should_return_true
   *
   * @return void
   */
  public function test_fetchAutomaticUpdate_when_all_good_should_return_true(){
     $default = $this->getMockBuilder(\FreePBX\modules\framework\Monitoring::class)
     ->disableOriginalConstructor()
			->setMethods(array('autoUpdateDetails'))
      ->getMock();  

    $default->method('autoUpdateDetails')->willReturn(array('auto_system_updates'=>'disabled','auto_module_updates'=>'enabled','auto_module_security_updates'=>'enabled'));
    self::$freepbx->Framework->setMonitoringObj($default);

    $response = $this->request("query{
      fetchAutomaticUpdate{
        status
        message
        systemUpdates
        moduleUpdates
        moduleSecurityUpdates
      }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"data":{"fetchAutomaticUpdate":{"status":true,"message":"Automatic update status","systemUpdates":"disabled","moduleUpdates":"enabled","moduleSecurityUpdates":"enabled"}}}',$json);
    $this->assertEquals(200, $response->getStatusCode());
  }
  
  /**
   * test_fetchAutomaticUpdate_when_return_empty_should_return_false
   *
   * @return void
   */
  public function test_fetchAutomaticUpdate_when_return_empty_should_return_false(){
     $default = $this->getMockBuilder(\FreePBX\modules\framework\Monitoring::class)
     ->disableOriginalConstructor()
			->setMethods(array('autoUpdateDetails'))
      ->getMock();  

    $default->method('autoUpdateDetails')->willReturn(array());
    self::$freepbx->Framework->setMonitoringObj($default);

    $response = $this->request("query{
      fetchAutomaticUpdate{
        status
        message
        systemUpdates
        moduleUpdates
        moduleSecurityUpdates
      }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"errors":[{"message":"Sorry, Could not find automatic update status","status":false}]}',$json);
    $this->assertEquals(400, $response->getStatusCode());
  }
  
  /**
   * test_fetchAsteriskDetails_when__true_should_return_true
   *
   * @return void
   */
  public function test_fetchAsteriskDetails_when__true_should_return_true(){
     $default = $this->getMockBuilder(\FreePBX\modules\framework\Monitoring::class)
     ->disableOriginalConstructor()
			->setMethods(array('asteriskInfo','asteriskRunning','astmanInfo'))
      ->getMock();  

    $default->method('asteriskInfo')->willReturn(array('version'=>'16.0'));
    $default->method('asteriskRunning')->willReturn(true);
    $default->method('astmanInfo')->willReturn(true);
    
    self::$freepbx->Framework->setMonitoringObj($default);

    $response = $this->request("query{
      fetchAsteriskDetails{
      status
      message
      asteriskStatus
      asteriskVersion
      amiStatus
      }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"data":{"fetchAsteriskDetails":{"status":true,"message":"Asterisk Details","asteriskStatus":"Running","asteriskVersion":"16.0","amiStatus":"Connected"}}}',$json);
    $this->assertEquals(200, $response->getStatusCode());
  }
  
  /**
   * test_fetchAsteriskDetails_when__astriskNotrunning_should_return_true_with_astrisk_notrunning
   *
   * @return void
   */
  public function test_fetchAsteriskDetails_when__astriskNotrunning_should_return_true_with_astrisk_notrunning(){
     $default = $this->getMockBuilder(\FreePBX\modules\framework\Monitoring::class)
     ->disableOriginalConstructor()
			->setMethods(array('asteriskInfo','asteriskRunning','astmanInfo'))
      ->getMock();  

    $default->method('asteriskInfo')->willReturn(array('version'=>'16.0'));
    $default->method('asteriskRunning')->willReturn(false);
    $default->method('astmanInfo')->willReturn(true);
    
    self::$freepbx->Framework->setMonitoringObj($default);

    $response = $this->request("query{
      fetchAsteriskDetails{
      status
      message
      asteriskStatus
      asteriskVersion
      amiStatus
      }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"data":{"fetchAsteriskDetails":{"status":true,"message":"Asterisk Details","asteriskStatus":"Not running","asteriskVersion":"16.0","amiStatus":"Connected"}}}',$json);
    $this->assertEquals(200, $response->getStatusCode());
  }
   
   /**
    * test_fetchAsteriskDetails_when__astriskNotrunning_and_astman_not_connected_should_return_true_with_astrisk_notrunning_and_astman_not_connected
    *
    * @return void
    */
   public function test_fetchAsteriskDetails_when__astriskNotrunning_and_astman_not_connected_should_return_true_with_astrisk_notrunning_and_astman_not_connected(){
     $default = $this->getMockBuilder(\FreePBX\modules\framework\Monitoring::class)
     ->disableOriginalConstructor()
			->setMethods(array('asteriskInfo','asteriskRunning','astmanInfo'))
      ->getMock();  

    $default->method('asteriskInfo')->willReturn(array('version'=>'16.0'));
    $default->method('asteriskRunning')->willReturn(false);
    $default->method('astmanInfo')->willReturn(false);
    
    self::$freepbx->Framework->setMonitoringObj($default);

    $response = $this->request("query{
      fetchAsteriskDetails{
      status
      message
      asteriskStatus
      asteriskVersion
      amiStatus
      }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"data":{"fetchAsteriskDetails":{"status":true,"message":"Asterisk Details","asteriskStatus":"Not running","asteriskVersion":"16.0","amiStatus":"Not Connected"}}}',$json);
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * test_switchAstriskVersion_should_return_transaction_id_when_executed_correctly
   *
   * @return void
   */
  public function test_switchAstriskVersion_should_return_transaction_id_when_executed_correctly(){

    $mockHelper = $this->getMockBuilder(Freepbx\framework\amp_conf\htdocs\admin\libraries\BMO\Hooks::class)
      ->disableOriginalConstructor()
      ->setMethods(array('runModuleSystemHook'))
      ->getMock();

    $mockHelper->method('runModuleSystemHook')
    ->willReturn(true);

    self::$freepbx->sysadmin()->setRunHook($mockHelper);

    $response = $this->request(
      "mutation {
          switchAstriskVersion(input: {
                asteriskVersion: 18
            }) {
              status message transaction_id
          }
      }"
    );

    $json = (string)$response->getBody();
    $txnId = json_decode($json)->data->switchAstriskVersion->transaction_id;
    $this->assertNotEmpty($txnId);
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * test_switchAstriskVersion_should_return_error_when_invalid_argument_is_passed
   *
   * @return void
   */
  public function test_switchAstriskVersion_should_return_error_when_invalid_argument_is_passed(){

    $mockHelper = $this->getMockBuilder(Freepbx\framework\amp_conf\htdocs\admin\libraries\BMO\Hooks::class)
      ->disableOriginalConstructor()
      ->setMethods(array('runModuleSystemHook'))
      ->getMock();

    $mockHelper->method('runModuleSystemHook')
    ->willReturn(true);

    self::$freepbx->sysadmin()->setRunHook($mockHelper);

    $response = $this->request(
      "mutation {
          switchAstriskVersion(input: {
                invalid: 123 
            }) {
              status message transaction_id
          }
      }"
    );

    $json = (string)$response->getBody();
    $this->assertEquals('{"errors":[{"message":"Field \"invalid\" is not defined by type switchAstriskVersionInput.","status":false}]}', $json);
    $this->assertEquals(400, $response->getStatusCode());
  }
}