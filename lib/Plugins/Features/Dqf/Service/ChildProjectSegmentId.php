<?php

namespace Features\Dqf\Service;

use Exception;
use Features\Dqf\Utils\Functions;
use INIT;

class ChildProjectSegmentId {

    private $session;
    private $localSegmentId;

    public function __construct( ISession $session, $localSegmentId ) {
        $this->session        = $session;
        $this->localSegmentId = $localSegmentId;
    }

    public function getRemoteId() {
        $client = new Client();
        $client->setSession( $this->session );

        $headers = [
                'sessionId' => $this->session->getSessionId(),
                'apiKey'    => INIT::$DQF_API_KEY,
                'clientId'  => Functions::scopeId( $this->localSegmentId )
        ];

        $resource = $client->createResource( '/DQFSegmentId', 'get', [ 'headers' => $headers ] );

        $client->curl()->multiExec();
        $content = json_decode( $client->curl()->getSingleContent( $resource ), true );

        if ( $client->curl()->hasError( $resource ) ) {
            throw new Exception( 'Error trying to get remote segment id' );
        }

        return $content[ 'dqfId' ];
    }
}