<?php

namespace Features\Dqf\CommandHandler;

use Features\Dqf\Factory\UserRepositoryFactory;
use Features\Dqf\Utils\SessionProviderService;
use Matecat\Dqf\Utils\DataEncryptor;
use Translators\JobsTranslatorsDao;

abstract class AbstractCommandHandler implements CommandHandlerInterface {

    /**
     * @param $externalId
     *
     * @return mixed|void
     * @throws \Exception
     */
    protected function getSessionId($externalId) {

        $userRepo = UserRepositoryFactory::create();
        $dqfUser = $userRepo->getByExternalId($externalId);

        if ( false === $dqfUser ) {
            throw new \Exception('No DQF user is already logged in.');
        }

        // DECRYPT
        $dataEncryptor = new DataEncryptor(\INIT::$DQF_ENCRYPTION_KEY, \INIT::$DQF_ENCRYPTION_IV);

        if ( $dqfUser->isGeneric() ) {
            return SessionProviderService::getAnonymous( $dataEncryptor->decrypt($dqfUser->getGenericEmail()), $externalId );
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

    /**
     * @param $externalId
     *
     * @return string|null
     * @throws \Exception
     */
    protected function getAssignerEmail( $externalId) {

        $userRepo = UserRepositoryFactory::create();
        $dqfUser = $userRepo->getByExternalId($externalId);

        $dataEncryptor = new DataEncryptor(\INIT::$DQF_ENCRYPTION_KEY, \INIT::$DQF_ENCRYPTION_IV);

        return ( $dqfUser->isGeneric() ) ? $dataEncryptor->decrypt($dqfUser->getGenericEmail()) : $dataEncryptor->decrypt($dqfUser->getUsername());
    }

    /**
     * @param int $job_id
     * @param string $job_password
     *
     * @return int
     */
    protected function getTranslatorUid( $job_id, $job_password) {
        $jobsTranslatorsDao = new JobsTranslatorsDao();
        $translatorProfile = $jobsTranslatorsDao->findUserIdByJobIdAndPassword($job_id, $job_password);

        return (int)$translatorProfile['uid_translator'];
    }
}