<?php

namespace Features\Dqf\Utils;

use Features\Dqf\Service\ISession;
use Features\Dqf\Utils\Factory\DataEncryptorFactory;
use Users\MetadataDao;

class UserMetadata {

    const DQF_USERNAME_KEY    = 'dqf_username';
    const DQF_PASSWORD_KEY    = 'dqf_password';
    const DQF_SESSION_ID      = 'dqf_session_id';
    const DQF_IS_GENERIC      = 'dqf_is_generic';
    const DQF_GENERIC_EMAIL   = 'dqf_generic_email';

    public static function extractCredentials( $user_metadata ) {

        $dataEncryptor = DataEncryptorFactory::create();

        return [
                $dataEncryptor->decrypt($user_metadata[ self::DQF_USERNAME_KEY ]),
                $dataEncryptor->decrypt($user_metadata[ self::DQF_PASSWORD_KEY ]),
                $user_metadata[ self::DQF_SESSION_ID ],
                $user_metadata[ self::DQF_IS_GENERIC ],
                $dataEncryptor->decrypt($user_metadata[ self::DQF_GENERIC_EMAIL ]),
        ];
    }

    /**
     * Clear DQF user credentials from DB
     *
     * @param $uid
     */
    public static function clearCredentials( $uid ) {
        $dao = new MetadataDao();
        $dao->delete( $uid, self::DQF_USERNAME_KEY );
        $dao->delete( $uid, self::DQF_PASSWORD_KEY );
        $dao->delete( $uid, self::DQF_SESSION_ID );
        $dao->delete( $uid, self::DQF_IS_GENERIC );
        $dao->delete( $uid, self::DQF_GENERIC_EMAIL );
    }

    /**
     * Set DQF user credentials to DB (insert or update)
     *
     * @param          $uid
     * @param ISession $session
     */
    public static function setCredentials( $uid, $username, $password, $sessionId, $isGeneric, $genericEmail = null ) {

        $dataEncryptor = DataEncryptorFactory::create();
        $dao = new MetadataDao();
        $dao->set( $uid, self::DQF_USERNAME_KEY, $dataEncryptor->encrypt($username) );
        $dao->set( $uid, self::DQF_PASSWORD_KEY, $dataEncryptor->encrypt($password) );
        $dao->set( $uid, self::DQF_SESSION_ID, $sessionId );
        $dao->set( $uid, self::DQF_IS_GENERIC, $isGeneric );

        if(false === empty($genericEmail)) {
            $dao->set( $uid, self::DQF_GENERIC_EMAIL, $dataEncryptor->encrypt($genericEmail) );
        }
    }
}