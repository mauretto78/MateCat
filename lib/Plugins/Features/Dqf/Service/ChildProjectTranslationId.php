<?php

namespace Features\Dqf\Service;

use Exception;
use Features\Dqf\Utils\Functions;
use Files_FileStruct;
use INIT;

class ChildProjectTranslationId {

    protected $session ;
    protected $localSegmentId ;
    private   $dqfChildProjectId;

    public function __construct(ISession $session, $dqfChildProjectId, $localSegmentId ) {
        $this->session = $session ;
        $this->localSegmentId = $localSegmentId ;
        $this->dqfChildProjectId = $dqfChildProjectId;
    }

    public function getRemoteId() {
        $client = new Client();
        $client->setSession( $this->session );

        $headers = [
                'sessionId'  =>   $this->session->getSessionId() ,
                'apiKey'     =>   INIT::$DQF_API_KEY ,
                'clientId'   =>   Functions::scopeId( $this->dqfChildProjectId . '-' . $this->localSegmentId )
        ];

        $resource = $client->createResource('/DQFTranslationId', 'get', [ 'headers' => $headers ]);

        $client->curl()->multiExec() ;
        $content = json_decode( $client->curl()->getSingleContent( $resource ), true ) ;

        if ( $client->curl()->hasError( $resource ) ) {
            throw new Exception('Error trying to get remote translation id') ;
        }

        return $content['dqfId'] ;
    }
}