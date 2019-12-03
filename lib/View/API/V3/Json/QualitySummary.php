<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 25/10/19
 * Time: 18:45
 *
 */

namespace API\V3\Json;


use Chunks_ChunkStruct;
use Features\ReviewExtended\Model\QualityReportDao;
use Features\ReviewExtended\ReviewUtils;
use Jobs_JobStruct;
use LQA\ChunkReviewStruct;
use Projects_ProjectStruct;
use RevisionFactory;

class QualitySummary {

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk;
    /**
     * @var Projects_ProjectStruct
     */
    protected $project;

    public function __construct( Chunks_ChunkStruct $chunk, Projects_ProjectStruct $project ) {
        $this->chunk = $chunk;
        $this->project = $project;
    }

    /**
     * @param ChunkReviewStruct[]    $chunkReviewList
     *
     * @return array
     */
    public function render( $chunkReviewList ) {

        $result = [];
        $result[ 'quality_summary' ] = [];

        foreach( $chunkReviewList as $chunkReview ){
            $result[ 'quality_summary' ][] = $this->renderItem( $chunkReview );
        }

        return $result;

    }

    protected function renderItem( ChunkReviewStruct $chunkReview ) {

        list( $passFail, $reviseIssues, $quality_overall, $is_pass, $score, $total_issues_weight, $total_reviewed_words_count, $categories ) =
                self::revisionQualityVars( $this->chunk, $this->project, $chunkReview );

        $result = self::populateQualitySummarySection( $chunkReview->source_page,
                $this->chunk, $quality_overall, $reviseIssues, $score, $categories,
                $total_issues_weight, $total_reviewed_words_count, $passFail,
                $chunkReview->total_tte,
                $is_pass
        );

        return $result;

    }


    /**
     * @param $result
     * @param $source_page
     * @param $jStruct
     * @param $quality_overall
     * @param $reviseIssues
     * @param $score
     * @param $categories
     * @param $total_issues_weight
     * @param $total_reviewed_words_count
     * @param $passfail
     *
     * @param $total_tte
     * @param $is_pass
     *
     * @return mixed
     */
    public static function populateQualitySummarySection( $source_page, Jobs_JobStruct $jStruct, $quality_overall, $reviseIssues, $score, $categories,
                                                          $total_issues_weight, $total_reviewed_words_count, $passfail, $total_tte, $is_pass ) {

        $result = [
                'revision_number'            => ReviewUtils::sourcePageToRevisionNumber( $source_page ),
                'equivalent_class'           => $jStruct->getQualityInfo(),
                'is_pass'                    => $is_pass,
                'quality_overall'            => $quality_overall,
                'errors_count'               => (int)$jStruct->getErrorsCount(),
                'revise_issues'              => $reviseIssues,
                'score'                      => floatval( $score ),
                'categories'                 => $categories,
                'total_issues_weight'        => (float)$total_issues_weight,
                'total_reviewed_words_count' => (int)$total_reviewed_words_count,
                'passfail'                   => $passfail,
                'total_time_to_edit'         => (int)$total_tte
        ];

        return $result;

    }

    /**
     * @param Chunks_ChunkStruct     $jStruct
     * @param Projects_ProjectStruct $project
     * @param                        $chunkReview
     *
     * @return array
     * @internal param $reviseIssues
     */
    protected static function revisionQualityVars( Chunks_ChunkStruct $jStruct, Projects_ProjectStruct $project, $chunkReview ) {

        $reviseIssues = [];

        $qualityReportDao = new QualityReportDao();
        $qa_data          = $qualityReportDao->getReviseIssuesByChunk( $jStruct->id, $jStruct->password, $chunkReview->source_page );
        foreach ( $qa_data as $issue ) {
            if ( !isset( $reviseIssues[ $issue->id_category ] ) ) {
                $reviseIssues[ $issue->id_category ] = [
                        'name'   => $issue->issue_category_label,
                        'founds' => [
                                $issue->issue_severity => 1
                        ]
                ];
            } else {
                if ( !isset( $reviseIssues[ $issue->id_category ][ 'founds' ][ $issue->issue_severity ] ) ) {
                    $reviseIssues[ $issue->id_category ][ 'founds' ][ $issue->issue_severity ] = 1;
                } else {
                    $reviseIssues[ $issue->id_category ][ 'founds' ][ $issue->issue_severity ]++;
                }
            }
        }

        if ( @$chunkReview->is_pass === null ) {
            $quality_overall = null;
            $is_pass         = null;
        } elseif ( !empty( $chunkReview->is_pass ) ) {
            $quality_overall = 'excellent';
            $is_pass         = (bool)$chunkReview->is_pass;
        } else {
            $quality_overall = 'fail';
            $is_pass         = false;
        }

        $chunkReviewModel = RevisionFactory::initFromProject( $project )->getChunkReviewModel( $chunkReview );

        $score = number_format( $chunkReviewModel->getScore(), 2, ".", "" );

        $total_issues_weight        = $chunkReviewModel->getPenaltyPoints();
        $total_reviewed_words_count = $chunkReviewModel->getReviewedWordsCount();

        $model      = $project->getLqaModel();
        $categories = $model->getCategoriesAndSeverities();
        $passfail   = [ 'type' => $model->pass_type, 'options' => [ 'limit' => $chunkReviewModel->getQALimit() ] ];

        return [
                $passfail,
                $reviseIssues, $quality_overall, $is_pass, $score, $total_issues_weight, $total_reviewed_words_count, $categories
        ];

    }

}