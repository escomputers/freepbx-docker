<?php 

namespace FreepPBX\framework\utests;

require_once('../api/utests/ApiBaseTestCase.php');

use FreePBX\modules\framework;
use Exception;
use FreePBX\modules\Api\utests\ApiBaseTestCase;

class ModuleAdminGqlApiTest extends ApiBaseTestCase {
    protected static $sysadmin;
    
    public static function setUpBeforeClass() {
      parent::setUpBeforeClass();
    }
    
    public static function tearDownAfterClass() 
    {
      parent::tearDownAfterClass();
    }
  
    public function testModuleOperationswhenHookExcuteShoudReturnTrue()
    {
      $module = 'core';
      $action = 'install';

      $mockGqlHelper = $this->getMockBuilder(Freepbx\api\Api::class)
       ->disableOriginalConstructor()
       ->setMethods(array('initiateGqlAPIProcess'))
       ->getMock();

      $mockGqlHelper->method('initiateGqlAPIProcess')->willReturn(true);

     self::$freepbx->Api()->setObj($mockGqlHelper);  

      $response = $this->request("mutation {
        moduleOperations(input: { 
          module: \"{$module}\" 
          action: \"{$action}\" }) 
          { status message }
        }
      ");

		  $json = (string)$response->getBody();

      $this->assertEquals('{"data":{"moduleOperations":{"status":true,"message":"Action['.$action.'] on module['.$module.'] has been initiated. Please check the status using fetchApiStatus api with the returned transaction id"}}}', $json);
      
      $this->assertEquals(200, $response->getStatusCode());
    }

    public function testModuleOperationswhenActionParamNotSentWillReturnErrors()
    {
      $module = 'core';

      $mockGqlHelper = $this->getMockBuilder(Freepbx\api\Api::class)
       ->disableOriginalConstructor()
       ->setMethods(array('initiateGqlAPIProcess'))
       ->getMock();

      $mockGqlHelper->method('initiateGqlAPIProcess')->willReturn(true);

     self::$freepbx->Api()->setObj($mockGqlHelper);  

      $response = $this->request("mutation {
        moduleOperations(input: { 
          module: \"{$module}\"  }) 
          { status message }
        }
      ");

		  $json = (string)$response->getBody();

	  	$this->assertEquals('{"errors":[{"message":"Field moduleOperationsInput.action of required type String! was not provided.","status":false}]}', $json);
      
      $this->assertEquals(400, $response->getStatusCode());
    }

    public function testModuleOperationsWhenModuleParamNotSentWillReturnErrors()
    {
      $action = 'install';

      $mockGqlHelper = $this->getMockBuilder(Freepbx\api\Api::class)
       ->disableOriginalConstructor()
       ->setMethods(array('initiateGqlAPIProcess'))
       ->getMock();

      $mockGqlHelper->method('initiateGqlAPIProcess')->willReturn(true);

     self::$freepbx->Api()->setObj($mockGqlHelper);   

      $response = $this->request("mutation {
        moduleOperations(input: { 
          action: \"{$action}\" }) 
          { status message }
        }
      ");

		  $json = (string)$response->getBody();

      $this->assertEquals('{"errors":[{"message":"Field moduleOperationsInput.module of required type String! was not provided.","status":false}]}', $json);
      
      $this->assertEquals(400, $response->getStatusCode());
    }

    public function testInstallModuleShoudReturnTrue(){
      
      $module = 'xmpp';

      $mockGqlHelper = $this->getMockBuilder(Freepbx\api\Api::class)
       ->disableOriginalConstructor()
       ->setMethods(array('initiateGqlAPIProcess'))
       ->getMock();

      $mockGqlHelper->method('initiateGqlAPIProcess')->willReturn(true);

     self::$freepbx->Api()->setObj($mockGqlHelper);  

      $response = $this->request("mutation {
        installModule(input: { 
          module: \"{$module}\" }) 
          { status message }
        }
      ");

		  $json = (string)$response->getBody();

	  	$this->assertEquals('{"data":{"installModule":{"status":true,"message":"Action[install] on module['.$module.'] has been initiated. Please check the status using fetchApiStatus api with the returned transaction id"}}}', $json);
      
      $this->assertEquals(200, $response->getStatusCode());
    }

    public function testInstallModuleWithDownloadShoudReturnTrue(){
      
      $module = 'xmpp';

      $mockGqlHelper = $this->getMockBuilder(Freepbx\api\Api::class)
       ->disableOriginalConstructor()
       ->setMethods(array('initiateGqlAPIProcess'))
       ->getMock();

      $mockGqlHelper->method('initiateGqlAPIProcess')->willReturn(true);

     self::$freepbx->Api()->setObj($mockGqlHelper);  

      $response = $this->request("mutation {
        installModule(input: { 
          module: \"{$module}\"
          forceDownload:true}) 
          { status message }
        }
      ");

		  $json = (string)$response->getBody();

	  	$this->assertEquals('{"data":{"installModule":{"status":true,"message":"Action[downloadinstall] on module['.$module.'] has been initiated. Please check the status using fetchApiStatus api with the returned transaction id"}}}', $json);
      
      $this->assertEquals(200, $response->getStatusCode());
    }

    public function testInstallModuleWithDownloadSetFalseShoudReturnTrueWithOnlyInstall(){
      
      $module = 'xmpp';

      $mockGqlHelper = $this->getMockBuilder(Freepbx\api\Api::class)
       ->disableOriginalConstructor()
       ->setMethods(array('initiateGqlAPIProcess'))
       ->getMock();

      $mockGqlHelper->method('initiateGqlAPIProcess')->willReturn(true);

     self::$freepbx->Api()->setObj($mockGqlHelper);  

      $response = $this->request("mutation {
        installModule(input: { 
          module: \"{$module}\"
          forceDownload:false}) 
          { status message }
        }
      ");

		  $json = (string)$response->getBody();

	  	$this->assertEquals('{"data":{"installModule":{"status":true,"message":"Action[install] on module['.$module.'] has been initiated. Please check the status using fetchApiStatus api with the returned transaction id"}}}', $json);
      
      $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUninstallModuleShoudReturnTrue(){
      
      $module = 'xmpp';

      $mockGqlHelper = $this->getMockBuilder(Freepbx\api\Api::class)
       ->disableOriginalConstructor()
       ->setMethods(array('initiateGqlAPIProcess'))
       ->getMock();

      $mockGqlHelper->method('initiateGqlAPIProcess')->willReturn(true);

     self::$freepbx->Api()->setObj($mockGqlHelper);  

      $response = $this->request("mutation {
        uninstallModule(input: { 
          module: \"{$module}\" }) 
          { status message }
        }
      ");

		  $json = (string)$response->getBody();

	  	$this->assertEquals('{"data":{"uninstallModule":{"status":true,"message":"Action[uninstall] on module['.$module.'] has been initiated. Please check the status using fetchApiStatus api with the returned transaction id"}}}', $json);
    
      $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUninstallModuleWithRemoveSetToTrueShoudReturnTrue(){
      
      $module = 'xmpp';

      $mockGqlHelper = $this->getMockBuilder(Freepbx\api\Api::class)
       ->disableOriginalConstructor()
       ->setMethods(array('initiateGqlAPIProcess'))
       ->getMock();

      $mockGqlHelper->method('initiateGqlAPIProcess')->willReturn(true);

     self::$freepbx->Api()->setObj($mockGqlHelper);  

      $response = $this->request("mutation {
        uninstallModule(input: { 
          module: \"{$module}\"
          RemoveCompletely:true}) 
          { status message }
        }
      ");

		  $json = (string)$response->getBody();

	  	$this->assertEquals('{"data":{"uninstallModule":{"status":true,"message":"Action[remove] on module['.$module.'] has been initiated. Please check the status using fetchApiStatus api with the returned transaction id"}}}', $json);
   
      $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUninstallModuleWithRemoveSetToFalseShoudReturnTrue(){
      
      $module = 'xmpp';

      $mockGqlHelper = $this->getMockBuilder(Freepbx\api\Api::class)
       ->disableOriginalConstructor()
       ->setMethods(array('initiateGqlAPIProcess'))
       ->getMock();

      $mockGqlHelper->method('initiateGqlAPIProcess')->willReturn(true);

     self::$freepbx->Api()->setObj($mockGqlHelper);  

      $response = $this->request("mutation {
        uninstallModule(input: { 
          module: \"{$module}\"
          RemoveCompletely:false}) 
          { status message }
        }
      ");

		  $json = (string)$response->getBody();

	  	$this->assertEquals('{"data":{"uninstallModule":{"status":true,"message":"Action[uninstall] on module['.$module.'] has been initiated. Please check the status using fetchApiStatus api with the returned transaction id"}}}', $json);
   
      $this->assertEquals(200, $response->getStatusCode());
    }

    public function testEnableModuleShoudReturnTrue(){
      
      $module = 'xmpp';

      $mockGqlHelper = $this->getMockBuilder(Freepbx\api\Api::class)
       ->disableOriginalConstructor()
       ->setMethods(array('initiateGqlAPIProcess'))
       ->getMock();

      $mockGqlHelper->method('initiateGqlAPIProcess')->willReturn(true);

     self::$freepbx->Api()->setObj($mockGqlHelper);  

      $response = $this->request("mutation {
        enableModule(input: { 
          module: \"{$module}\" }) 
          { status message }
        }
      ");

		  $json = (string)$response->getBody();

	  	$this->assertEquals('{"data":{"enableModule":{"status":true,"message":"Action[enable] on module['.$module.'] has been initiated. Please check the status using fetchApiStatus api with the returned transaction id"}}}', $json);
    
      $this->assertEquals(200, $response->getStatusCode());
    }

     public function testDisableModuleShoudReturnTrue(){
      
      $module = 'xmpp';

      $mockGqlHelper = $this->getMockBuilder(Freepbx\api\Api::class)
       ->disableOriginalConstructor()
       ->setMethods(array('initiateGqlAPIProcess'))
       ->getMock();

      $mockGqlHelper->method('initiateGqlAPIProcess')->willReturn(true);

     self::$freepbx->Api()->setObj($mockGqlHelper);  

      $response = $this->request("mutation {
        disableModule(input: { 
          module: \"{$module}\" }) 
          { status message }
        }
      ");

		  $json = (string)$response->getBody();

	  	$this->assertEquals('{"data":{"disableModule":{"status":true,"message":"Action[disable] on module['.$module.'] has been initiated. Please check the status using fetchApiStatus api with the returned transaction id"}}}', $json);
   
      $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUgradeModuleShoudReturnTrue(){
      
      $module = 'xmpp';

      $mockGqlHelper = $this->getMockBuilder(Freepbx\api\Api::class)
       ->disableOriginalConstructor()
       ->setMethods(array('initiateGqlAPIProcess'))
       ->getMock();

      $mockGqlHelper->method('initiateGqlAPIProcess')->willReturn(true);

     self::$freepbx->Api()->setObj($mockGqlHelper);  

      $response = $this->request("mutation {
        upgradeModule(input: { 
          module: \"{$module}\" }) 
          { status message }
        }
      ");

		  $json = (string)$response->getBody();

	  	$this->assertEquals('{"data":{"upgradeModule":{"status":true,"message":"Action[upgrade] on module['.$module.'] has been initiated. Please check the status using fetchApiStatus api with the returned transaction id"}}}', $json);
   
      $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUgradeAllModulesShoudReturnTrue(){
  
      $mockGqlHelper = $this->getMockBuilder(Freepbx\api\Api::class)
       ->disableOriginalConstructor()
       ->setMethods(array('initiateGqlAPIProcess'))
       ->getMock();

      $mockGqlHelper->method('initiateGqlAPIProcess')->willReturn(true);

     self::$freepbx->Api()->setObj($mockGqlHelper);  

      $response = $this->request("mutation {
        upgradeAllModules(input: { }) 
          { status message }
        }
      ");

		  $json = (string)$response->getBody();

	  	$this->assertEquals('{"data":{"upgradeAllModules":{"status":true,"message":"Action[upgradeAll] on module[] has been initiated. Please check the status using fetchApiStatus api with the returned transaction id"}}}', $json);
    
      $this->assertEquals(200, $response->getStatusCode());
    }
    
    /**
     * testDoreoadShouldReturnTrue
     *
     * @return void
     */
    public function testDoreoadShouldReturnTrue(){
  
      $mockGqlHelper = $this->getMockBuilder(Freepbx\api\Api::class)
       ->disableOriginalConstructor()
       ->setMethods(array('doreload'))
       ->getMock();

      $mockGqlHelper->method('doreload')->willReturn(true);

     self::$freepbx->Api()->setObj($mockGqlHelper);  

      $response = $this->request("mutation {
        doreload(input: { }) 
          { status message }
        }
      ");

		  $json = (string)$response->getBody();

	  	$this->assertEquals('{"data":{"doreload":{"status":true,"message":"Doreload\/apply config has been initiated. Please check the status using fetchApiStatus api with the returned transaction id"}}}', $json);
    
      $this->assertEquals(200, $response->getStatusCode());
    }
        
    /**
     * testYumUpgradeAllTrueShoudReturnTrue
     *
     * @return void
     */
    public function testYumUpgradeWhenTrueShoudReturnTrue(){
      $mockHelper = $this->getMockBuilder(Freepbx\framework\amp_conf\htdocs\admin\libraries\BMO\Hooks::class)
       ->disableOriginalConstructor()
       ->setMethods(array('runModuleSystemHook'))
       ->getMock();

      $mockHelper->method('runModuleSystemHook')
      ->willReturn(true);

      self::$freepbx->sysadmin()->setRunHook($mockHelper);  

      $response = $this->request("mutation {
        updateSystemRPM(input: {}){
          status
          message   
        }
      }");

    $json = (string)$response->getBody();
  
    $this->assertEquals('{"data":{"updateSystemRPM":{"status":true,"message":"Yum Upgrade has been initiated. Kindly check the fetchApiStatus api with the transaction id."}}}',$json);
    $this->assertEquals(200, $response->getStatusCode());
  }
  
  /**
   * testYumUpgradeWhenFalseShoudReturnFasle
   *
   * @return void
   */
  public function testYumUpgradeWhenFalseShoudReturnFasle(){
    $mockHelper = $this->getMockBuilder(Freepbx\framework\amp_conf\htdocs\admin\libraries\BMO\Hooks::class)
       ->disableOriginalConstructor()
       ->setMethods(array('runModuleSystemHook'))
       ->getMock();

      $mockHelper->method('runModuleSystemHook')
      ->willReturn(false);

      $response = $this->request("mutation {
        updateSystemRPM(input: {}){
          status
          message   
        }
      }");

      self::$freepbx->sysadmin()->setRunHook($mockHelper);  

    $json = (string)$response->getBody();
  
    $this->assertEquals('{"errors":[{"message":"Failed to run yum Upgrade","status":false}]}',$json);
    $this->assertEquals(400, $response->getStatusCode());
  }

  /**
   * test_fwconsoleCommand_should_return_transaction_id_when_executed_correctly
   *
   * @return void
   */
  public function test_fwconsoleCommand_should_return_transaction_id_when_executed_correctly()
  {
    $mockHelper = $this->getMockBuilder(Freepbx\framework\amp_conf\htdocs\admin\libraries\BMO\Hooks::class)
      ->disableOriginalConstructor()
      ->setMethods(array('runModuleSystemHook'))
      ->getMock();

    $mockHelper->method('runModuleSystemHook')
    ->willReturn(true);

    self::$freepbx->sysadmin()->setRunHook($mockHelper);

    $response = $this->request(
      "mutation {
          fwconsoleCommand(input: {
                command: reload
            }) {
              status message transaction_id
          }
      }"
    );

    $json = (string)$response->getBody();

    $txnId = json_decode($json)->data->fwconsoleCommand->transaction_id;

    $this->assertNotEmpty($txnId);

    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * test_fwconsoleCommand_should_return_error_when_invalid_argument_is_passed
   *
   * @return void
   */
  public function test_fwconsoleCommand_should_return_error_when_invalid_argument_is_passed()
  {
    $mockHelper = $this->getMockBuilder(Freepbx\framework\amp_conf\htdocs\admin\libraries\BMO\Hooks::class)
      ->disableOriginalConstructor()
      ->setMethods(array('runModuleSystemHook'))
      ->getMock();

    $mockHelper->method('runModuleSystemHook')
    ->willReturn(true);

    self::$freepbx->sysadmin()->setRunHook($mockHelper);

    $response = $this->request(
      "mutation {
          fwconsoleCommand(input: {
                command: lorem
            }) {
              status message transaction_id
          }
      }"
    );

    $json = (string)$response->getBody();

    $this->assertEquals('{"errors":[{"message":"Expected type command, found lorem.","status":false}]}', $json);

    $this->assertEquals(400, $response->getStatusCode());
  }
   
   /**
    * test_fetchApiStatus_should_return_status_failed_and_failure_response
    *
    * @return void
    */
   public function test_fetchApiStatus_should_return_status_failed_and_failure_response(){
     $default = $this->getMockBuilder(\FreePBX\api\api::class)
     ->disableOriginalConstructor()
			->setMethods(array('getTransactionStatus'))
      ->getMock();  

    $default->method('getTransactionStatus')->willReturn(array('event_status'=>'Failed','failure_reason'=>'Failed to restart asterisk'));
    
    self::$freepbx->Api()->setObj($default);  

    $response = $this->request("query{
      fetchApiStatus( txnId: 97){
        status
        message
        failureReason
    }}");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"data":{"fetchApiStatus":{"status":true,"message":"Failed","failureReason":"Failed to restart asterisk"}}}',$json);
    $this->assertEquals(200, $response->getStatusCode());
  }
    
  /**
   * testUgradeAllModulesWhenBackupIsInProgressShouldReturnFalse
   *
   * @return void
   */
  public function testUgradeAllModulesWhenBackupIsInProgressShouldReturnFalse(){

    $default = $this->getMockBuilder(\FreePBX\framework\Framework::class)
      ->disableOriginalConstructor()
      ->setMethods(array('checkBackUpAndRestoreProgressStatus'))
      ->getMock(); 

    $default->method('checkBackUpAndRestoreProgressStatus')->willReturn(false);
     
    self::$freepbx->Framework = $default;  

    $response = $this->request("mutation {
      upgradeAllModules(input: { }) 
        { status message }
      }
    ");

    $json = (string)$response->getBody();

    $this->assertEquals('{"errors":[{"message":"Backup & Restore process is in progress. Please wait till the process is completed.","status":false}]}', $json);
  }
    
  /**
   * testYumUpgradeWhenWhenBackupIsInProgressShouldReturnFalse
   *
   * @return void
   */
  public function testYumUpgradeWhenWhenBackupIsInProgressShouldReturnFalse() {

    $default = $this->getMockBuilder(\FreePBX\framework\Framework::class)
      ->disableOriginalConstructor()
      ->setMethods(array('checkBackUpAndRestoreProgressStatus'))
      ->getMock(); 

    $default->method('checkBackUpAndRestoreProgressStatus')->willReturn(false);
     
    self::$freepbx->Framework = $default;  

    $response = $this->request("mutation {
      updateSystemRPM(input: {}){
        status
        message   
      }
    }");

    $json = (string)$response->getBody();
  
    $this->assertEquals('{"errors":[{"message":"Backup & Restore process is in progress. Please wait till the process is completed.","status":false}]}',$json);
  }
  
  /**
   * testFetchInstalledModulesShouldReturntrue
   *
   * @return void
   */
  public function testFetchInstalledModulesShouldReturntrue() {
  
    $default = $this->getMockBuilder(\FreePBX\framework\Framework::class)
          ->disableOriginalConstructor()
          ->setMethods(array('getInstalledModulesList'))
          ->getMock(); 

    $arr = array(
                  'result' => 0,
                  'output' => array
                      (
                         '{"status":true,"type":"message","data":"No repos specified, using: [extended,unsupported,standard,commercial] from last GUI settings"}',
                          '{"status":true,"type":"message","data":""}',
                         '{"status":true,"type":"message","data":[["accountcodepreserve","13.0.2.2","Enabled","GPLv2"]]}'
                      )

              );
              
    $default->method('getInstalledModulesList')->willReturn($arr);
     
    self::$freepbx->Framework = $default;  

    $response = $this->request("{
                          fetchInstalledModules{
                            status
                            message
                            modules{
                              name,
                              state,
                              version,
                              license
                            }
                          }
                        }");

    $json = (string)$response->getBody();

    $this->assertEquals('{"data":{"fetchInstalledModules":{"status":true,"message":"Installed modules list loaded successfully ","modules":[{"name":"accountcodepreserve","state":"Enabled","version":"13.0.2.2","license":"GPLv2"}]}}}',$json);
  }

  
  /**
   * testFetchInstalledModulesShouldReturnfalseWhenExcutingCommandFails
   *
   * @return void
   */
  public function testFetchInstalledModulesShouldReturnfalseWhenExcutingCommandFails() {
  
    $default = $this->getMockBuilder(\FreePBX\framework\Framework::class)
          ->disableOriginalConstructor()
          ->setMethods(array('getInstalledModulesList'))
          ->getMock(); 

    $arr = array(
                  'result' => 1,
                  'output' => []
              );
              
    $default->method('getInstalledModulesList')->willReturn($arr);
     
    self::$freepbx->Framework = $default;  

    $response = $this->request("{
                         fetchInstalledModules{
                            status
                            message
                            modules{
                              name,
                              state,
                              version,
                              license
                            }
                          }
                        }");

    $json = (string)$response->getBody();

    $this->assertEquals('{"errors":[{"message":"Failed to load installed modules list ","status":false}]}',$json);
  }

  /**
   * testFetchInstalledModulesWithInvalidQueryParamShouldReturnError
   *
   * @return void
   */
  public function testFetchInstalledModulesWithInvalidQueryParamShouldReturnError() {

    $response = $this->request("{
                          fetchInstalledModules{
                            status
                            message
                            modules{
                              name,
                              lorem
                            }
                          }
                        }");

    $json = (string)$response->getBody();

    $this->assertEquals('{"errors":[{"message":"Cannot query field \"lorem\" on type \"module\".","status":false}]}',$json);

  }
}
