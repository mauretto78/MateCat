<?php

namespace Features\Dqf\Service\Struct\Request;

use Features\Dqf\Service\Struct\BaseRequestStruct;

class ChildProjectSegmentTranslationRequestStruct extends BaseRequestStruct {

    // path
    public $projectId;
    public $fileId;
    public $targetLangCode;
    public $sourceSegmentId;
    public $translationId;

    // header
    public $sessionId;
    public $apiKey;
    public $projectKey;

    //form-data
    public $sourceSegment;
    public $indexNo;
    public $targetSegment;
    public $editedSegment;
    public $time; // no-required
    public $segmentOriginId;
    public $matchRate; // no-required
    public $mtEngineId; // no-required
    public $mtEngineOtherName; // no-required
    public $mtEngineVersion; // no-required
    public $segmentOriginDetail; // no-required
    public $clientId; // no-required

    public function getHeaders() {
        return $this->toArray( [
                'sessionId',
                'apiKey',
                'projectKey'
        ] );
    }

    /**
     * @return array
     */
    public function getPathParams() {
        return [
                'projectId'      => $this->projectId,
                'fileId'         => $this->fileId,
                'targetLangCode' => $this->targetLangCode,
                'segmentId'      => $this->sourceSegmentId,
                'translationId'  => $this->translationId,
        ];
    }

    public function getFormData()
    {
        return [
            'sourceSegment' => $this->sourceSegment,
            'indexNo' => $this->indexNo,
            'targetSegment' => $this->targetSegment,
            'editedSegment' => $this->editedSegment,
            'time' => $this->time,
            'segmentOriginId' => $this->segmentOriginId,
            'matchRate' => $this->matchRate,
            'mtEngineId' => $this->mtEngineId,
            'mtEngineOtherName' => $this->mtEngineOtherName,
            'mtEngineVersion' => $this->mtEngineVersion,
            'segmentOriginDetail' => $this->segmentOriginDetail,
            'clientId' => $this->clientId,
        ];
    }
}