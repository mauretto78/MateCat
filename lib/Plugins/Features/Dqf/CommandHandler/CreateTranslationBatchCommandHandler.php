<?php

namespace Features\Dqf\CommandHandler;

use Features\Dqf\Command\CommandInterface;
use Features\Dqf\Command\CreateTranslationBatchCommand;
use Features\Dqf\Factory\ClientFactory;
use Features\Dqf\Model\DqfFileMapDao;
use Features\Dqf\Model\DqfProjectMapDao;
use Features\Dqf\Model\DqfProjectMapStruct;
use Features\Dqf\Model\DqfSegmentsDao;
use Features\Dqf\Transformer\SegmentTranslationTransformer;
use Matecat\Dqf\Constants;
use Matecat\Dqf\Model\Entity\ChildProject;
use Matecat\Dqf\Model\Entity\File;
use Matecat\Dqf\Model\Entity\MasterProject;
use Matecat\Dqf\Model\Entity\SourceSegment;
use Matecat\Dqf\Model\Entity\TranslatedSegment;
use Matecat\Dqf\Model\ValueObject\TranslationBatch;
use Matecat\Dqf\Repository\Api\ChildProjectRepository;
use Matecat\Dqf\Repository\Api\FilesRepository;
use Matecat\Dqf\Repository\Api\MasterProjectRepository;
use Matecat\Dqf\Repository\Api\TranslationRepository;

class CreateTranslationBatchCommandHandler extends AbstractCommandHanlder {

    /**
     * @var CreateTranslationBatchCommand
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
     * @param CommandInterface $command
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function handle( $command ) {

        if ( false === $command instanceof CreateTranslationBatchCommand ) {
            throw new \Exception( 'Provided command is not a valid instance of CreateMasterProjectCommand class' );
        }

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
        $this->chunk   = \Chunks_ChunkDao::getByJobID( $command->id_job )[ 0 ];

        // REFACTOR THIS!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        $uid = $this->chunk->getProject()->getOriginalOwner()->getUid();
        // THIS MUST BE CHANGED

        $sessionId    = $this->getSessionId( $uid );
        $genericEmail = $this->getGenericEmail( $uid );

        // get the last child project on DB associated with the chunk
        $dqfProjectMapDao          = new DqfProjectMapDao();
        $projects                  = $dqfProjectMapDao->getByType( $this->chunk, Constants::PROJECT_TYPE_TRANSLATION );
        $this->dqfProjectMapStruct = end( $projects );

        // set repos
        $this->masterProjectRepository = new MasterProjectRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->childProjectRepository  = new ChildProjectRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->translationRepository   = new TranslationRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->filesRepository         = new FilesRepository( ClientFactory::create(), $sessionId, $genericEmail );
    }

    /**
     * @throws \Exception
     */
    private function submitBatch() {
        $masterProject  = $this->getDqfMasterProject();
        $childProject   = $this->getDqfChildProject( $masterProject );
        $dqfFileMapDao  = new DqfFileMapDao();
        $transformer    = new SegmentTranslationTransformer();
        $dqfSegmentsDao = new DqfSegmentsDao();

        foreach ( $this->chunk->getFiles() as $file ) {

            // get the DqfId of file
            $dqfFileMapStruct = $dqfFileMapDao->findOne( $file->id, $this->command->id_job );
            $dqfFile          = $this->getDqfFile( $childProject, $dqfFileMapStruct->dqf_id );

            // init translationBatch
            $translationBatch = new TranslationBatch( $childProject, $dqfFile, $this->chunk->target );

            // loop all segmentTranslation by files
            $translations = ( new \Translations_SegmentTranslationDao() )->getByFile( $file );
            foreach ( $translations as $translation ) {
                $transformedTranslation = $transformer->transform( $translation );

                $mtEngine        = $transformedTranslation[ 'mtEngineId' ];
                $segmentOriginId = $transformedTranslation[ 'segmentOriginId' ];
                $targetLang      = $transformedTranslation[ 'targetLang' ];
                $targetSegment   = $transformedTranslation[ 'targetSegment' ];
                $editedSegment   = $transformedTranslation[ 'editedSegment' ];
                $sourceSegment   = $this->getDqfSourceSegment( $masterProject, $transformedTranslation[ 'sourceSegmentId' ] );

                $segmentTranslation = new TranslatedSegment( $mtEngine, $segmentOriginId, $targetLang, $sourceSegment, $targetSegment, $editedSegment );
                $segmentTranslation->setMatchRate( $transformedTranslation[ 'matchRate' ] );
                $segmentTranslation->setTime( $transformedTranslation[ 'time' ] );
                $segmentTranslation->setIndexNo( $transformedTranslation[ 'indexNo' ] );

                $translationBatch->addSegment( $segmentTranslation );
            }

            /** @var TranslationBatch $translationBatch */
            $translationBatch = $this->translationRepository->save( $translationBatch );

            // save segment translations reference in a transaction
            $segmentReferences = [];

            foreach ( $translationBatch->getSegments() as $index => $segment ) {
                $segmentReferences[] = (int)$this->chunk->getTranslations()[ $index ]->id_segment;
                $segmentReferences[] = (int)$segment->getSourceSegment()->getDqfId();
                $segmentReferences[] = (int)$segment->getDqfId();
                $segmentReferences[] = (int)$childProject->getDqfId();
            }

            $dqfSegmentsDao->insertInATransaction( $segmentReferences );
        }
    }

    /**
     * @return MasterProject
     */
    private function getDqfMasterProject() {
        $dqfId   = (int)$this->dqfProjectMapStruct->dqf_parent_id;
        $dqfUuid = $this->dqfProjectMapStruct->dqf_parent_uuid;

        return $this->masterProjectRepository->get( $dqfId, $dqfUuid );
    }

    /**
     * @param MasterProject $masterProject
     *
     * @return ChildProject
     */
    private function getDqfChildProject( MasterProject $masterProject ) {

        // get DqfId and DqfUuid
        $dqfId   = (int)$this->dqfProjectMapStruct->dqf_project_id;
        $dqfUuid = $this->dqfProjectMapStruct->dqf_project_uuid;

        $childProject = $this->childProjectRepository->get( $dqfId, $dqfUuid );
        $childProject->setParentProject( $masterProject );

        return $childProject;
    }

    /**
     * @param ChildProject $childProject
     * @param int          $dqfFileMapStructId
     *
     * @return File
     */
    private function getDqfFile( ChildProject $childProject, $dqfFileMapStructId ) {
        return $this->filesRepository->getByIdAndChildProject( $childProject->getDqfId(), $childProject->getDqfUuid(), $dqfFileMapStructId );
    }

    /**
     * @param MasterProject $masterProject
     * @param int           $sourceSegmentDqfId
     *
     * @return SourceSegment|null
     */
    private function getDqfSourceSegment( MasterProject $masterProject, $sourceSegmentDqfId ) {
        $sourceSegments = $masterProject->getSourceSegments();

        /** @var SourceSegment $sourceSegment */
        foreach ( $sourceSegments as $sourceSegment ) {
            if ( $sourceSegment->getDqfId() === $sourceSegmentDqfId ) {
                return $sourceSegment;
            }
        }

        return null;
    }
}