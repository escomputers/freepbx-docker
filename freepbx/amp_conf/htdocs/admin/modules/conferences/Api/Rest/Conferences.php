<?php
namespace FreePBX\modules\Conferences\Api\Rest;
use FreePBX\modules\Api\Rest\Base;
class Conferences extends Base {
	protected $module = 'conferences';
	public function setupRoutes($app) {

		/**
		* @verb GET
		* @returns - the conference list
		* @uri /conferences
		*/
		$app->get('/', function ($request, $response, $args) {
			\FreePBX::Modules()->loadFunctionsInc('conferences');
			$conferences = conferences_list();

			foreach($conferences as $conference) {
				$room = new \stdClass();
				$room->id = $conference[0];
				$room->description = $conference[1];
				$list[$conference[0]] = $room;
			}

			$list = $list ? $list : false;

			return $response->withJson($list);
		})->add($this->checkAllReadScopeMiddleware());

		/**
		* @verb GET
		* @returns - a list of conference settings
		* @uri /conference/:id
		*/
		$app->get('/{id}', function ($request, $response, $args) {
			\FreePBX::Modules()->loadFunctionsInc('conferences');
			$conference = conferences_get($args['id']);

			$conference = $conference ? $conference : false;

			return $response->withJson($conference);
		})->add($this->checkAllReadScopeMiddleware());

		/**
		* @verb DELETE
		* @returns - true if the conference was deleted, false otherwise
		* @uri /conference/:id
		*/
		$app->delete('/{id}', function ($request, $response, $args) {
			\FreePBX::Modules()->loadFunctionsInc('conferences');
			conferences_del($args['id']);

			return $response->withJson(true);
		})->add($this->checkAllWriteScopeMiddleware());

		/**
		* @verb PUT
		* @uri /conference/:id
		*/
		$app->put('/{id}', function ($request, $response, $args) {
			\FreePBX::Modules()->loadFunctionsInc('callforward');
			$params = $request->getParsedBody();
			conferences_del($args['id']);
			$ret = conferences_add($args["id"], $params["name"], $params["userpin"], $params["adminpin"], $params["options"], $params["joinmsg_id"], $params["music"], $params["users"]);
			return $response->withJson($ret);
		})->add($this->checkAllWriteScopeMiddleware());
	}
}
