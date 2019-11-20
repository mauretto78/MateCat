<?php

namespace Features\Dqf\CommandHandler;

use Features\Dqf\Command\CommandInterface;
use Features\Dqf\Command\UpdateSegmentTranslationCommand;
use Features\Dqf\Factory\ClientFactory;
use Features\Dqf\Model\DqfFileMapDao;
use Features\Dqf\Model\DqfFileMapStruct;
use Features\Dqf\Model\DqfProjectMapDao;
use Features\Dqf\Model\DqfProjectMapStruct;
use Features\Dqf\Transformer\SegmentTransformer;
use Matecat\Dqf\Model\Entity\ChildProject;
use Matecat\Dqf\Model\Entity\File;
use Matecat\Dqf\Model\Entity\MasterProject;
use Matecat\Dqf\Model\Entity\SourceSegment;
use Matecat\Dqf\Model\Entity\TranslatedSegment;
use Matecat\Dqf\Repository\Api\ChildProjectRepository;
use Matecat\Dqf\Repository\Api\FilesRepository;
use Matecat\Dqf\Repository\Api\MasterProjectRepository;
use Matecat\Dqf\Repository\Api\TranslationRepository;

class UpdateSegmentTranslationCommandHandler extends AbstractCommandHanlder {

    /**
     * @var UpdateSegmentTranslationCommand
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
     * @var DqfFileMapStruct
     */
    private $dqfFileMapStruct;

    /**
     * @var FilesRepository
     */
    private $filesRepository;

    /**
     * @param UpdateSegmentTranslationCommand $command
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle( $command ) {
        if ( false === $command instanceof UpdateSegmentTranslationCommand ) {
            throw new \Exception( 'Provided command is not a valid instance of UpdateSegmentTranslationCommand class' );
        }

        $this->setUp( $command );
        $this->submit();
    }

    /**
     * @param UpdateSegmentTranslationCommand $command
     *
     * @throws \Exception
     */
    private function setUp( UpdateSegmentTranslationCommand $command ) {
        $this->command = $command;
        $this->chunk   = \Chunks_ChunkDao::getByIdAndPassword( $command->job_id, $command->job_password );

        // REFACTOR THIS!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        $uid = $this->chunk->getProject()->getOriginalOwner()->getUid();
        // THIS MUST BE CHANGED

        $sessionId    = $this->getSessionId( $uid );
        $genericEmail = $this->getGenericEmail( $uid );

        // get the last child project on DB associated with the chunk
        $dqfProjectMapDao          = new DqfProjectMapDao();
        $projects                  = $dqfProjectMapDao->getByType( $this->chunk, 'translation' );
        $this->dqfProjectMapStruct = end( $projects );

        // get the DqfId of file
        $dqfFileMapDao          = new DqfFileMapDao();
        $this->dqfFileMapStruct = $dqfFileMapDao->findOne( $command->id_file, $command->job_id );

        $this->masterProjectRepository = new MasterProjectRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->childProjectRepository  = new ChildProjectRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->translationRepository   = new TranslationRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->filesRepository         = new FilesRepository( ClientFactory::create(), $sessionId, $genericEmail );
    }

    /**
     * @throws \Exception
     */
    private function submit() {
        $masterProject     = $this->getDqfMasterProject();
        $childProject      = $this->getDqfChildProject( $masterProject );
        $file              = $this->getDqfFile( $childProject );
        $translatedSegment = $this->getTranslatedSegment( $masterProject );

        $this->translationRepository->update($childProject, $file, $translatedSegment);
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
     * @return mixed
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
     *
     * @return File
     */
    private function getDqfFile( ChildProject $childProject ) {
        return $this->filesRepository->getByIdAndChildProject( $childProject->getDqfId(), $childProject->getDqfUuid(), $this->dqfFileMapStruct->dqf_id );
    }

    /**
     * @param MasterProject $masterProject
     *
     * @return TranslatedSegment
     * @throws \Exception
     */
    private function getTranslatedSegment( MasterProject $masterProject ) {
        // get the segment translation
        $segmentTranslation = \Translations_SegmentTranslationDao::findBySegmentAndJob( $this->command->id_segment, $this->command->job_id );
        $transformer        = new SegmentTransformer();

        $transformedTranslation = $transformer->transform( $segmentTranslation );

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

        return $segmentTranslation;
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