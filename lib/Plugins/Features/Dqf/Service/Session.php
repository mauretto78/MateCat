<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 24/02/2017
 * Time: 13:19
 */

namespace Features\Dqf\Service;

use Exception;
use Features\Dqf\Service\Struct\IBaseStruct;

class Session implements ISession {

    protected $email;
    protected $password;
    protected $sessionId;
    protected $expires;

    /**
     * @param mixed $sessionId
     */
    public function setSessionId( $sessionId ) {
        $this->sessionId = $sessionId;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getSessionId() {
        if ( is_null( $this->sessionId ) ) {
            throw new Exception( 'sessionId is null, try to login first' );
        }

        return $this->sessionId;
    }

    /**
     * @param mixed $expires
     */
    public function setExpires( $expires ) {
        $this->expires = $expires;
    }

    /**
     * @return mixed
     */
    public function getExpires() {
        return $this->expires;
    }

    /**
     * @param mixed $email
     */
    public function setEmail( $email ) {
        $this->email = $email;
    }

    /**
     * @return mixed
     */
    public function getEmail() {
        return $this->email;
    }

    /**
     * @param mixed $password
     */
    public function setPassword( $password ) {
        $this->password = $password;
    }

    /**
     * @return mixed
     */
    public function getPassword() {
        return $this->password;
    }

    /**
     * @param $headers
     *
     * @return mixed
     */
    public function getHeaders( $headers ) {
        return $headers;
    }

    /**
     * @param IBaseStruct $struct
     *
     * @return mixed
     */
    public function filterHeaders( IBaseStruct $struct ) {
        return $struct->getHeaders();
    }
}