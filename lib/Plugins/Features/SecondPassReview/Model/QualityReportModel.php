<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/05/2019
 * Time: 10:42
 */

namespace Features\SecondPassReview\Model;


use Features\ReviewExtended\Model\ChunkReviewDao;
use Features\ReviewExtended\ReviewUtils;
use RevisionFactory;

class QualityReportModel extends \Features\ReviewExtended\Model\QualityReportModel {

    protected function _attachReviewsData() {
        $chunk_reviews = ( new ChunkReviewDao() )->findChunkReviews( $this->chunk );

        $this->quality_report_structure[ 'chunk' ][ 'reviews' ] = [];
        foreach ( $chunk_reviews as $chunk_review ) {

            $chunkReviewModel = RevisionFactory::initFromProject( $this->getProject() )->getChunkReviewModel( $chunk_review );

            $this->quality_report_structure[ 'chunk' ][ 'reviews' ][] = [
                    'revision_number' => ReviewUtils::sourcePageToRevisionNumber( $chunk_review->source_page ),
                    'is_pass'         => !!$chunk_review->is_pass,
                    'score'           => $chunkReviewModel->getScore(),
                    'reviewer_name'   => $this->getReviewerName()
            ];
        }
    }
}