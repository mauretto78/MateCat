<?php

namespace Features;

use Exceptions\ControllerReturnException;
use Exceptions\ValidationError;
use Features\TranslationVersions\Model\BatchEventCreator;
use Features\TranslationVersions\Model\SegmentTranslationEventModel;
use Jobs_JobDao;
use Projects_ProjectDao;

class TranslationVersions extends BaseFeature {

    const FEATURE_CODE = 'translation_versions';

    public function preSetTranslationCommitted( $params ) {
        // evaluate if the record is to be created, either the
        // status changed or the translation changed
        $user = $params[ 'user' ];

        /** @var \Translations_SegmentTranslationStruct $translation */
        $translation = $params[ 'translation' ];

        /** @var \Translations_SegmentTranslationStruct $old_translation */
        $old_translation  = $params[ 'old_translation' ];

        $source_page_code = $params[ 'source_page_code' ];

        /** @var \Chunks_ChunkStruct $chunk */
        $chunk = $params[ 'chunk' ];

        /** @var \FeatureSet $features */
        $features = $params[ 'features' ];

        /** @var \Projects_ProjectStruct $project */
        $project = $params[ 'project' ];

        $sourceEvent = new SegmentTranslationEventModel( $old_translation, $translation, $user, $source_page_code );

        $batchEventCreator = new BatchEventCreator( $chunk );
        $batchEventCreator->setFeatureSet( $features );
        $batchEventCreator->addEventModel( $sourceEvent );
        $batchEventCreator->setProject( $project );

        // If propagated segments exist, start cycle here
        if ( isset( $params[ 'propagation' ][ 'segments_for_propagation' ] ) and false === empty($params[ 'propagation' ][ 'segments_for_propagation' ] ) ) {
            foreach ( $params[ 'propagation' ][ 'segments_for_propagation' ] as $segmentTranslationBeforeChange ) {

                /** @var \Translations_SegmentTranslationStruct $propagatedSegmentAfterChange */
                $propagatedSegmentAfterChange                      = clone $segmentTranslationBeforeChange;
                $propagatedSegmentAfterChange->translation         = $translation->translation;
                $propagatedSegmentAfterChange->status              = $translation->status;
                $propagatedSegmentAfterChange->autopropagated_from = $translation->id_segment;
                $propagatedSegmentAfterChange->time_to_edit        = 0;

                $propagatedEvent = new SegmentTranslationEventModel(
                        $segmentTranslationBeforeChange,
                        $propagatedSegmentAfterChange,
                        $user,
                        $source_page_code
                );

                $propagatedEvent->setPropagationSource( false );
                $batchEventCreator->addEventModel( $propagatedEvent );
            }
        }

        try {
            $batchEventCreator->save();
            // $event->setChunkReviewsList( $chunkReviews ) ;
            ( new Jobs_JobDao() )->destroyCacheByProjectId( $chunk->id_project );
            Projects_ProjectDao::destroyCacheById( $chunk->id_project );
        } catch ( ValidationError $e ) {
            $params[ 'controller_result' ][ 'errors' ] [] = [
                    'code'    => -2000,
                    'message' => $e->getMessage()
            ];
            throw new ControllerReturnException( $e->getMessage(), -2000 );
        }
    }

    public function filter_get_segments_optional_fields() {
        $options[ 'optional_fields' ] = [ 'st.version_number' ];

        return $options;
    }

}
