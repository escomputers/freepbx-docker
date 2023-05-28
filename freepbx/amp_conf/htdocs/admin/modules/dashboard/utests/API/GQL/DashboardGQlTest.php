<?php 
namespace FreepPBX\dashboard\utests;

require_once('../api/utests/ApiBaseTestCase.php');

use Exception;
use FreePBX\modules\Api\utests\ApiBaseTestCase;

class DashboardGQLTest extends ApiBaseTestCase {
  protected static $dashboard;
    
  public static function setUpBeforeClass() {
    parent::setUpBeforeClass();
    self::$dashboard = self::$freepbx->dashboard;
  }			
  
  public static function tearDownAfterClass() {
      parent::tearDownAfterClass();
  }

  public function testCheckdiskspace() {
    $response = $this->request("query {
      checkdiskspace { status message }
      }");
    $json = (string)$response->getBody();
    $this->assertEquals('{"data":{"checkdiskspace":{"status":true,"message":"Successfully found disks space details"}}}', $json);
    $this->assertEquals(200, $response->getStatusCode());
  }

  public function test_checkdiskspace_all_return_true() {
      $mockHelper = $this->getMockBuilder(\Dashboard::class)
      ->disableOriginalConstructor()
      ->setMethods(array('getdiskspace'))
      ->getMock();

      $mockHelper->method('getdiskspace')->willReturn(array(
        "/dev/mapper/SangomaVG-root" => array(
          "size" => "16G",
          "used" => "9.1G",
          "avail" => "6.2G",
          "usepct" => "60%",
          "mountpoint" => "/"
        ),"/dev/sda1" => array(
          "size" => "1.9G",
          "used" => "58M",
          "avail" => "1.8G",
          "usepct" => "4%",
          "mountpoint" => "/boot"
        )
      ));
      self::$freepbx->Dashboard = $mockHelper;
      $response = $this->request("query{
        checkdiskspace {status message}
      }");
      $json = (string)$response->getBody();
      $this->assertEquals('{"data":{"checkdiskspace":{"status":true,"message":"Successfully found disks space details"}}}', $json);
      $this->assertEquals(200, $response->getStatusCode());
  }

  public function test_checkdiskspace_all_return_false() {
    $mockHelper = $this->getMockBuilder(\Dashboard::class)
    ->disableOriginalConstructor()
    ->setMethods(array('getdiskspace'))
    ->getMock();
    $mockHelper->method('getdiskspace')->willReturn([]);
    self::$freepbx->Dashboard = $mockHelper;

    $response = $this->request("query{
      checkdiskspace {status message diskspace {storage_path}}
    }");

    $json = (string)$response->getBody();
    $this->assertEquals('{"errors":[{"message":"Sorry, unable to find any diskspace","status":false}]}', $json);
    $this->assertEquals(400, $response->getStatusCode());
  }

}
