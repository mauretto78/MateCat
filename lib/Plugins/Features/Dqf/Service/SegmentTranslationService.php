<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 17/07/2017
 * Time: 17:21
 */

namespace Features\Dqf\Service;

use Features\Dqf\Model\CachedAttributes\SegmentOrigin;
use Features\Dqf\Model\DqfProjectMapDao;
use Features\Dqf\Model\DqfProjectMapStruct;
use Features\Dqf\Model\ExtendedTranslationStruct;
use Features\Dqf\Model\TranslationVersionDao;
use Features\Dqf\Service\Struct\Request\ChildProjectSegmentTranslationRequestStruct;

class SegmentTranslationService extends AbstractService {


    /**
     * @var \Translations_SegmentTranslationStruct
     */
    private $translation;

    /**
     * SegmentTranslationService constructor.
     *
     * @param ISession                               $session
     * @param \Translations_SegmentTranslationStruct $translation
     */
    public function __construct( ISession $session, \Translations_SegmentTranslationStruct $translation ) {
        parent::__construct( $session );

        $this->translation = $translation;
    }

    /**
     * For further documentation, please see:
     * https://dqf-api.stag.taus.net/#!/Project%2FChild%2FFile%2FTarget_Language%2FSegment/update_0
     *
     * @throws \Exception
     */
    public function process() {
        $request = $this->createChildProjectSegmentTranslationRequestStruct();
        $url = "/project/child/%s/file/%s/targetLang/%s/segment/%s" ;

        $resource = $this->client->createResource( $url, 'put', [
                'headers'    => $this->session->filterHeaders( $request ),
                'pathParams' => $request->getPathParams(),
                'formData' => $request->getFormData(),
        ] );

        $this->client->curl()->multiExec() ;
        $content = json_decode( $this->client->curl()->getSingleContent( $resource ), true ) ;

        if ( $this->client->curl()->hasError( $resource ) ) {
            throw new \Exception('Error trying to get segment id ' . $this->translation->id_segment) ;
        }

        return $content['dqfId'] ;
    }

    /**
     * @param ISession $session
     * @param array    $params
     *
     * @return ChildProjectSegmentTranslationRequestStruct
     * @throws \Exception
     */
    private function createChildProjectSegmentTranslationRequestStruct() {

        /** @var \Segments_SegmentStruct $segment */
        $segment = ( new \Segments_SegmentDao() )->getById( $this->translation->id_segment );

        /** @var \Chunks_ChunkStruct $chunk */
        $chunk = \Chunks_ChunkDao::getByJobID( $this->translation->id_job )[ 0 ];

        /** @var DqfProjectMapStruct $dqfProject */
        $dqfProject = ( new DqfProjectMapDao() )->getByType( $chunk, $this->getProjectType() )[ 0 ];

        /** @var ExtendedTranslationStruct $translation */
        $translation = ( new TranslationVersionDao() )->getExtendedTranslationByFile(
                \Files_FileDao::getById( $segment->id_file ),
                $this->getLimitDate( $chunk ),
                $segment->id,
                $segment->id
        )[ $segment->id ];

        list( $segmentOriginId, $matchRate ) = $this->filterDqfSegmentOriginAndMatchRate( $chunk );

        $request                    = new ChildProjectSegmentTranslationRequestStruct();
        $request->sessionId         = $this->session->getSessionId();
        $request->fileId            = $this->getRemoteFileId( $this->session, $segment->id_file );
        $request->projectKey        = $dqfProject->dqf_project_uuid;
        $request->projectId         = $dqfProject->dqf_project_id;
        $request->targetLangCode    = $chunk->target;
        $request->apiKey            = \INIT::$DQF_API_KEY;
        $request->segmentId         = $this->getSegmentId( $this->session, $this->translation->id_segment );
        $request->mtEngineId        = 22; // MyMemory
        $request->mtEngineOtherName = '';
        $request->targetSegment     = $translation->translation_before;
        $request->editedSegment     = $translation->translation_after;
        $request->sourceSegment     = $segment->segment;
        $request->segmentOriginId   = $segmentOriginId;
        $request->matchRate         = $matchRate;
        $request->indexNo           =  1; // ????

        return $request;
    }

    /**
     * @param $session
     * @param $fileId
     *
     * @return mixed
     * @throws \Exception
     */
    private function getRemoteFileId( $session, $fileId ) {
        $file    = \Files_FileDao::getById( $fileId );
        $service = new FileIdMapping( $session, $file );

        return $service->getRemoteId();
    }

    /**
     * @param $session
     * @param $localSegmentId
     *
     * @return mixed
     * @throws \Exception
     */
    private function getSegmentId( $session, $localSegmentId ) {
        $segment = ( new \Segments_SegmentDao() )->getById( $localSegmentId );
        $service = new ChildProjectSegmentId( $session, $segment );

        return $service->getRemoteId();
    }

    /**
     * @return string
     */
    private function getProjectType() {
        return ( $this->translation->isReviewedStatus() ) ? DqfProjectMapDao::PROJECT_TYPE_REVISE : DqfProjectMapDao::PROJECT_TYPE_TRANSLATE;
    }

    /**
     * @param \Chunks_ChunkStruct $chunk
     *
     * @return array
     * @throws \Exception
     */
    private function filterDqfSegmentOriginAndMatchRate( \Chunks_ChunkStruct $chunk ) {
        $projectType = $chunk->getProject()->getMetadataValue( 'project_type' );

        if ( $projectType == 'MT' ) {
            $data = [
                    'originName' => 'MT',
                    'matchRate'  => 100,
            ];
        } else {
            $data = [
                    'originName' => 'HT',
                    'matchRate'  => null,
            ];
        }

        $object = (new SegmentOrigin())->getByName( $data[ 'originName' ] );

        return [ $object[ 'id' ], $data[ 'matchRate' ] ];
    }

    /**
     * @param \Chunks_ChunkStruct $chunk
     *
     * @return mixed
     * @throws \Exception
     */
    private function getLimitDate( \Chunks_ChunkStruct $chunk ) {
        // find date of completion event for inverse type
        $is_review = ( $this->getProjectType() == DqfProjectMapDao::PROJECT_TYPE_REVISE );
        $prevEvent = \Chunks_ChunkCompletionEventDao::lastCompletionRecord( $chunk, [ 'is_review' => !$is_review ] );

        if ( $prevEvent ) {
            return $prevEvent[ 'create_date' ];
        }

        return $chunk->getProject()->create_date;
    }
}