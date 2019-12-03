<?php

namespace Features\Dqf\CommandHandler;

use Constants_TranslationStatus;
use Features\Dqf\Command\CommandInterface;
use Features\Dqf\Command\CreateTranslationBatchCommand;
use Features\Dqf\Factory\ClientFactory;
use Features\Dqf\Model\DqfFileMapDao;
use Features\Dqf\Model\DqfProjectMapDao;
use Features\Dqf\Model\DqfProjectMapStruct;
use Features\Dqf\Model\DqfSegmentsDao;
use Features\Dqf\Transformer\SegmentTransformer;
use Matecat\Dqf\Constants;
use Matecat\Dqf\Model\Entity\ChildProject;
use Matecat\Dqf\Model\Entity\File;
use Matecat\Dqf\Model\Entity\TranslatedSegment;
use Matecat\Dqf\Model\ValueObject\TranslationBatch;
use Matecat\Dqf\Repository\Api\ChildProjectRepository;
use Matecat\Dqf\Repository\Api\FilesRepository;
use Matecat\Dqf\Repository\Api\TranslationRepository;

class CreateTranslationBatchCommandHandler extends AbstractTranslationCommandHandler {

    /**
     * @var CreateTranslationBatchCommand
     */
    private $command;

    /**
     * @var \Chunks_ChunkStruct
     */
    private $chunk;

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
     * @param CommandInterface $command
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function handle( $command ) {

        if ( false === $command instanceof CreateTranslationBatchCommand ) {
            throw new \Exception( 'Provided command is not a valid instance of CreateMasterProjectCommand class' );
        }

        /** @var CreateTranslationBatchCommand $command */
        $this->setUp( $command );
        $this->submitBatch();
    }

    /**
     * @param CreateTranslationBatchCommand $command
     *
     * @throws \Exception
     */
    private function setUp( CreateTranslationBatchCommand $command ) {
        $this->command = $command;
        $this->chunk   = \Chunks_ChunkDao::getByIdAndPassword( $command->job_id, $command->job_password );
        
        $uid          = $this->getTranslatorUid( $command->job_id, $command->job_password );
        $sessionId    = $this->getSessionId( $uid );
        $genericEmail = $this->getGenericEmail( $uid );

        // get the last child project on DB associated with the chunk
        $dqfProjectMapDao          = new DqfProjectMapDao();
        $projects                  = $dqfProjectMapDao->getByType( $this->chunk, Constants::PROJECT_TYPE_TRANSLATION );
        $this->dqfProjectMapStruct = end( $projects );

        // set repos
        $this->childProjectRepository  = new ChildProjectRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->translationRepository   = new TranslationRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->filesRepository         = new FilesRepository( ClientFactory::create(), $sessionId, $genericEmail );

        // transformer
        $this->transformer    = new SegmentTransformer();
    }

    /**
     * @throws \Exception
     */
    private function submitBatch() {
        $childProject   = $this->getDqfChildProject();
        $dqfFileMapDao  = new DqfFileMapDao();
        $dqfSegmentsDao = new DqfSegmentsDao();

        foreach ( $this->chunk->getFiles() as $file ) {

            // get the DqfId of file
            $dqfFileMapStruct = $dqfFileMapDao->findOne( $file->id, $this->command->job_id );
            $dqfFile          = $this->getDqfFile( $childProject, $dqfFileMapStruct->dqf_id );

            // init translationBatch
            $translationBatch = new TranslationBatch( $childProject, $dqfFile, $this->chunk->target );

            // get all segmentTranslation (STATUS_TRANSLATED) by files
            $translatedSegmentTranslations = ( new \Translations_SegmentTranslationDao() )->getByFileJobIdStatusAndSourcePage( $file->id, $this->command->job_id, Constants_TranslationStatus::STATUS_TRANSLATED );
            foreach ( $translatedSegmentTranslations as $translation ) {

                // if the segment is NOT a new or draft
                if ( $translation->status !== Constants_TranslationStatus::STATUS_NEW and $translation->status !== Constants_TranslationStatus::STATUS_DRAFT ) {

                    // transform $translation into an array containing all infos needed for DQF
                    $segmentTranslation = $this->getTranslatedSegmentFromTransformer($translation);
                    $translationBatch->addSegment( $segmentTranslation );
                }
            }

            /** @var TranslationBatch $savedTranslationBatch */
            $savedTranslationBatch = $this->translationRepository->save( $translationBatch );

            // save segment translations reference in a transaction
            $segmentReferences = [];

            foreach ( $savedTranslationBatch->getSegments() as $index => $segment ) {
                $segmentReferences[] = [
                        (int)$this->chunk->getTranslations()[ $index ]->id_segment,
                        (int)$segment->getSourceSegmentId(),
                        (int)$segment->getDqfId(),
                        (int)$childProject->getDqfId(),
                ];
            }

            $dqfSegmentsDao->insertOrUpdateInATransaction( $segmentReferences );
        }
    }

    /**
     * @return ChildProject
     */
    private function getDqfChildProject(  ) {

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
        return $this->filesRepository->getByIdAndChildProject(
                (int)$childProject->getDqfId(),
                $childProject->getDqfUuid(),
                (int)$dqfFileMapStructId
        );
    }
}