<?php

namespace Features\Dqf\CommandHandler;

use Features\Dqf\Command\SubmitRevisionCommand;
use Features\Dqf\Factory\ClientFactory;
use Features\Dqf\Model\DqfFileMapDao;
use Features\Dqf\Model\DqfProjectMapDao;
use Features\Dqf\Model\DqfProjectMapStruct;
use Features\Dqf\Model\DqfReviewsDao;
use Features\Dqf\Model\DqfSegmentsDao;
use Features\Dqf\Transformer\RevisionTransformer;
use Features\Dqf\Transformer\SegmentTransformer;
use LQA\EntryDao;
use LQA\EntryStruct;
use Matecat\Dqf\Constants;
use Matecat\Dqf\Model\Entity\ChildProject;
use Matecat\Dqf\Model\Entity\File;
use Matecat\Dqf\Model\Entity\Revision;
use Matecat\Dqf\Model\ValueObject\ReviewBatch;
use Matecat\Dqf\Model\ValueObject\RevisionCorrection;
use Matecat\Dqf\Model\ValueObject\RevisionCorrectionItem;
use Matecat\Dqf\Model\ValueObject\RevisionError;
use Matecat\Dqf\Repository\Api\ChildProjectRepository;
use Matecat\Dqf\Repository\Api\FilesRepository;
use Matecat\Dqf\Repository\Api\MasterProjectRepository;
use Matecat\Dqf\Repository\Api\ReviewRepository;
use Matecat\Dqf\Repository\Api\TranslationRepository;
use Matecat\Dqf\Utils\Analysers\RevisionCorrectionAnalyser;

class SubmitRevisionCommandHandler extends AbstractCommandHandler {

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
     * @var \Translations_SegmentTranslationStruct
     */
    private $translation;

    /**
     * @param SubmitRevisionCommand $command
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle( $command ) {
        if ( false === $command instanceof SubmitRevisionCommand ) {
            throw new \InvalidArgumentException( 'Provided command is not a valid instance of '. SubmitRevisionCommand::class .' class' );
        }

        $this->setUp( $command );
        $this->submitBatch();
    }

    /**
     * @param SubmitRevisionCommand $command
     *
     * @throws \Exception
     */
    private function setUp( SubmitRevisionCommand $command ) {
        $this->command     = $command;
        $this->chunk       = \Chunks_ChunkDao::getByIdAndPassword( $command->job_id, $command->job_password );

        if(null === $this->chunk){
            throw new \InvalidArgumentException('Chunk with id ' . $command->job_id . ' and password ' . $command->job_password . ' does not exist.');
        }

        $this->translation = \Translations_SegmentTranslationDao::findBySegmentAndJob( $command->id_segment, $command->job_id );

        $dqfReference    = $this->getDqfReferenceEmailAndUserId();
        $this->userEmail = $dqfReference['email'];
        $this->userId    = $dqfReference['uid'];

        $sessionId    = $this->getSessionId( $this->userEmail, $this->userId );
        $genericEmail = (null !== $this->userId) ? $this->getGenericEmail( $this->userId ) : $this->userEmail;

        // get the last child project on DB associated with the chunk
        $projects                  = (new DqfProjectMapDao())->getChildByChunkAndType( $this->chunk, Constants::PROJECT_TYPE_REVIEW );
        $this->dqfProjectMapStruct = end($projects);

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
        $childReview        = $this->getDqfChildProject();
        $dqfFileMapDao      = new DqfFileMapDao();
        $segmentTransformer = new SegmentTransformer();
        $reviewTransformer  = new RevisionTransformer();
        $dqfSegmentsDao     = new DqfSegmentsDao();

        $segment = ( new \Segments_SegmentDao )->getById( $this->command->id_segment );

        // get the DqfId of file
        $dqfFileMapStruct = $dqfFileMapDao->findOne( $segment->id_file, $this->command->job_id );
        $dqfFile          = $this->getDqfFile( $childReview, $dqfFileMapStruct->dqf_id );

        // get dqf id of segment and translation
        $dqfSegmentsStruct = $dqfSegmentsDao->getByIdSegmentAndDqfProjectId( (int)$this->command->id_segment, (int)$this->dqfProjectMapStruct->dqf_parent_id );

        // get the TranslatedSegment from DQF
        $translatedSegment = $this->translationRepository->getTranslatedSegment(
                (int)$this->dqfProjectMapStruct->dqf_parent_id,
                $this->dqfProjectMapStruct->dqf_parent_uuid,
                (int)$dqfFileMapStruct->dqf_id,
                $this->chunk->target,
                (int)$dqfSegmentsStruct->dqf_segment_id,
                (int)$dqfSegmentsStruct->dqf_translation_id
        );

        // init ReviewBatch
        $batchId     = \Utils::createToken();
        $reviewBatch = new ReviewBatch( $childReview, $dqfFile, $this->chunk->target, $translatedSegment, $batchId );

        // get all issues for the segment
        $issues = ( new EntryDao() )->getByJobIdSourcePageAndIdSegment( $this->command->job_id, $this->command->source_page, $this->command->id_segment );

        foreach ( $issues as $issue ) {

            $transformedTranslation = $segmentTransformer->transform( $this->translation );

            // init the ReviewedSegment
            $revisionSegmentClientId = \Utils::createToken();
            $revision                = new Revision( $issue->comment );
            $revision->setClientId( $revisionSegmentClientId );

            // set the correction
            $correction  = new RevisionCorrection( $transformedTranslation[ 'editedSegment' ], $transformedTranslation[ 'time' ] );
            $corrections = RevisionCorrectionAnalyser::analyse( $transformedTranslation[ 'targetSegment' ], $transformedTranslation[ 'editedSegment' ] );

            foreach ( $corrections as $key => $value ) {
                $correction->addItem( new RevisionCorrectionItem( $key, $value ) );
            }

            $revision->setCorrection( $correction );

            // set errors
            $transformedIssue = $reviewTransformer->transform( $issue );

            $revision->addError( new RevisionError(
                    $transformedIssue[ 'errorCategoryId' ],
                    $transformedIssue[ 'severityId' ],
                    $transformedIssue[ 'charPosStart' ],
                    $transformedIssue[ 'charPosEnd' ],
                    $transformedIssue[ 'isRepeated' ]
            ) );

            $reviewBatch->addRevision( $revision );
        }

        /** @var ReviewBatch $savedReviewBatch */
        $savedReviewBatch = $this->reviewRepository->save( $reviewBatch );

        $this->writeReviews( $savedReviewBatch );
    }

