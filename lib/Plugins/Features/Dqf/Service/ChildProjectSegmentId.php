<?php

namespace Features\Dqf\Service;

use Exception;
use Features\Dqf\Utils\Functions;
use Files_FileStruct;
use INIT;

class ChildProjectSegmentId {

    protected $session ;
    protected $segment ;

    public function __construct(ISession $session, \Segments_SegmentStruct $segment ) {
        $this->session = $session ;
        $this->segment = $segment ;
    }

    public function getRemoteId() {
        $client = new Client();
        $client->setSession( $this->session );

        $headers = [
                'sessionId'  =>   $this->session->getSessionId() ,
                'apiKey'     =>   INIT::$DQF_API_KEY ,
                'clientId'   =>   Functions::scopeId( $this->segment->id )
        ];

        $resource = $client->createResource('/DQFSegmentId', 'get', [ 'headers' => $headers ]);

        $client->curl()->multiExec() ;
        $content = json_decode( $client->curl()->getSingleContent( $resource ), true ) ;

        if ( $client->curl()->hasError( $resource ) ) {
            throw new Exception('Error trying to get remote file id') ;
        }

        return $content['dqfId'] ;
    }
}