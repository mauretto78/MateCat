<?php

namespace Features\Dqf\CommandHandler;

use Constants_TranslationStatus;
use Features\Dqf\Command\CreateReviewCommand;
use Features\Dqf\Factory\ClientFactory;
use Features\Dqf\Model\DqfFileMapDao;
use Features\Dqf\Model\DqfProjectMapDao;
use Features\Dqf\Model\DqfProjectMapStruct;
use Features\Dqf\Model\DqfReviewsDao;
use Features\Dqf\Model\DqfSegmentsDao;
use Features\Dqf\Transformer\ReviewTransformer;
use Features\Dqf\Transformer\SegmentTransformer;
use Features\ReviewExtended\Model\QualityReportDao;
use Matecat\Dqf\Constants;
use Matecat\Dqf\Model\Entity\ChildProject;
use Matecat\Dqf\Model\Entity\File;
use Matecat\Dqf\Model\Entity\ReviewedSegment;
use Matecat\Dqf\Model\ValueObject\ReviewBatch;
use Matecat\Dqf\Model\ValueObject\RevisionCorrection;
use Matecat\Dqf\Model\ValueObject\RevisionCorrectionItem;
use Matecat\Dqf\Model\ValueObject\RevisionError;
use Matecat\Dqf\Repository\Api\ChildProjectRepository;
use Matecat\Dqf\Repository\Api\FilesRepository;
use Matecat\Dqf\Repository\Api\MasterProjectRepository;
use Matecat\Dqf\Repository\Api\ReviewRepository;
use Matecat\Dqf\Repository\Api\TranslationRepository;
use Matecat\Dqf\Utils\RevisionCorrectionAnalyser;

class CreateReviewCommandHandler extends AbstractCommandHanlder {

    /**
     * @var CreateReviewCommand
     */
    private $command;

    /**
     * @var \Chunks_ChunkStruct
     */
    private $chunk;

    /**
     * @var MasterProjectRepository
     */
    private $masterProjectRepository;

    /**
     * @var ChildProjectRepository
     */
    private $childProjectRepository;

    /**
     * @var TranslationRepository
     */
    private $translationRepository;

    /**
     * @var DqfProjectMapStruct
     */
    private $dqfProjectMapStruct;

    /**
     * @var FilesRepository
     */
    private $filesRepository;

    /**
     * @var ReviewRepository
     */
    private $reviewRepository;

    /**
     * @param CreateReviewCommand $command
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle( $command ) {
        if ( false === $command instanceof CreateReviewCommand ) {
            throw new \Exception( 'Provided command is not a valid instance of UpdateSegmentTranslationCommand class' );
        }

        $this->setUp( $command );
        $this->submitBatch();
    }

    /**
     * @param CreateReviewCommand $command
     *
     * @throws \Exception
     */
    private function setUp( CreateReviewCommand $command ) {
        $this->command = $command;
        $this->chunk   = \Chunks_ChunkDao::getByIdAndPassword( $command->job_id, $command->job_password );

        // REFACTOR THIS!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        $uid = $this->chunk->getProject()->getOriginalOwner()->getUid();
        // THIS MUST BE CHANGED

        $sessionId    = $this->getSessionId( $uid );
        $genericEmail = $this->getGenericEmail( $uid );

        // get the last child project on DB associated with the chunk
        $dqfProjectMapDao = new DqfProjectMapDao();

        $projects                  = $dqfProjectMapDao->getByType( $this->chunk, Constants::PROJECT_TYPE_REVIEW );
        $this->dqfProjectMapStruct = end( $projects );

        // set repos
        $this->masterProjectRepository = new MasterProjectRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->childProjectRepository  = new ChildProjectRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->translationRepository   = new TranslationRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->filesRepository         = new FilesRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->reviewRepository        = new ReviewRepository( ClientFactory::create(), $sessionId, $genericEmail );
    }

