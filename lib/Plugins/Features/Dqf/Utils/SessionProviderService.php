<?php

namespace Features\Dqf\Utils;

use Features\Dqf\Factory\SessionProviderFactory;
use Matecat\Dqf\SessionProvider;

class SessionProviderService {

    /**
     * @var SessionProvider
     */
    private static $sessionProvider;

    /**
     * @return SessionProvider
     * @throws \Exception
     */
    private static function getSessionProviderInstance() {

        if ( false === isset( self::$sessionProvider ) ) {
            self::$sessionProvider = SessionProviderFactory::create();
        }

        return self::$sessionProvider;
    }

    /**
     * @param string $email
     * @param int    $externalId
     *
     * @return mixed
     * @throws \Exception
     */
    public static function getAnonymous( $email, $externalId ) {
        $sessionProvider = self::getSessionProviderInstance();

        if ( false === $sessionProvider->hasGenericEmail( $email ) ) {
            self::createAnonymous( $email, $externalId );
        }

        return $sessionProvider->getByGenericEmail( $email, $externalId );
    }

    /**
     * @param string $email
     * @param int    $externalId
     *
     * @throws \Exception
     */
    private static function createAnonymous( $email, $externalId ) {
        self::getSessionProviderInstance()->create( [
                'externalReferenceId' => $externalId,
                'genericEmail'        => $email,
                'username'            => \INIT::$DQF_GENERIC_USERNAME,
                'password'            => \INIT::$DQF_GENERIC_PASSWORD,
                'isGeneric'           => true,
        ] );
    }

    /**
     * @param int $externalReferenceId
     * @param null $username
     * @param null $password
     *
     * @return mixed|void
     * @throws \Exception
     */
    public static function get( $externalReferenceId, $username = null, $password = null ) {
        $sessionProvider = self::getSessionProviderInstance();

        if ( false === $sessionProvider->hasId( $externalReferenceId ) ) {
            if ( null === $username and null === $password ) {
                throw new \Exception( 'SessionProvider cannot retrieve a DqfUser from the provided id. Username and password are needed to perform login.' );
            }

            self::create( $externalReferenceId, $username, $password );
        }

        return self::getSessionProviderInstance()->getById( $externalReferenceId );
    }

    /**
     * @param $externalReferenceId
     * @param $username
     * @param $password
     *
     * @throws \Exception
     */
    private static function create( $externalReferenceId, $username, $password ) {
        self::getSessionProviderInstance()->create( [
                'externalReferenceId' => $externalReferenceId,
                'username'            => $username,
                'password'            => $password,
        ] );
    }
}