<?php

namespace FreePBX\Api\Rest;
use FreePBX\modules\Api\Rest\Base;
class System extends Base {
	protected $module = 'framework';
	public static function getScopes() {
		return [
			'read:system' => [
				'description' => _('Read system information'),
			]
		];
	}

	public function setupRoutes($app) {
		$app->get('/version', function ($request, $response, $args) {
			$data = array('status' => true, 'version' => getVersion());
			return $response->withJson($data);
		})->add($this->checkReadScopeMiddleware('system'));

		$app->get('/engine', function ($request, $response, $args) {
			$data = array('status' => true, 'engine' => engine_getinfo()['version']);
			return $response->withJson($data);
		})->add($this->checkReadScopeMiddleware('system'));

		$app->get('/needreload', function ($request, $response, $args) {
			$data = array('status' => true, 'needreload' => check_reload_needed(true));
			return $response->withJson($data);
		})->add($this->checkReadScopeMiddleware('system'));

		//rest api to perform doreload/apply config 
		$app->put('/reload', function($request, $response, $args) {
		$txnId = $this->freepbx->api->addTransaction("Processing","Framework","rest-do-reload");
		$this->freepbx->api->setGqlApiHelper()->doreload($txnId);
		return $response->withJson(['status' => true,
				'message' => 'Doreload/apply config has been initiated. Please check the status using /apistatus/'.$txnId.' api with the following transaction id '.$txnId]);
		})->add($this->checkReadScopeMiddleware('system'));

		//fetch the api status for asyc task
		$app->get('/apistatus/{txnId}', function($request, $response, $args) {
		//fetch the api status
		$message = $this->freepbx->api->getTransactionStatus($args['txnId']);
		//return the api status
		return $response->withJson(['status' => true,
				'message' => $message['event_status']]);
		})->add($this->checkReadScopeMiddleware('system'));
	}
}
