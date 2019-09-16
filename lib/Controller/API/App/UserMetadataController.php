<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 08/12/2016
 * Time: 09:45
 */

namespace API\App;


use API\V2\Exceptions\AuthenticationError;
use Bootstrap;
use Exceptions\NotFoundException;
use Features\Dqf\Service\SessionProvider;
use Features\Dqf\Utils\UserMetadata;

class UserMetadataController extends AbstractStatefulKleinController {

    public function update() {

        if ( !$this->user ) {
            throw new NotFoundException( 'User not found' );
        }

        $dqfUsername = $this->request->param( 'metadata' )[ 'dqf_username' ];
        $dqfPassword = $this->request->param( 'metadata' )[ 'dqf_password' ];

        try {
            // Authenticate
            $session = SessionProvider::getByCredentials( $dqfUsername, $dqfPassword );

            // persist data on db
            UserMetadata::setCredentials( $this->user->getUid(), $session );

            // update response
            $data = $this->user->getMetadataAsKeyValue();

            if ( empty ( $data ) ) {
                $data = null;
            }

            $this->response->json( $data );
        } catch ( AuthenticationError $e ) {
            $this->response->json( null );
        }
    }

    protected function afterConstruct() {
        Bootstrap::sessionClose();
    }

}