    /**
     * @param ChildProject $parentProject
     *
     * @return mixed
     */
    private function getDqfChildProject() {

        // get DqfId and DqfUuid
        $dqfId   = (int)$this->dqfProjectMapStruct->dqf_project_id;
        $dqfUuid = $this->dqfProjectMapStruct->dqf_project_uuid;

        $childProject = $this->childProjectRepository->get( $dqfId, $dqfUuid );
        $childProject->setParentProjectUuid( $this->dqfProjectMapStruct->dqf_parent_uuid );

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
     * @param EntryStruct $issue
     *
     * @return \Translations_SegmentTranslationStruct|\Translations_TranslationVersionStruct|null
     */
    private function getTranslation( EntryStruct $issue ) {
        $translation = ( new \Translations_TranslationVersionDao() )->getVersionNumberForTranslation( $this->command->job_id, $issue->id_segment, $issue->translation_version );
        if ( !$translation ) {
            return \Translations_SegmentTranslationDao::findBySegmentAndJob( $issue->id_segment, $issue->id_job );
        }

        return $translation;
    }

    /**
     * @param ReviewBatch $savedReviewBatch
     *
     * @throws \Exception
     */
    private function writeReviews( ReviewBatch $savedReviewBatch ) {
        $values = [];

        $ppid = $savedReviewBatch->getChildProject()->getDqfId();
        $tid  = $savedReviewBatch->getTranslation()->getDqfId();

        if(count($savedReviewBatch->getRevisions()) > 0){
            foreach ( $savedReviewBatch->getRevisions() as $revision ) {
                $values[] = [
                        $tid,
                        $revision->getDqfId(),
                        $ppid
                ];
            }
        } else {
            $values[] = [
                    $tid,
                    null,
                    $ppid
            ];
        }

        $dqfReviewsDao = new DqfReviewsDao();
        $dqfReviewsDao->insertOrUpdateInATransaction( $values );
    }
}