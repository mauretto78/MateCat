<?php

namespace Features\Dqf\CommandHandler;

use Features\Dqf\Utils\Factory\UserRepositoryFactory;
use Features\Dqf\Utils\SessionProviderService;
use Matecat\Dqf\Utils\DataEncryptor;

abstract class AbstractCommandHanlder implements CommandHandlerInterface {

    /**
     * @param $externalId
     *
     * @return mixed|void
     * @throws \Exception
     */
    protected function getSessionId($externalId) {

        $userRepo = UserRepositoryFactory::create();
        $dqfUser = $userRepo->getByExternalId($externalId);

        // DECRYPT
        $dataEncryptor = new DataEncryptor(\INIT::$DQF_ENCRYPTION_KEY, \INIT::$DQF_ENCRYPTION_IV);

        if ( $dqfUser->isGeneric() ) {
            return SessionProviderService::getAnonymous( $dataEncryptor->decrypt($dqfUser->getGenericEmail()) );
        }

        return SessionProviderService::get( $dqfUser->getExternalReferenceId(), $dataEncryptor->decrypt($dqfUser->getUsername()), $dataEncryptor->decrypt($dqfUser->getPassword()) );
    }

    /**
     * @param $externalId
     *
     * @return string|null
     * @throws \Exception
     */
    protected function getGenericEmail($externalId) {

        $userRepo = UserRepositoryFactory::create();
        $dqfUser = $userRepo->getByExternalId($externalId);

        $dataEncryptor = new DataEncryptor(\INIT::$DQF_ENCRYPTION_KEY, \INIT::$DQF_ENCRYPTION_IV);

        return ( $dqfUser->isGeneric() ) ? $dataEncryptor->decrypt($dqfUser->getGenericEmail()) : null;
    }
}