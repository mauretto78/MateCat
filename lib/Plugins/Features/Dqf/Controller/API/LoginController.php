<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 24/02/2017
 * Time: 14:41
 */

namespace Features\Dqf\Controller\API;


use API\V2\KleinController;
use Features\Dqf\Utils\Factory\SessionProviderFactory;
use Features\Dqf\Utils\Factory\UserRepositoryFactory;

class LoginController extends KleinController {

    /**
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exception
     */
    public function login() {

        $id       = 1;
        $username = 'fabrizio@translated.net';
        $password = 'password';

        $sessionProvider = SessionProviderFactory::create();
        $sessionId       = $sessionProvider->create( [
                'externalReferenceId' => $id,
                'username'            => $username,
                'password'            => $password,
        ] );

        $userRepository = UserRepositoryFactory::create();

        $dqfUser = $userRepository->getByExternalId( $id );

        $this->response->code( 200 );
        $this->response->json( [
                'session' => [
                        'sessionId'      => $sessionId,
                        'expires'        => $dqfUser->getSessionExpiresAt(),
                        'is_still_valid' => $dqfUser->isSessionStillValid()
                ]
        ] );
    }
}