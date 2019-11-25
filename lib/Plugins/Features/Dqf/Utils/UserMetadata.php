<?php

namespace Features\Dqf\Utils;

use Features\Dqf\Factory\DataEncryptorFactory;
use Users\MetadataDao;

class UserMetadata {

    const DQF_USERNAME_KEY  = 'dqf_username';
    const DQF_PASSWORD_KEY  = 'dqf_password';
    const DQF_SESSION_ID    = 'dqf_session_id';
    const DQF_IS_GENERIC    = 'dqf_is_generic';
    const DQF_GENERIC_EMAIL = 'dqf_generic_email';

    /**
     * @return \Matecat\Dqf\Utils\DataEncryptor
     */
    private static function getDataEncryptor() {
        return DataEncryptorFactory::create();
    }

    public static function extractCredentials( $user_metadata ) {

        $dataEncryptor = self::getDataEncryptor();

        return [
                $dataEncryptor->decrypt( $user_metadata[ self::DQF_USERNAME_KEY ] ),
                $dataEncryptor->decrypt( $user_metadata[ self::DQF_PASSWORD_KEY ] ),
                $user_metadata[ self::DQF_SESSION_ID ],
                $user_metadata[ self::DQF_IS_GENERIC ],
                $dataEncryptor->decrypt( $user_metadata[ self::DQF_GENERIC_EMAIL ] ),
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
     * @param int    $uid
     * @param string $username
     * @param string $password
     * @param string $sessionId
     * @param bool   $isGeneric
     * @param null   $genericEmail
     */
    public static function setCredentials( $uid, $username, $password, $sessionId, $isGeneric, $genericEmail = null ) {

        $dataEncryptor = self::getDataEncryptor();
        $dao           = new MetadataDao();
        $dao->set( $uid, self::DQF_USERNAME_KEY, $dataEncryptor->encrypt( $username ) );
        $dao->set( $uid, self::DQF_PASSWORD_KEY, $dataEncryptor->encrypt( $password ) );
        $dao->set( $uid, self::DQF_SESSION_ID, $sessionId );
        $dao->set( $uid, self::DQF_IS_GENERIC, $isGeneric );

        if ( false === empty( $genericEmail ) ) {
            $dao->set( $uid, self::DQF_GENERIC_EMAIL, $dataEncryptor->encrypt( $genericEmail ) );
        }
    }
}