    /**
     * @throws \Exception
     */
    private function submitBatch() {
        $parentProject      = $this->getDqfParentProject();
        $childReview        = $this->getDqfChildProject( $parentProject );
        $dqfFileMapDao      = new DqfFileMapDao();
        $segmentTransformer = new SegmentTransformer();
        $reviewTransformer  = new ReviewTransformer();
        $dqfSegmentsDao     = new DqfSegmentsDao();
        $qualityReportDao   = new QualityReportDao();

        // loop all chunk files
        foreach ( $this->chunk->getFiles() as $file ) {

            // get the DqfId of file
            $dqfFileMapStruct = $dqfFileMapDao->findOne( $file->id, $this->command->job_id );
            $dqfFile          = $this->getDqfFile( $childReview, $dqfFileMapStruct->dqf_id );

            // get all 'APPROVED' segmentTranslation by files
            $approvedSegmentTranslations = ( new \Translations_SegmentTranslationDao() )->getByFileJobIdAndStatus( $file->id, $this->chunk->id, Constants_TranslationStatus::STATUS_APPROVED );
            foreach ( $approvedSegmentTranslations as $translation ) {

                $segmentId = $translation->id_segment;
                $issues    = $qualityReportDao->getReviseIssuesBySegmentTranslation( $segmentId );

                if ( count( $issues ) > 0 ) {

                    // get the transformed translation
                    $transformedTranslation = $segmentTransformer->transform( $translation );

                    // get dqf id of segment and translation
                    $dqfSegmentsStruct = $dqfSegmentsDao->getByIdSegmentAndDqfProjectId( (int)$segmentId, $parentProject->getDqfId() );

                    // get the TranslatedSegment from DQF
                    $translatedSegment = $this->translationRepository->getTranslatedSegment(
                            $parentProject,
                            (int)$dqfFileMapStruct->dqf_id, $this->chunk->target,
                            (int)$dqfSegmentsStruct->dqf_segment_id,
                            (int)$dqfSegmentsStruct->dqf_translation_id
                    );

                    // init ReviewBatch
                    $batchId     = \Utils::createToken();
                    $reviewBatch = new ReviewBatch( $childReview, $dqfFile, $this->chunk->target, $translatedSegment, $batchId );

                    // init the ReviewedSegment
                    $reviewedSegmentClientId = \Utils::createToken();
                    $reviewedSegment         = new ReviewedSegment( 'this is a comment' ); // @TODO UPDATE COMMENT
                    $reviewedSegment->setClientId( $reviewedSegmentClientId );

                    // set the correction
                    $correction  = new RevisionCorrection( $transformedTranslation[ 'editedSegment' ], $transformedTranslation[ 'time' ] );
                    $corrections = RevisionCorrectionAnalyser::analyse( $transformedTranslation[ 'targetSegment' ], $transformedTranslation[ 'editedSegment' ] );

                    foreach ( $corrections as $key => $value ) {
                        $correction->addItem( new RevisionCorrectionItem( $key, $value ) );
                    }

                    $reviewedSegment->setCorrection( $correction );

                    // set errors
                    // by looping all the issues for the segmentTranslation on Matecat
                    foreach ( $issues as $issue ) {
                        $transformedIssue = $reviewTransformer->transform( $issue );

                        $reviewedSegment->addError( new RevisionError(
                                $transformedIssue[ 'errorCategoryId' ],
                                $transformedIssue[ 'severityId' ],
                                $transformedIssue[ 'charPosStart' ],
                                $transformedIssue[ 'charPosEnd' ],
                                $transformedIssue[ 'isRepeated' ]
                        ) );
                    }

                    $reviewBatch->addReviewedSegment( $reviewedSegment );

                    /** @var ReviewBatch $savedReviewBatch */
                    $savedReviewBatch = $this->reviewRepository->save( $reviewBatch );

                    $this->writeReviews($savedReviewBatch);
                }
            }
        }
    }

    /**
     * @return ChildProject
     */
    private function getDqfParentProject() {
        $dqfId   = (int)$this->dqfProjectMapStruct->dqf_parent_id;
        $dqfUuid = $this->dqfProjectMapStruct->dqf_parent_uuid;

        return $this->childProjectRepository->get( $dqfId, $dqfUuid );
    }

    /**
     * @param ChildProject $parentProject
     *
     * @return mixed
     */
    private function getDqfChildProject( ChildProject $parentProject ) {

        // get DqfId and DqfUuid
        $dqfId   = (int)$this->dqfProjectMapStruct->dqf_project_id;
        $dqfUuid = $this->dqfProjectMapStruct->dqf_project_uuid;

        $childProject = $this->childProjectRepository->get( $dqfId, $dqfUuid );
        $childProject->setParentProject( $parentProject );

        return $childProject;
    }

    /**
     * @param ChildProject $childProject
     * @param int          $dqfFileMapStructId
     *
     * @return File
     */
    private function getDqfFile( ChildProject $childProject, $dqfFileMapStructId ) {
        return $this->filesRepository->getByIdAndChildProject( $childProject->getDqfId(), $childProject->getDqfUuid(), (int)$dqfFileMapStructId );
    }

    /**
     * @param ReviewBatch $savedReviewBatch
     *
     * @throws \Exception
     */
    private function writeReviews(ReviewBatch $savedReviewBatch) {
        $values = [];

        $ppid = $savedReviewBatch->getChildProject()->getDqfId();
        $tid = $savedReviewBatch->getTranslation()->getDqfId();

        foreach ($savedReviewBatch->getReviewedSegments() as $reviewedSegment){
            $values[] = [
                    $tid,
                    $reviewedSegment->getDqfId(),
                    $ppid
            ];
        }


        $dqfReviewsDao = new DqfReviewsDao();
        $dqfReviewsDao->insertOrUpdateInATransaction($values);
    }
}