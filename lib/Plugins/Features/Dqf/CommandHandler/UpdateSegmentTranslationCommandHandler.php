<?php

namespace Features\Dqf\CommandHandler;

use Features\Dqf\Command\UpdateSegmentTranslationCommand;
use Features\Dqf\Factory\ClientFactory;
use Features\Dqf\Model\DqfFileMapDao;
use Features\Dqf\Model\DqfFileMapStruct;
use Features\Dqf\Model\DqfProjectMapDao;
use Features\Dqf\Model\DqfProjectMapStruct;
use Features\Dqf\Model\DqfSegmentsDao;
use Features\Dqf\Model\DqfSegmentsStruct;
use Features\Dqf\Transformer\SegmentTransformer;
use Matecat\Dqf\Model\Entity\ChildProject;
use Matecat\Dqf\Model\Entity\File;
use Matecat\Dqf\Model\Entity\TranslatedSegment;
use Matecat\Dqf\Repository\Api\ChildProjectRepository;
use Matecat\Dqf\Repository\Api\FilesRepository;
use Matecat\Dqf\Repository\Api\TranslationRepository;

class UpdateSegmentTranslationCommandHandler extends AbstractTranslationCommandHandler {

    /**
     * @var UpdateSegmentTranslationCommand
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
     * @var DqfFileMapStruct
     */
    private $dqfFileMapStruct;

    /**
     * @var FilesRepository
     */
    private $filesRepository;

    /**
     * @var DqfSegmentsStruct
     */
    private $dqfSegmentsStruct;

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
        $this->transformer        = new SegmentTransformer();

        $uid          = $this->getTranslatorUid( $command->job_id, $command->job_password );
        $sessionId    = $this->getSessionId( $uid );
        $genericEmail = $this->getGenericEmail( $uid );

        // get the last child project on DB associated with the chunk
        $dqfProjectMapDao          = new DqfProjectMapDao();
        $projects                  = $dqfProjectMapDao->getByType( $this->chunk, 'translation' );
        $this->dqfProjectMapStruct = end( $projects );

        $segment = (new \Segments_SegmentDao)->getById($this->command->id_segment);

        // get the DqfId of file
        $dqfFileMapDao          = new DqfFileMapDao();
        $this->dqfFileMapStruct = $dqfFileMapDao->findOne( $segment->id_file, $command->job_id );

        // get the DqfId of translation
        $dqfSegmentsDao = new DqfSegmentsDao();
        $this->dqfSegmentsStruct = $dqfSegmentsDao->getByIdSegment($command->id_segment);

        // set repos
        $this->childProjectRepository = new ChildProjectRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->translationRepository  = new TranslationRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->filesRepository        = new FilesRepository( ClientFactory::create(), $sessionId, $genericEmail );

        // transformer
        $this->transformer    = new SegmentTransformer();
    }

    /**
     * @throws \Exception
     */
    private function submit() {
        $childProject      = $this->getDqfChildProject();
        $file              = $this->getDqfFile( $childProject );
        $translatedSegment = $this->getTranslatedSegment();
        $translatedSegment->setDqfId((int)$this->dqfSegmentsStruct->dqf_translation_id);

        $this->translationRepository->update( $childProject, $file, $translatedSegment );
    }

    /**
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
     *
     * @return File
     */
    private function getDqfFile( ChildProject $childProject ) {
        return $this->filesRepository->getByIdAndChildProject(
                (int)$childProject->getDqfId(),
                $childProject->getDqfUuid(),
                (int)$this->dqfFileMapStruct->dqf_id
        );
    }

    /**
     * @return TranslatedSegment
     * @throws \Exception
     */
    private function getTranslatedSegment() {
        // get the segment translation
        $segmentTranslation = \Translations_SegmentTranslationDao::findBySegmentAndJob( $this->command->id_segment, $this->command->job_id );

        return $this->getTranslatedSegmentFromTransformer($segmentTranslation);
    }
}