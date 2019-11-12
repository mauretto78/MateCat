<?php

namespace Features\Dqf\CommandHandler;

use Features\Dqf\Command\CreateReviewCommand;
use Features\Dqf\Factory\ClientFactory;
use Features\Dqf\Model\DqfFileMapDao;
use Features\Dqf\Model\DqfProjectMapDao;
use Features\Dqf\Model\DqfProjectMapStruct;
use Features\Dqf\Model\DqfSegmentsDao;
use Features\Dqf\Transformer\ReviewTranslationTransformer;
use Features\Dqf\Transformer\SegmentTranslationTransformer;
use Matecat\Dqf\Constants;
use Matecat\Dqf\Model\Entity\ChildProject;
use Matecat\Dqf\Model\Entity\MasterProject;
use Matecat\Dqf\Repository\Api\ChildProjectRepository;
use Matecat\Dqf\Repository\Api\FilesRepository;
use Matecat\Dqf\Repository\Api\MasterProjectRepository;
use Matecat\Dqf\Repository\Api\TranslationRepository;

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
        $this->chunk   = \Chunks_ChunkDao::getByJobID( $command->id_job )[ 0 ];

        // REFACTOR THIS!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        $uid = $this->chunk->getProject()->getOriginalOwner()->getUid();
        // THIS MUST BE CHANGED

        $sessionId    = $this->getSessionId( $uid );
        $genericEmail = $this->getGenericEmail( $uid );

        // get the last child project on DB associated with the chunk
        $dqfProjectMapDao          = new DqfProjectMapDao();

        $projects                  = $dqfProjectMapDao->getByType( $this->chunk, Constants::PROJECT_TYPE_REVIEW );
        $this->dqfProjectMapStruct = end( $projects );

        // set repos
        $this->masterProjectRepository = new MasterProjectRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->childProjectRepository  = new ChildProjectRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->translationRepository   = new TranslationRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->filesRepository         = new FilesRepository( ClientFactory::create(), $sessionId, $genericEmail );
    }

    private function submitBatch() {
        $parentProject  = $this->getDqfParentProject();
        $childProject   = $this->getDqfChildProject( $parentProject );
        $dqfFileMapDao  = new DqfFileMapDao();
        $transformer    = new ReviewTranslationTransformer();
        $dqfSegmentsDao = new DqfSegmentsDao();




//        $correction = new RevisionCorrection('Another review comment', 10000);
//        $correction->addItem(new RevisionCorrectionItem('review', 'deleted'));
//        $correction->addItem(new RevisionCorrectionItem('Another comment', 'unchanged'));
//
//        $reviewedSegment = new ReviewedSegment('this is a comment');
//        $reviewedSegment->addError(new RevisionError(11, 2));
//        $reviewedSegment->addError(new RevisionError(9, 1, 1, 5));
//        $reviewedSegment->setCorrection($correction);
//
//        $reviewedSegment2 = new ReviewedSegment('this is another comment');
//        $reviewedSegment2->addError(new RevisionError(10, 2));
//        $reviewedSegment2->addError(new RevisionError(11, 1, 1, 5));
//        $reviewedSegment2->setCorrection($correction);
//
//        $batchId = Uuid::uuid4()->toString();
//        $reviewBatch = new ReviewBatch($childReview, $file, 'en-US', $segment, $batchId);
//        $reviewBatch->addReviewedSegment($reviewedSegment);
//        $reviewBatch->addReviewedSegment($reviewedSegment2);
//
//        $reviewRepository->save($reviewBatch);
    }

    /**
     * @return ChildProject
     */
    private function getDqfParentProject() {
        $dqfId   = (int)$this->dqfProjectMapStruct->dqf_parent_id;
        $dqfUuid = $this->dqfProjectMapStruct->dqf_parent_uuid;

        return $this->masterProjectRepository->get( $dqfId, $dqfUuid );
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
}