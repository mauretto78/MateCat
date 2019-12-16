<?php

namespace Features\Dqf\CommandHandler;

use Features\Dqf\Command\CommandInterface;
use Features\Dqf\Factory\DataEncryptorFactory;
use Features\Dqf\Factory\UserRepositoryFactory;
use Features\Dqf\Utils\SessionProviderService;
use Matecat\Dqf\Utils\DataEncryptor;
use Translators\JobsTranslatorsDao;

abstract class AbstractCommandHandler implements CommandHandlerInterface {

    /**
     * @var CommandInterface
     */
    protected $command;

    /**
     * @var int
     */
    protected $userId;

    /**
     * @var string
     */
    protected $userEmail;

    /**
     * @param string $email
     * @param null   $userId
     *
     * @return mixed|void
     * @throws \Exception
     */
    protected function getSessionId( $email, $userId = null ) {

        $dataEncryptor = DataEncryptorFactory::create();
        $dqfUserRepo   = UserRepositoryFactory::create();

        if ( !$userId ) {
            $dqfUser = $dqfUserRepo->getByUsername( $dataEncryptor->encrypt( $email ) );

            if ( false === $dqfUser ) {
                return SessionProviderService::getAnonymous( $email );
            }
        }

        $dqfUser = $dqfUserRepo->getByExternalId( $userId );

        if ( false === $dqfUser ) {
            return SessionProviderService::getAnonymous( $email, $userId );
        }

        if ( $dqfUser->isGeneric() ) {
            return SessionProviderService::getAnonymous( $dataEncryptor->decrypt( $dqfUser->getGenericEmail() ), $userId );
        }

        return SessionProviderService::get( $dqfUser->getExternalReferenceId(), $dataEncryptor->decrypt( $dqfUser->getUsername() ), $dataEncryptor->decrypt( $dqfUser->getPassword() ) );
    }

    /**
     * @param $externalId
     *
     * @return string|null
     * @throws \Exception
     */
    protected function getGenericEmail( $externalId ) {

        $userRepo = UserRepositoryFactory::create();
        $dqfUser  = $userRepo->getByExternalId( $externalId );

        $dataEncryptor = new DataEncryptor( \INIT::$DQF_ENCRYPTION_KEY, \INIT::$DQF_ENCRYPTION_IV );

        return ( $dqfUser->isGeneric() ) ? $dataEncryptor->decrypt( $dqfUser->getGenericEmail() ) : null;
    }

    /**
     * @return array
     * @throws \Exception
     */
    protected function getDqfReferenceEmailAndUserId() {

        $translator = $this->getTranslator( $this->command->job_id, $this->command->job_password );

        if ( $translator ) {
            return [
                    'email' => $translator->email,
                    'uid'   => $translator->getUser()->uid, // this can be null
            ];
        }

        return [
                'email' => \INIT::$DQF_GENERIC_EMAIL,
                'uid'   => null
        ];
    }

    /**
     * @param int    $job_id
     * @param string $job_password
     *
     * @return \DataAccess_IDaoStruct|\Translators\JobsTranslatorsStruct
     */
    protected function getTranslator( $job_id, $job_password ) {
        $jobsTranslatorsDao = new JobsTranslatorsDao();

        return $jobsTranslatorsDao->findUserIdByJobIdAndPassword( $job_id, $job_password );
    }

}