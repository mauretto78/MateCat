<?php

namespace Dqf;

use Matecat\Dqf\SessionProvider;

class SessionProviderService {

    /**
     * @var SessionProvider
     */
    private static $sessionProvider;

    /**
     * @return SessionProvider
     * @throws \Predis\Connection\ConnectionException
     * @throws \ReflectionException
     */
    private static function getSessionProviderInstance() {

        if(false === isset(self::$sessionProvider)){
            self::$sessionProvider = SessionProviderFactory::create();
        }

        return self::$sessionProvider;
    }

    /**
     * @param $email
     *
     * @return mixed
     * @throws \Matecat\Dqf\Exceptions\SessionProviderException
     * @throws \Predis\Connection\ConnectionException
     * @throws \ReflectionException
     */
    public static function getAnonymous( $email ) {
        $sessionProvider = self::getSessionProviderInstance();

        if(false === $sessionProvider->hasGenericEmail($email)){
            self::createAnonymous( $email );
        }

        return $sessionProvider->getByGenericEmail($email);
    }

    /**
     * @param $email
     *
     * @throws \Matecat\Dqf\Exceptions\SessionProviderException
     * @throws \Predis\Connection\ConnectionException
     * @throws \ReflectionException
     */
    private static function createAnonymous( $email ) {
        self::getSessionProviderInstance()->createAnonymous(
                $email,
                \INIT::$DQF_GENERIC_USERNAME,
                \INIT::$DQF_GENERIC_PASSWORD
        );
    }

    /**
     * @param $externalReferenceId
     * @param $username
     * @param $password
     *
     * @return mixed|void
     * @throws \Matecat\Dqf\Exceptions\SessionProviderException
     * @throws \Predis\Connection\ConnectionException
     * @throws \ReflectionException
     */
    public static function get( $externalReferenceId, $username = null, $password = null ) {
        $sessionProvider = self::getSessionProviderInstance();

        if(false === $sessionProvider->hasId($externalReferenceId)){
            if(null === $username and null === $password){
                throw new \Exception('Session Provider cannot retrieve a DqfUser from the provided id. Username and password are needed to perform login.');
            }

            self::create( $externalReferenceId, $username, $password );
        }

        return self::getSessionProviderInstance()->getById($externalReferenceId);
    }

    /**
     * @param $externalReferenceId
     * @param $username
     * @param $password
     *
     * @throws \Matecat\Dqf\Exceptions\SessionProviderException
     * @throws \Predis\Connection\ConnectionException
     * @throws \ReflectionException
     */
    private static function create( $externalReferenceId, $username, $password ) {
        self::getSessionProviderInstance()->createByCredentials($externalReferenceId, $username, $password);
    }
}