<?php

namespace Features\Dqf\Service;

use API\V2\Exceptions\AuthenticationError;
use Features\Dqf\Service\Struct\LoginRequestStruct;
use Features\Dqf\Service\Struct\LoginResponseStruct;
use Features\Dqf\Service\Struct\LogoutRequestStruct;
use Features\Dqf\Utils\AuthenticatorDataEncryptor;

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
     * @param ISession|null $session
     */
    public function __construct( ISession $session = null ) {
        $this->session = ( isset( $session ) ) ? $session : new Session();
        $this->client  = new Client();
    }

    /**
     * @param string $email
     * @param string $password
     *
     * @return Session
     * @throws AuthenticationError
     */
    public function login( $email, $password ) {
        $struct           = new LoginRequestStruct();
        $struct->email    = AuthenticatorDataEncryptor::encrypt( $email );
        $struct->password = AuthenticatorDataEncryptor::encrypt( $password );

        $request = $this->client->createResource( '/login', 'post', [
                'formData' => $struct->getParams(),
                'headers'  => $this->session->getHeaders( $struct->getHeaders() )
        ] );

        $this->client->curl()->multiExec();

        if ( $this->client->curl()->hasError( $request ) ) {
            throw new AuthenticationError( 'Login failed with message: ' . $this->client->curl()->getSingleContent( $request ) );
        }

        $content  = json_decode( $this->client->curl()->getSingleContent( $request ), true );
        $response = new LoginResponseStruct( $content[ 'loginResponse' ] );

        \Log::doJsonLog( " SessionId " . $response->sessionId );

        $this->session->setEmail( $email );
        $this->session->setPassword( $password );
        $this->session->setSessionId( $response->sessionId );
        $this->session->setExpires( (int)( strtotime( "now" ) + (int)$response->expires ) );

        return $this->session;
    }

    /**
     * @param null $email
     * @param null $sessionId
     *
     * @return Session
     * @throws AuthenticationError
     */
    public function logout() {
        $struct            = new LogoutRequestStruct();
        $struct->email     = AuthenticatorDataEncryptor::encrypt( $this->session->getEmail() );
        $struct->sessionId = $this->session->getSessionId();

        $request = $this->client->createResource( '/logout', 'post', [
                'formData' => $struct->getParams(),
                'headers'  => $this->session->getHeaders( $struct->getHeaders() )
        ] );

        $this->client->curl()->multiExec();

        if ( $this->client->curl()->hasError( $request ) ) {
            throw new AuthenticationError( 'Logout failed with message: ' . $this->client->curl()->getSingleContent( $request ) );
        }

        \Log::doJsonLog( " Logging out with success" );

        return new Session();
    }
}