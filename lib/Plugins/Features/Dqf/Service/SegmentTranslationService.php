<?php

namespace Features\Dqf\Service;

use Features\Dqf\Service\Struct\Request\ChildProjectSegmentTranslationRequestStruct;
use Features\Dqf\Utils\SegmentTranslationTransformer;

class SegmentTranslationService extends AbstractService {


    /**
     * @var \Translations_SegmentTranslationStruct
     */
    private $translation;

    /**
     * @var SegmentTranslationTransformer
     */
    private $segmentTranslationHelper;

    /**
     * SegmentTranslationService constructor.
     *
     * @param ISession                               $session
     * @param \Translations_SegmentTranslationStruct $translation
     */
    public function __construct( ISession $session, \Translations_SegmentTranslationStruct $translation ) {
        parent::__construct( $session );

        $this->translation = $translation;
        $this->segmentTranslationHelper = new SegmentTranslationTransformer($session);
    }

    /**
     * For further documentation, please see:
     * https://dqf-api.stag.taus.net/#!/Project%2FChild%2FFile%2FTarget_Language%2FSegment/update_0
     *
     * @throws \Exception
     */
    public function process() {
        $request = $this->createChildProjectSegmentTranslationRequestStruct();
        $url     = "/project/child/%s/file/%s/targetLang/%s/sourceSegment/%s/translation/%s";

        $resource = $this->client->createResource( $url, 'put', [
                'headers'    => $this->session->filterHeaders( $request ),
                'pathParams' => $request->getPathParams(),
                'formData'   => $request->getFormData(),
        ] );

        $this->client->curl()->multiExec();
        $content = json_decode( $this->client->curl()->getSingleContent( $resource ), true );

        if ( $this->client->curl()->hasError( $resource ) and  $content[ 'status' ] !== 'OK') {
            throw new \Exception( 'Error trying to setup the translation segment id ' . $this->translation->id_segment );
        }

        // message => Segments successfully updated
        // status => OK

        return $content[ 'status' ];
    }

    /**
     * @param ISession $session
     * @param array    $params
     *
     * @return ChildProjectSegmentTranslationRequestStruct
     * @throws \Exception
     */
    private function createChildProjectSegmentTranslationRequestStruct() {

        $convertedForDqfArray = $this->segmentTranslationHelper->transform($this->translation);

        $request                    = new ChildProjectSegmentTranslationRequestStruct();
        $request->sessionId         = $convertedForDqfArray['sessionId'];
        $request->fileId            = $convertedForDqfArray['fileId'];
        $request->projectKey        = $convertedForDqfArray['projectKey'];
        $request->projectId         = $convertedForDqfArray['projectId'];
        $request->targetLangCode    = $convertedForDqfArray['targetLangCode'];
        $request->apiKey            = $convertedForDqfArray['apiKey'];
        $request->sourceSegmentId   = $convertedForDqfArray['sourceSegmentId'];
        $request->translationId     = $convertedForDqfArray['translationId'];
        $request->mtEngineId        = $convertedForDqfArray['mtEngineId'];
        $request->mtEngineOtherName = $convertedForDqfArray['mtEngineOtherName'];
        $request->targetSegment     = $convertedForDqfArray['targetSegment'];
        $request->editedSegment     = $convertedForDqfArray['editedSegment'];
        $request->sourceSegment     = $convertedForDqfArray['sourceSegment'];
        $request->segmentOriginId   = $convertedForDqfArray['segmentOriginId'];
        $request->matchRate         = $convertedForDqfArray['matchRate'];
        $request->indexNo           = $convertedForDqfArray['indexNo'];
        $request->time              = $convertedForDqfArray['time'];

        return $request;
    }
}