<?php

namespace Features\Dqf\CommandHandler;

use Features\Dqf\Command\CreateChildProjectCommand;
use Features\Dqf\Factory\ClientFactory;
use Features\Dqf\Model\DqfFileTargetLangAssocMapDao;
use Features\Dqf\Model\DqfFileTargetLangAssocMapStruct;
use Features\Dqf\Model\DqfProjectMapDao;
use Features\Dqf\Model\DqfProjectMapStruct;
use Matecat\Dqf\Model\Entity\ChildProject;
use Matecat\Dqf\Model\Entity\FileTargetLang;
use Matecat\Dqf\Repository\Api\ChildProjectRepository;
use Matecat\Dqf\Repository\Api\MasterProjectRepository;

class CreateChildProjectCommandHandler extends AbstractCommandHanlder {

    /**
     * @var ChildProjectRepository
     */
    private $childProjectRepository;

    /**
     * @var MasterProjectRepository
     */
    private $masterProjectRepository;

    /**
     * @var CreateChildProjectCommand
     */
    private $command;

    /**
     * @var \Chunks_ChunkStruct
     */
    private $chunk;

    /**
     * @param CreateChildProjectCommand $command
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function handle( $command ) {

        if ( false === $command instanceof CreateChildProjectCommand ) {
            throw new \Exception( 'Provided command is not a valid instance of CreateChildProjectCommand class' );
        }

        $this->setUp( $command );

        $childProject = $this->createProject();
        $this->assocTargetLang( $childProject );
    }

    /**
     * @param CreateChildProjectCommand $command
     *
     * @throws \Exception
     */
    private function setUp( CreateChildProjectCommand $command ) {
        $this->command = $command;
        $this->chunk   = \Chunks_ChunkDao::getByJobID( $command->id_job )[ 0 ];

        // REFACTOR THIS!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        $uid = $this->chunk->getProject()->getOriginalOwner()->getUid();
        // THIS MUST BE CHANGED

        $sessionId    = $this->getSessionId( $uid );
        $genericEmail = $this->getGenericEmail( $uid );

        $this->masterProjectRepository = new MasterProjectRepository( ClientFactory::create(), $sessionId, $genericEmail );
        $this->childProjectRepository  = new ChildProjectRepository( ClientFactory::create(), $sessionId, $genericEmail );
    }

    /**
     * @return \Matecat\Dqf\Model\Entity\BaseApiEntity
     * @throws \Exception
     */
    private function createProject() {
        $id_project          = \Database::obtain()->nextSequence( 'id_dqf_project' )[ 0 ];
        $clientId            = \Utils::createToken();
        $parentMasterProject = ( new DqfProjectMapDao() )->findRootProject( $this->chunk );

        $dqfMasterProject = $this->masterProjectRepository->get( (int)$parentMasterProject->dqf_project_id, $parentMasterProject->dqf_project_uuid );

        $childProject = new ChildProject( $this->command->type );
        $childProject->setParentProject( $dqfMasterProject );
        $childProject->setClientId( $clientId );

        $dqfChildProject = $this->childProjectRepository->save( $childProject );

        $struct = new DqfProjectMapStruct( [
                'id'               => $id_project,
                'id_job'           => $this->chunk->id,
                'password'         => $this->chunk->password,
                'first_segment'    => $this->chunk->job_first_segment,
                'last_segment'     => $this->chunk->job_last_segment,
                'dqf_client_id'    => $clientId,
                'dqf_project_id'   => $dqfChildProject->getDqfId(),
                'dqf_project_uuid' => $dqfChildProject->getDqfUuid(),
                'dqf_parent_id'    => $dqfMasterProject->getDqfId(),
                'dqf_parent_uuid'  => $dqfMasterProject->getDqfUuid(),
                'create_date'      => \Utils::mysqlTimestamp( time() ),
                'project_type'     => $this->command->type,
                'uid'              => $this->chunk->getProject()->getOriginalOwner()->getUid() // THIS NEEDS REFACTORING!!!!!!!!!!!!!!!!!!
        ] );

        DqfProjectMapDao::insertStructWithAutoIncrements( $struct );

        return $dqfChildProject;
    }

    /**
     * @param ChildProject $childProject
     *
     * @throws \Exception
     */
    private function assocTargetLang( ChildProject $childProject ) {

        $i = null;

        foreach ( $this->chunk->getFiles() as $file ) {
            foreach ( $childProject->getParentProject()->getFiles() as $index => $dqfFile ) {
                if ( $dqfFile->getName() === $file->filename ) {
                    $childProject->assocTargetLanguageToFile( $this->chunk->target, $dqfFile );
                    $i = $index; // return the position in assocTargetLanguageToFile, it's needed later to be passed to getTargetLanguageAssoc method
                }
            }
        }

        $this->childProjectRepository->update( $childProject );

        if ( null !== $i ) {
            /** @var FileTargetLang $dqfTargetLangAssoc */
            $dqfTargetLangAssoc = $childProject->getTargetLanguageAssoc()[ $this->chunk->target ][ $i ];

            $struct = new DqfFileTargetLangAssocMapStruct( [
                    'dqf_id'             => $dqfTargetLangAssoc->getDqfId(),
                    'dqf_file_id'        => $dqfTargetLangAssoc->getFile()->getDqfId(),
                    'dqf_target_lang_id' => $dqfTargetLangAssoc->getLanguage()->getDqfId(),
                    'target_lang'        => $this->chunk->target,
            ] );

            DqfFileTargetLangAssocMapDao::insertStruct( $struct );
        }
    }
}