<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 24/02/2017
 * Time: 14:41
 */

namespace Features\Dqf\Controller\API;


use API\V2\KleinController;
use Features\Dqf\Service\Authenticator;
use Features\Dqf\Service\Session;

class LoginController extends KleinController {

    /**
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exception
     */
    public function login() {
        // TODO: these should be passed as params
        $username = 'fabrizio@translated.net';
        $password = 'fabrizio@translated.net';

        $session = (new Authenticator())->login($username, $password);

        $this->response->code(200);
        $this->response->json(['session' => [
                'sessionId' => $session->getSessionId(),
                'expires' => $session->getExpires()
        ]]) ;
    }
}