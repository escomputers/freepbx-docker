<?php 

namespace FreepPBX\pm2\utests;

require_once('../api/utests/ApiBaseTestCase.php');

use FreePBX\modules\pm2;
use Exception;
use FreePBX\modules\Api\utests\ApiBaseTestCase;

/**
 * Pm2GqlApiTest
 */
class Pm2GqlApiTest extends ApiBaseTestCase {
    protected static $pm2;
        
    /**
     * setUpBeforeClass
     *
     * @return void
     */
    public static function setUpBeforeClass() {
      parent::setUpBeforeClass();
      self::$pm2 = self::$freepbx->pm2;
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
    * test_fetchPm2AppStatus_all_good_should_return_true
    *
    * @return void
    */
   public function test_fetchPm2AppStatus_all_good_should_return_true(){
      $mockpm2Apps = $this->getMockBuilder(\FreePBX\modules\pm2\Pm2Apps::class)
       ->disableOriginalConstructor()
       ->setMethods(array('getAppStatus'))
       ->getMock();

      $mockpm2Apps->method('getAppStatus')->willReturn(array(array('PID'=>12345,'name'=>'test1','status'=>'online','uptime'=>'14 days','memory'=>'11.43MB'),
                                                             array('PID'=>3456,'name'=>'test2' ,'status'=>'online','uptime'=>'10 days','memory'=>'1.4MB'))); 
      self::$freepbx->pm2->setPm2AppsObj($mockpm2Apps);  

      $response = $this->request("query{
         fetchPm2AppStatus{
            status
            message
            apps{
               name
               PID
               status
               memory
               uptime       
         } }}");
      
      $json = (string)$response->getBody();
      $this->assertEquals('{"data":{"fetchPm2AppStatus":{"status":true,"message":"Kindly follow the pm2 apps and their status","apps":[{"name":"test1","PID":"12345","status":"online","memory":"11.43MB","uptime":"14 days"},{"name":"test2","PID":"3456","status":"online","memory":"1.4MB","uptime":"10 days"}]}}}',$json);
      
      $this->assertEquals(200, $response->getStatusCode());
   }
   
   /**
    * test_fetchPm2AppStatus_when_wrong_param_sent_should_return_false
    *
    * @return void
    */
   public function test_fetchPm2AppStatus_when_wrong_param_sent_should_return_false(){
      $mockpm2Apps = $this->getMockBuilder(\FreePBX\modules\pm2\Pm2Apps::class)
       ->disableOriginalConstructor()
       ->setMethods(array('getAppStatus'))
       ->getMock();

      $mockpm2Apps->method('getAppStatus')->willReturn(array(array('PID'=>12345,'name'=>'test1','status'=>'online','uptime'=>'14 days','memory'=>'11.43MB'),
                                                             array('PID'=>3456,'name'=>'test2' ,'status'=>'online','uptime'=>'10 days','memory'=>'1.4MB'))); 
      self::$freepbx->pm2->setPm2AppsObj($mockpm2Apps);  

      $response = $this->request("query{
         fetchPm2AppStatus{
            status
            message
            apps{
               name
               PID
               status
               memory
               uptime 
               test      
         } }}");
      
      $json = (string)$response->getBody();
      $this->assertEquals('{"errors":[{"message":"Cannot query field \"test\" on type \"pm2\".","status":false}]}',$json);
      
      $this->assertEquals(400, $response->getStatusCode());
   }
   
   /**
    * test_fetchPm2AppStatus_no_apps_data_recevied_should_return_false
    *
    * @return void
    */
   public function test_fetchPm2AppStatus_no_apps_data_recevied_should_return_false(){
      $mockpm2Apps = $this->getMockBuilder(\FreePBX\modules\pm2\Pm2Apps::class)
       ->disableOriginalConstructor()
       ->setMethods(array('getAppStatus'))
       ->getMock();

      $mockpm2Apps->method('getAppStatus')->willReturn(array()); 
      self::$freepbx->pm2->setPm2AppsObj($mockpm2Apps);  

      $response = $this->request("query{
         fetchPm2AppStatus{
            status
            message
            apps{
               name
               PID
               status
               memory
               uptime 
         } }}");
      
      $json = (string)$response->getBody();
      $this->assertEquals('{"errors":[{"message":"Sorry unable to find the pm2apps status","status":false}]}',$json);
      
      $this->assertEquals(400, $response->getStatusCode());
   }

   public function test_fetchPm2AppStatus_when_any_process_empty_should_return_null(){
      $mockpm2Apps = $this->getMockBuilder(\FreePBX\modules\pm2\Pm2Apps::class)
       ->disableOriginalConstructor()
       ->setMethods(array('getAppStatus'))
       ->getMock();

      $mockpm2Apps->method('getAppStatus')->willReturn(array(array('name'=>'test1','status'=>'stopped','uptime'=>'14 days','memory'=>''),
                                                             array('PID'=>3456,'name'=>'test2' ,'status'=>'online','uptime'=>'10 days','memory'=>'1.4MB'))); 
      self::$freepbx->pm2->setPm2AppsObj($mockpm2Apps);  

      $response = $this->request("query{
         fetchPm2AppStatus{
            status
            message
            apps{
               name
               PID
               status
               memory
               uptime       
         } }}");
      
      $json = (string)$response->getBody();
      $this->assertEquals('{"data":{"fetchPm2AppStatus":{"status":true,"message":"Kindly follow the pm2 apps and their status","apps":[{"name":"test1","PID":"0","status":"stopped","memory":"","uptime":"14 days"},{"name":"test2","PID":"3456","status":"online","memory":"1.4MB","uptime":"10 days"}]}}}',$json);
      
      $this->assertEquals(200, $response->getStatusCode());
   }
}