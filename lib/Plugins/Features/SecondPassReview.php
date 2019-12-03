<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/03/2019
 * Time: 12:26
 */

namespace Features;

use BasicFeatureStruct;
use catController;
use Chunks_ChunkStruct;
use Exceptions\NotFoundException;
use Features;
use Features\ReviewExtended\Controller\API\Json\ProjectUrls;
use Features\ReviewExtended\ReviewUtils as ReviewUtils;
use Features\SecondPassReview\Model\ChunkReviewModel;
use Features\TranslationVersions\Model\SegmentTranslationEventDao;
use Klein\Klein;
use LQA\ChunkReviewDao;
use LQA\ChunkReviewStruct;
use Projects_ProjectDao;

class SecondPassReview extends BaseFeature {
    const FEATURE_CODE = 'second_pass_review';

    protected static $dependencies = [
            Features::REVIEW_EXTENDED
    ];

    public static function projectUrls( $formatted ) {
        $projectUrlsDecorator = new ProjectUrls( $formatted->getData() );

        return $projectUrlsDecorator;
    }

    public static function loadRoutes( Klein $klein ) {
        route( '/project/[:id_project]/[:password]/reviews', 'POST',
                'Features\SecondPassReview\Controller\ReviewsController', 'createReview' );
    }

    /**
     * @param ChunkReviewStruct       $chunkReview
     * @param \Projects_ProjectStruct $projectStruct
     *
     * @throws \Exception
     */
    public function chunkReviewRecordCreated( ChunkReviewStruct $chunkReview, \Projects_ProjectStruct $projectStruct ) {
        // This is needed to properly populate advancement wc for ICE matches
        ( new ChunkReviewModel( $chunkReview ) )->recountAndUpdatePassFailResult( $projectStruct );
    }

    /**
     * This callback is necessary so that ICE matches are included in advancement_wc.
     *
     * @param $project_id
     * @param $_analyzed_report
     *
     * @throws \Exception
     */
    public function afterTMAnalysisCloseProject( $project_id, $_analyzed_report ) {
        $project      = Projects_ProjectDao::findById( $project_id, 300 );
        $chunkReviews = ( new ChunkReviewDao() )->findChunkReviewsForList( $project->getChunks() );
        foreach ( $chunkReviews as $chunkReview ) {
            $model = new ChunkReviewModel( $chunkReview );
            $model->recountAndUpdatePassFailResult( $project );
        }
    }

    public function catControllerChunkFound( catController $controller ) {
        if ( !$controller->isRevision() ) {
            return;
        }

        if ( $controller->getRevisionNumber() > 1 ) {
            $chunk_review = ( new ChunkReviewDao() )->findByJobIdPasswordAndSourcePage(
                    $controller->getChunk()->id,
                    $controller->getChunk()->password,
                    ReviewUtils::revisionNumberToSourcePage( $controller->getRevisionNumber() )
            );

            if ( empty( $chunk_review ) ) {
                throw new NotFoundException( "This revision did not start yet: " . $controller->getRevisionNumber() );
            }
        }
    }

    public function filterSourcePage( $sourcePage ) {
        $_from_url    = parse_url( @$_SERVER[ 'HTTP_REFERER' ] );
        $chunk_review = $matches = null;
        preg_match( '/revise([2-9])?\//s', $_from_url[ 'path' ], $matches );
        if ( count( $matches ) > 1 ) {
            $sourcePage = ReviewUtils::revisionNumberToSourcePage( $matches[ 1 ] );
        }

        return $sourcePage;
    }

    /**
     * @param $inputStats
     * @param $options
     *
     * @return array
     * @throws \Exception
     */
    public function filterStatsResponse( $inputStats, $options ) {
        /** @var Chunks_ChunkStruct $chunk */
        $chunk        = $options[ 'chunk' ];
        $chunkReviews = ( new ChunkReviewDao() )->findChunkReviews( $chunk );

        return ReviewUtils::formatStats( $inputStats, $chunkReviews );
    }

    public function filterGetSegmentsResult( $data, Chunks_ChunkStruct $chunk ) {
        reset( $data[ 'files' ] );

        $firstFile = current( $data[ 'files' ] );
        $lastFile  = end( $data[ 'files' ] );
        $firstSid  = $firstFile[ 'segments' ][ 0 ][ 'sid' ];

        $lastSegment = end( $lastFile[ 'segments' ] );
        $lastSid     = $lastSegment[ 'sid' ];

        $segment_translation_events = ( new SegmentTranslationEventDao() )->getLatestEventsInSegmentInterval(
                $chunk->id, $firstSid, $lastSid );

        $by_id_segment = [];
        foreach ( $segment_translation_events as $record ) {
            $by_id_segment[ $record->id_segment ] = $record;
        }

        foreach ( $data[ 'files' ] as $file => $content ) {
            foreach ( $content[ 'segments' ] as $key => $segment ) {
                $data [ 'files' ] [ $file ] [ 'segments' ] [ $key ] [ 'revision_number' ] = ReviewUtils::sourcePageToRevisionNumber(
                        $by_id_segment[ $segment[ 'sid' ] ]->source_page
                );
            }
        }

        return $data;
    }

    /**
     * @param $projectFeatures
     * @param $controller \NewController|\createProjectController
     *
     * @return mixed
     * @throws \Exception
     */
    public function filterCreateProjectFeatures( $projectFeatures, $controller ) {
        $projectFeatures[ self::FEATURE_CODE ] = new BasicFeatureStruct( [ 'feature_code' => self::FEATURE_CODE ] );
        $projectFeatures                       = $controller->getFeatureSet()->filter( 'filterOverrideReviewExtended', $projectFeatures, $controller );

        return $projectFeatures;
    }

}