<?php

namespace Features\Dqf\Service;

use Exception;
use Features\Dqf\Exception\SessionProviderException;
use Features\Dqf\Model\UserModel;
use Features\Dqf\Utils\UserMetadata;

class SessionProvider {

    /**
     * Provides the sessionId from credentials
     *
     * @param string $username
     * @param string $password
     *
     * @return Session
     * @throws \API\V2\Exceptions\AuthenticationError
     */
    public static function getByCredentials( $username, $password ) {
        return ( new Authenticator() )->login( $username, $password );
    }

    /**
     * Provides the sessionId from user's metadata
     *
     * @param $userId
     *
     * @return Session
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws Exception
     */
    public static function getByUserId( $userId ) {
        $dqfUser = new UserModel( ( new \Users_UserDao() )->getByUid( $userId ) );

        if ( !$dqfUser ) {
            throw new SessionProviderException( "User with id " . $userId . " does not exists" );
        }

        $meta = $dqfUser->getMetadata();

        if ( empty( $meta ) ) {
            throw new SessionProviderException( "User with id " . $userId . " has not metadata set. Try to login first." );
        }

        if ( self::isSessionStillValid( $meta ) ) {
            $session = new Session();
            $session->setEmail( $meta[ 'dqf_username' ] );
            $session->setPassword( $meta[ 'dqf_password' ] );
            $session->setSessionId( $meta[ 'dqf_session_id' ] );
            $session->setExpires( $meta[ 'dqf_session_expires' ] );

            return $session;
        }

        $session = self::getByCredentials( $meta[ 'dqf_username' ], $meta[ 'dqf_password' ] );
        UserMetadata::setCredentials($userId, $session);

        return $session;
    }

    /**
     * @param array $meta
     *
     * @return bool
     */
    private static function isSessionStillValid( $meta ) {
        return isset( $meta[ 'dqf_session_id' ] ) and '' !== $meta[ 'dqf_session_id' ] and isset( $meta[ 'dqf_session_expires' ] ) and $meta[ 'dqf_session_expires' ] >= strtotime( "now" );
    }

    /**
     * @param $userId
     *
     * @throws \API\V2\Exceptions\AuthenticationError
     */
    public static function destroy( $userId ) {
        $session = self::getByUserId( $userId );
        ( new Authenticator( $session ) )->logout();
        UserMetadata::clearCredentials( $userId );
    }
}
