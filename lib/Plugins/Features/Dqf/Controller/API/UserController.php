<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/09/2017
 * Time: 15:57
 */

namespace Features\Dqf\Controller\API;

use API\App\AbstractStatefulKleinController;
use Features\Dqf\Factory\SessionProviderFactory;
use Features\Dqf\Utils\UserMetadata;

class UserController extends AbstractStatefulKleinController {

    /**
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exception
     */
    public function clearCredentials() {
        $userId = $this->getUser()->getUid();

        $sessionProvider = SessionProviderFactory::create();
        $sessionProvider->destroy( $userId );
        UserMetadata::clearCredentials( $userId );

        $this->response->code( 200 );
    }
}

