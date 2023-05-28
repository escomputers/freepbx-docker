<?php
namespace FreePBX\modules\Voicemail\Api\Rest;
use FreePBX\modules\Api\Rest\Base;
class Voicemail extends Base {
	protected $module = 'voicemail';
	public function setupRoutes($app) {

		/**
		 * @verb GET
		 * @returns - a mailbox resource
		 * @uri /voicemail/mailboxes/:id
		 */
		$app->get('/voicemail/mailboxes/{id}', function ($request, $response, $args) {
			\FreePBX::Modules()->loadFunctionsInc('voicemail');
			return $response->withJson(voicemail_mailbox_get($args['id']));
		})->add($this->checkAllReadScopeMiddleware());

		/**
		 * @verb PUT
		 * @uri /voicemail/password/:id
		 */
		$app->put('/voicemail/password/{id}', function ($request, $response, $args) {
			\FreePBX::Modules()->loadFunctionsInc('voicemail');
			$params = $request->getParsedBody();
			if (!isset($params['password'])) {
				$response->withJson(false);
			}

			$uservm = voicemail_getVoicemail();
			$vmcontexts = array_keys($uservm);

			foreach ($vmcontexts as $vmcontext) {
				if(isset($uservm[$vmcontext][$params['id']])) {

					$uservm[$vmcontext][$params['id']]['pwd'] = $params['password'];

					voicemail_saveVoicemail($uservm);

					$this->freepbx->astman->send_request("Command", array("Command" => "voicemail reload"));

					$response->withJson(true);
				}
			}

			return $response->withJson(false);
		})->add($this->checkAllWriteScopeMiddleware());
	}
}
