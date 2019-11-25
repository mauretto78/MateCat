<?php

namespace Features\Dqf\CommandHandler;

use Features\Dqf\Transformer\TransformerInterface;
use Matecat\Dqf\Model\Entity\TranslatedSegment;

abstract class AbstractTranslationCommandHandler extends AbstractCommandHandler {

    /**
     * @var TransformerInterface
     */
    protected $transformer;

    /**
     * @param \DataAccess_AbstractDaoObjectStruct $segmentTranslation
     *
     * @return TranslatedSegment
     */
    protected function getTranslatedSegmentFromTransformer( \DataAccess_AbstractDaoObjectStruct $segmentTranslation ) {
        $transformedTranslation = $this->transformer->transform( $segmentTranslation );

        $mtEngine        = (int)$transformedTranslation[ 'mtEngineId' ];
        $segmentOriginId = (int)$transformedTranslation[ 'segmentOriginId' ];
        $targetLang      = $transformedTranslation[ 'targetLang' ];
        $targetSegment   = $transformedTranslation[ 'targetSegment' ];
        $editedSegment   = $transformedTranslation[ 'editedSegment' ];
        $sourceSegmentId = (int)$transformedTranslation[ 'sourceSegmentId' ];
        $indexNo         = (int)$transformedTranslation[ 'indexNo' ];
        $matchRate       = $transformedTranslation[ 'matchRate' ];
        $time            = (int)$transformedTranslation[ 'time' ];

        $segmentTranslation = new TranslatedSegment(
                $mtEngine,
                $segmentOriginId,
                $targetLang,
                $sourceSegmentId,
                $targetSegment,
                $editedSegment,
                $indexNo
        );
        $segmentTranslation->setMatchRate( $matchRate );
        $segmentTranslation->setTime( $time );

        return $segmentTranslation;
    }
}