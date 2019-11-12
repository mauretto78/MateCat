<?php

namespace Features\Dqf\Transformer;

use Chunks_ChunkStruct;
use Features\Dqf\Model\CachedAttributes\SegmentOrigin;
use Features\Dqf\Model\DqfProjectMapDao;
use Features\Dqf\Model\DqfSegmentsDao;
use Features\Dqf\Model\ExtendedTranslationStruct;
use Features\Dqf\Model\TranslationVersionDao;
use Files_FileDao;
use Matecat\Dqf\Cache\BasicAttributes;
use Translations_SegmentTranslationStruct;

class SegmentTranslationTransformer implements TranslationTransformerInterface {

    /**
     * ----------------------------------------------------------
     * Transform a normal segment translation struct into a array structure ready for DQF
     * ----------------------------------------------------------
     *
     * The segment translation single update is allowed only if:
     * - the segment translation is in TRANSLATION status
     * - these is already a DQF project including the segment translation
     *
     * @param Translations_SegmentTranslationStruct $translation
     *
     * @return array
     * @throws \Exception
     */
    public function transform( Translations_SegmentTranslationStruct $translation ) {

        $segment             = ( new \Segments_SegmentDao() )->getById( $translation->id_segment );
        $chunk               = $translation->getChunk();
        $extendedTranslation = $this->getExtendedTranslationForASegment( $segment, $this->getLimitDate( $translation, $chunk ) );

        // get $segmentOriginId and $matchRate
        list( $segmentOriginId, $matchRate ) = $this->filterDqfSegmentOriginAndMatchRate( $extendedTranslation, $chunk );

        // get the DQF remote segmentId and translationId
        $dqfSegment = ( new DqfSegmentsDao() )->getByIdSegment( $segment->id );

        $transformedArray                        = [];
        $transformedArray[ 'targetLang' ]        = $chunk->target;
        $transformedArray[ 'sourceSegmentId' ]   = $dqfSegment->dqf_segment_id;
        $transformedArray[ 'translationId' ]     = $dqfSegment->dqf_translation_id;
        $transformedArray[ 'mtEngineId' ]        = 22; // MyMemory
        $transformedArray[ 'mtEngineOtherName' ] = '';
        $transformedArray[ 'targetSegment' ]     = ($extendedTranslation->translation_before) ? $extendedTranslation->translation_before : ''; // CANNOT BE NULL
        $transformedArray[ 'editedSegment' ]     = $extendedTranslation->translation_after;
        $transformedArray[ 'sourceSegment' ]     = $segment->segment;
        $transformedArray[ 'segmentOriginId' ]   = $segmentOriginId;
        $transformedArray[ 'matchRate' ]         = $matchRate;
        $transformedArray[ 'time' ]              = $extendedTranslation->time;
        $transformedArray[ 'indexNo' ]           = $this->getSegmentIndexInJob( $chunk, $segment->id );

        return $transformedArray;
    }

    /**
     * @param $segment
     * @param $limitDate
     *
     * @return ExtendedTranslationStruct
     */
    private function getExtendedTranslationForASegment( $segment, $limitDate ) {
        return ( new TranslationVersionDao() )->getExtendedTranslationByFile(
                Files_FileDao::getById( $segment->id_file ),
                $limitDate,
                $segment->id,
                $segment->id
        )[ $segment->id ];
    }

    /**
     * @param Translations_SegmentTranslationStruct $translation
     *
     * @return string
     */
    private function getProjectType( Translations_SegmentTranslationStruct $translation ) {
        return ( $translation->isReviewedStatus() ) ? DqfProjectMapDao::PROJECT_TYPE_REVISE : DqfProjectMapDao::PROJECT_TYPE_TRANSLATE;
    }

    /**
     * Find date of completion event for inverse type
     *
     * @param Translations_SegmentTranslationStruct $translation
     * @param Chunks_ChunkStruct                    $chunk
     *
     * @return mixed
     * @throws \Exception
     */
    private function getLimitDate( Translations_SegmentTranslationStruct $translation, Chunks_ChunkStruct $chunk ) {
        $is_review = ( $this->getProjectType( $translation ) == DqfProjectMapDao::PROJECT_TYPE_REVISE );
        $prevEvent = \Chunks_ChunkCompletionEventDao::lastCompletionRecord( $chunk, [ 'is_review' => !$is_review ] );

        return ( $prevEvent ) ? $prevEvent[ 'create_date' ] : $chunk->getProject()->create_date;
    }

    /**
     * @param ExtendedTranslationStruct $translation
     * @param Chunks_ChunkStruct        $chunk
     *
     * @return array
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\NotFoundException
     * @throws \Exceptions\ValidationError
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */
    private function filterDqfSegmentOriginAndMatchRate( ExtendedTranslationStruct $translation, Chunks_ChunkStruct $chunk ) {

        $data = [
                'originName' => $translation->segment_origin,
                'matchRate'  => $translation->suggestion_match
        ];

        $data = $chunk->getProject()->getFeatures()->filter(
                'filterDqfSegmentOriginAndMatchRate', $data, $translation, $chunk
        );

        $object = BasicAttributes::getFromName(BasicAttributes::SEGMENT_ORIGIN,  $data[ 'originName' ]);

        return [
                $object->id,
                $data[ 'matchRate' ]
        ];
    }

    /**
     * @param \Chunks_ChunkStruct $chunk
     * @param int                 $idTranslation
     *
     * @return int|string
     */
    private function getSegmentIndexInJob( Chunks_ChunkStruct $chunk, $idTranslation ) {
        $segments = $chunk->getSegments();

        /** @var \Segments_SegmentStruct $segment */
        foreach ( $segments as $index => $segment ) {
            if ( $idTranslation === $segment->id ) {
                return $index + 1;
            }
        }
    }
}