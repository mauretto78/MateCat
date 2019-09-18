<?php

namespace Features\Dqf\Service;

abstract class AbstractService {

    /**
     * @var ISession
     */
    protected $session;

    /**
     * @var Client
     */
    protected $client;

    /**
     * AbstractService constructor.
     *
     * @param ISession $session
     */
    public function __construct( ISession $session ) {
        $this->session = $session ;
        $this->client = new Client();
        $this->client->setSession( $this->session );
    }
}