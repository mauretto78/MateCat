<?php

namespace Features\Dqf\Service;

use API\V2\Exceptions\AuthenticationError;
use Features\Dqf\Service\Struct\LoginRequestStruct;
use Features\Dqf\Service\Struct\LoginResponseStruct;
use Features\Dqf\Service\Struct\LogoutRequestStruct;

class Authenticator {

    /**
     * @var Session
     */
    private $session;

    /**
     * @var Client
     */
    private $client;

    /**
     * Authenticator constructor.
     *
     * @param Session $session
     */
    public function __construct( Session $session ) {
        $this->session = $session;
        $this->client = new Client();
    }

    /**
     * @return Session
     * @throws AuthenticationError
     */
    public function login() {
        $struct           = new LoginRequestStruct() ;
        $struct->email    = AuthenticatorDataEncryptor::encrypt( $this->session->getEmail() );
        $struct->password = AuthenticatorDataEncryptor::encrypt( $this->session->getPassword() );

        $request = $this->client->createResource( '/login', 'post', [
                'formData' => $struct->getParams(),
                'headers'  => $this->session->getHeaders( $struct->getHeaders() )
        ] );

        $this->client->curl()->multiExec();

        if ( $this->client->curl()->hasError( $request ) ) {
            // TODO: change this class to a DQF specific Authentication Error
            throw new AuthenticationError( 'Login failed with message: ' . $this->client->curl()->getSingleContent( $request ) );
        }

        $content  = json_decode( $this->client->curl()->getSingleContent( $request ), true );
        $response = new LoginResponseStruct( $content[ 'loginResponse' ] );

        \Log::doJsonLog( " SessionId " . $response->sessionId );

        $this->session->setSessionId( $response->sessionId );
        $this->session->setExpires( $response->expires );

        return $this->session;
    }

    /**
     * @return Session
     * @throws AuthenticationError
     */
    public function logout() {
        $struct           = new LogoutRequestStruct();
        $struct->email    = AuthenticatorDataEncryptor::encrypt( $this->session->getEmail() );
        $struct->sessionId =  $this->session->getSessionId();

        $request = $this->client->createResource( '/logout', 'post', [
                'formData' => $struct->getParams(),
                'headers'  => $this->session->getHeaders( $struct->getHeaders() )
        ] );

        $this->client->curl()->multiExec();

        if ( $this->client->curl()->hasError( $request ) ) {
            // TODO: change this class to a DQF specific Authentication Error
            throw new AuthenticationError( 'Logout failed with message: ' . $this->client->curl()->getSingleContent( $request ) );
        }

        $this->session->setSessionId(null);
        $this->session->setEmail(null);
        $this->session->setPassword(null);
        $this->session->setExpires(null);

        return $this->session;
    }
}