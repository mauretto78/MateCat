<?php

namespace Features\Dqf\Model;

use Chunks_ChunkStruct;
use Exception;
use Features\Dqf\Model\CachedAttributes\SegmentOrigin;
use Features\Dqf\Service\Struct\Request\ChildProjectTranslationRequestStruct;
use Features\Dqf\Service\TranslationBatchService;
use Features\Dqf\Utils\Functions;
use Features\Dqf\Utils\SegmentTranslationTransformer;
use INIT;

class TranslationChildProject extends AbstractChildProject {

    const SEGMENT_PAIRS_CHUNK_SIZE = 80;

    /**
     * @var SegmentOrigin
     */
    protected $originMap;

    /**
     * TranslationChildProject constructor.
     *
     * @param Chunks_ChunkStruct $chunk
     *
     * @throws Exception
     */
    public function __construct( Chunks_ChunkStruct $chunk ) {
        parent::__construct( $chunk, 'translate' );
        $this->originMap = new SegmentOrigin();
    }

    /**
     * At this point we must call this endpoint:
     * https://dqf-api.stag.taus.net/#!/Project%2FChild%2FFile%2FTarget_Language%2FSegment/add_0
     *
     * in order to do that, the most complext data structure we need to arrange is the one we pass in the
     * request's body:
     *
     * https://github.com/TAUSBV/dqf-api/blob/master/v3/README.md#batchUpload
     *
     * Example:
     *
     * { "segmentPairs":[
     *    {
     *       "sourceSegmentId":1, <---  id of the source segment
     *       "clientId":"8ab68bd9-8ae7-4860-be6c-bc9a4b276e37", <-- segment_id
     *       "targetSegment":"",                                                            <--- in order to collect this data we must read all segment versions since the last update...
     *       "editedSegment":"Proin interdum mauris non ligula pellentesque ultrices.",     <--- in fact we cannot rely on the latest version only. Subsequent edits may have happened.
     *       "time":6582,                                                                   <-- same thing here, we must make a sum of the time to edit of all versions ??? hum...
     *       "segmentOriginId":5,                         <--- segment origin mapping, read the docs
     *       "mtEngineId":null,                           <--- ??  we should have this field
     *       "mtEngineOtherName":null,                    <--- not needed ? ?
     *       "matchRate":0                                <---- we have this one.
     *    },
     *    {
     *       "sourceSegmentId":2,
     *       "clientId":"e5e6f2ae-7811-4d49-89df-d1b18d11f591",
     *       "targetSegment":"Duis mattis egestas metus.",
     *       "editedSegment":"Duis mattis egostas ligula matus.",
     *       "time":5530,
     *       "segmentOriginId":2,
     *       "mtEngineId":null,
     *       "mtEngineOtherName":null,
     *       "matchRate":100
     *    } ]
     *  }
     *
     * Given an input chunk, we may end up needing to make multiple batch requests, reading the Project Map.
     *
     * @throws \ReflectionException
     * @throws \Exception
     */
    protected function _submitData() {

        $this->files              = $this->chunk->getFiles();
        $service                  = new TranslationBatchService( $this->userSession );
        $segmentTranslationHelper = new SegmentTranslationTransformer( $this->userSession );

        foreach ( $this->files as $file ) {
            list ( $fileMinIdSegment, $fileMaxIdSegment ) = $file->getMaxMinSegmentBoundariesForChunk( $this->chunk );

            $remoteFileId     = $this->_findRemoteFileId( $file );
            $dqfChildProjects = $this->dqfProjectMapResolver
                    ->getCurrentInSegmentIdBoundaries(
                            $fileMinIdSegment, $fileMaxIdSegment
                    );

            foreach ( $dqfChildProjects as $dqfChildProject ) {

                $dao          = new \Translations_SegmentTranslationDao();
                $translations = $dao->getByFile( $file );

                // Now we have translations, make the actual call, one per file per project
                $segmentPairs = [];
                foreach ( $translations as $translation ) {
                    $convertedForDqfArray = $segmentTranslationHelper->transform( $translation );
                    $segmentPairs[]       = ( new SegmentPairStruct( [
                            "sourceSegmentId"   => $convertedForDqfArray[ 'sourceSegmentId' ],
                            "clientId"          => $convertedForDqfArray[ 'clientId' ],
                            "targetSegment"     => $convertedForDqfArray[ 'targetSegment' ],
                            "editedSegment"     => $convertedForDqfArray[ 'editedSegment' ],
                            "time"              => $convertedForDqfArray[ 'time' ],
                            "segmentOriginId"   => $convertedForDqfArray[ 'segmentOriginId' ],
                            "matchRate"         => $convertedForDqfArray[ 'matchRate' ],
                            "mtEngineId"        => $convertedForDqfArray[ 'mtEngineId' ],
                            "mtEngineOtherName" => $convertedForDqfArray[ 'mtEngineOtherName' ],
                    ] ) )->toArray();
                }

                $segmentParisChunks = array_chunk( $segmentPairs, self::SEGMENT_PAIRS_CHUNK_SIZE );

                foreach ( $segmentParisChunks as $segmentParisChunk ) {
                    $requestStruct                 = new ChildProjectTranslationRequestStruct();
                    $requestStruct->sessionId      = $this->userSession->getSessionId();
                    $requestStruct->fileId         = $remoteFileId;
                    $requestStruct->projectKey     = $dqfChildProject->dqf_project_uuid;
                    $requestStruct->projectId      = $dqfChildProject->dqf_project_id;
                    $requestStruct->targetLangCode = $this->chunk->target;
                    $requestStruct->apiKey         = INIT::$DQF_API_KEY;

                    $requestStruct->setSegments( $segmentParisChunk );

                    $service->addRequestStruct( $requestStruct );
                }
            }
        }

        $results = $service->process();
        $this->_saveResults( $results );
    }

    /**
     * @param $results
     *
     * @throws Exception
     */
    protected function _saveResults( $results ) {
        $results = array_map( function ( $item ) {
            $translations = json_decode( $item, true )[ 'translations' ];

            return array_map( function ( $item ) {
                return [
                        $this->translationIdFromDqf( $item[ 'clientId' ] ), $item[ 'dqfId' ]
                ];
            }, $translations );
        }, $results );

        $dao = new DqfSegmentsDao();

        foreach ( $results as $batch ) {
            $dao->insertBulkMapForTranslationId( $batch );
        }
    }

    /**
     * @param $id
     *
     * @return mixed
     */
    protected function translationIdFromDqf( $id ) {
        $cleanId = Functions::descope( $id );
        list( $dqfMapId, $segmentId ) = explode( '-', $cleanId );

        return $segmentId;
    }
}