<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 03/03/2017
 * Time: 17:09
 */

namespace Features\Dqf\Utils;


use Features\Dqf\Service\ISession;
use Users\MetadataDao;

class UserMetadata {

    const DQF_USERNAME_KEY    = 'dqf_username';
    const DQF_PASSWORD_KEY    = 'dqf_password';
    const DQF_SESSION_ID      = 'dqf_session_id';
    const DQF_SESSION_EXPIRES = 'dqf_session_expires';

    public static function extractCredentials( $user_metadata ) {
        return [
                $user_metadata[ self::DQF_USERNAME_KEY ],
                $user_metadata[ self::DQF_PASSWORD_KEY ],
                $user_metadata[ self::DQF_SESSION_ID ],
                $user_metadata[ self::DQF_SESSION_EXPIRES ],
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
        $dao->delete( $uid, self::DQF_SESSION_EXPIRES );
    }

    /**
     * Set DQF user credentials to DB (insert or update)
     *
     * @param          $uid
     * @param ISession $session
     */
    public static function setCredentials( $uid, ISession $session ) {
        $dao = new MetadataDao();
        $dao->set( $uid, self::DQF_USERNAME_KEY, $session->getEmail() );
        $dao->set( $uid, self::DQF_PASSWORD_KEY, $session->getPassword() );
        $dao->set( $uid, self::DQF_SESSION_ID, $session->getSessionId() );
        $dao->set( $uid, self::DQF_SESSION_EXPIRES, $session->getExpires() );
    }
}