<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 08/12/2016
 * Time: 09:45
 */

namespace API\App;

use Bootstrap;
use Exceptions\NotFoundException;
use Features\Dqf\Factory\SessionProviderFactory;
use Features\Dqf\Utils\UserMetadata;

class UserMetadataController extends AbstractStatefulKleinController {

    /**
     * @throws \Exception
     */
    public function update() {

        if ( !$this->user ) {
            throw new NotFoundException( 'User not found' );
        }

        $loginParams = $this->getLoginParams($this->user->getUid(), $this->request->param( 'metadata' ));
        $sessionProvider = SessionProviderFactory::create();

        try {
            // Authenticate and get the sessionId from DQF
            $sessionId = $sessionProvider->create( $loginParams );

            // persist metadata on db
            UserMetadata::setCredentials( $this->user->getUid(), $loginParams['username'], $loginParams['password'], $sessionId, $loginParams['isGeneric'] );

            // update response
            $data = $this->user->getMetadataAsKeyValue();

            if ( empty ( $data ) ) {
                $data = null;
            }

            $this->response->json( $data );
        } catch ( \Exception $e ) {
            $this->response->json( null );
        }
    }

    /**
     * @param $userId
     * @param $metadata
     *
     * @return array
     */
    private function getLoginParams($userId, $metadata) {

        // do anonymous login
        if ( isset( $metadata[ 'dqf_generic_email' ] ) ) {
            return [
                    'externalReferenceId' => $userId,
                    'username'            => \INIT::$DQF_GENERIC_USERNAME,
                    'password'            => \INIT::$DQF_GENERIC_PASSWORD,
                    'isGeneric'           => true,
                    'genericEmail'        => $metadata[ 'dqf_generic_email' ],
            ];
        }

        // do regular login
        if ( isset( $metadata[ 'dqf_username' ] ) and isset( $metadata[ 'dqf_password' ] ) ) {
            return [
                    'externalReferenceId' => $userId,
                    'username'            => $metadata[ 'dqf_username' ],
                    'password'            => $metadata[ 'dqf_password' ],
                    'isGeneric'           => false,
            ];
        }
    }

    protected function afterConstruct() {
        Bootstrap::sessionClose();
    }

}