<?php

namespace Features\Dqf\CommandHandler;

use Features\Dqf\Command\CreateTranslationBatchCommand;
use Features\Dqf\Model\DqfFileMapDao;
use Features\Dqf\Model\DqfFileMapStruct;
use Features\Dqf\Model\DqfProjectMapDao;
use Features\Dqf\Model\DqfProjectMapStruct;
use Features\Dqf\Utils\Factory\ClientFactory;
use Matecat\Dqf\Model\Entity\ChildProject;
use Matecat\Dqf\Model\Entity\File;
use Matecat\Dqf\Model\Entity\MasterProject;
use Matecat\Dqf\Model\Entity\SourceSegment;
use Matecat\Dqf\Model\ValueObject\TranslationBatch;
use Matecat\Dqf\Repository\Api\ChildProjectRepository;
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
     * @var DqfFileMapStruct
     */
    private $dqfFileMapStruct;

    /**
     * @param \Features\Dqf\Command\CommandInterface $command
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
        $projects                  = $dqfProjectMapDao->getByType( $this->chunk, 'translation' );
        $this->dqfProjectMapStruct = end( $projects );

        // get the DqfId of file
        $dqfFileMapDao          = new DqfFileMapDao();
        $this->dqfFileMapStruct = $dqfFileMapDao->findOne( $command->id_file, $command->id_job );

        $this->masterProjectRepository = new MasterProjectRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->childProjectRepository  = new ChildProjectRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->translationRepository   = new TranslationRepository( ClientFactory::create(), $sessionId, $genericEmail );
    }

    private function submitBatch() {
        $masterProject  = $this->getDqfMasterProject();
        $childProject   = $this->getDqfChildProject($masterProject);
        $file           = $this->getDqfFile($childProject);
        $sourceSegments = $this->getDqfSourceSegments($masterProject);

        $translationBatch = new TranslationBatch( $childProject, $file, $this->chunk->target );

        var_dump( $translationBatch );
        die();
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
    private function getDqfChildProject(MasterProject $masterProject) {

        // get DqfId and DqfUuid
        $dqfId   = (int)$this->dqfProjectMapStruct->dqf_project_id;
        $dqfUuid = $this->dqfProjectMapStruct->dqf_project_uuid;

        $childProject = $this->childProjectRepository->get($dqfId, $dqfUuid);
        $childProject->setParentProject( $masterProject );

        return $childProject;
    }

    /**
     * @param ChildProject $childProject
     *
     * @return File
     */
    private function getDqfFile( ChildProject $childProject ) {
        foreach ( $childProject->getFiles() as $file ) {
            if ( $file->getDqfId() === (int)$this->dqfFileMapStruct->dqf_id ) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @param MasterProject $masterProject
     *
     * @return SourceSegment[]
     */
    private function getDqfSourceSegments(MasterProject $masterProject) {
        return $masterProject->getSourceSegments();
    }
}