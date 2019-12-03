<?php

class Translations_SegmentTranslationStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, ArrayAccess {

    public $id_segment;
    public $id_job;
    public $segment_hash;
    public $autopropagated_from;
    public $status;
    public $translation;
    public $translation_date;
    public $time_to_edit;
    public $match_type;
    public $context_hash;
    public $eq_word_count;
    public $standard_word_count;
    public $suggestions_array;
    public $suggestion;
    public $suggestion_match;
    public $suggestion_source;
    public $suggestion_position;
    public $mt_qe;
    public $tm_analysis_status;
    public $locked;
    public $warning;
    public $serialized_errors_list;
    public $version_number;


    public function isReviewedStatus() {
        return in_array( $this->status, Constants_TranslationStatus::$REVISION_STATUSES );
    }

    public function isICE() {
        return $this->match_type == Constants_SegmentTranslationsMatchType::ICE;
    }

    public function isPostReviewedStatus() {
        return in_array( $this->status, Constants_TranslationStatus::$POST_REVISION_STATUSES );
    }

    public function isRejected(){
        return $this->status == Constants_TranslationStatus::STATUS_REJECTED;
    }

    public function isTranslationStatus() {
        return !$this->isReviewedStatus();
    }

    /**
     * @return Jobs_JobStruct
     */
    public function getJob() {
        return $this->cachable( __FUNCTION__, $this->id_job, function ( $id_job ) {
            return Jobs_JobDao::getById( $id_job )[ 0 ];
        } );
    }

    /**
     * @return mixed
     */
    public function getChunk() {
        return $this->cachable( __FUNCTION__, $this->id_job, function ( $id_job ) {
            return Chunks_ChunkDao::getByJobID( $id_job )[ 0 ];
        } );
    }

    /**
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists( $offset ) {
        return property_exists( $this, $offset );
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
     */
    public function offsetGet( $offset ) {
        return $this->$offset;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet( $offset, $value ) {
        $this->$offset = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset( $offset ) {
        $this->$offset = null;
    }

}
