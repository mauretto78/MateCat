<?php

namespace Features\Dqf\CommandHandler;

use Features\Dqf\Command\CreateChildProjectCommand;
use Features\Dqf\Factory\ClientFactory;
use Features\Dqf\Model\DqfFileMapDao;
use Features\Dqf\Model\DqfFileTargetLangAssocMapDao;
use Features\Dqf\Model\DqfFileTargetLangAssocMapStruct;
use Features\Dqf\Model\DqfProjectMapDao;
use Features\Dqf\Model\DqfProjectMapStruct;
use Features\Dqf\Model\DqfQualityModel;
use Matecat\Dqf\Constants;
use Matecat\Dqf\Model\Entity\ChildProject;
use Matecat\Dqf\Model\Entity\File;
use Matecat\Dqf\Model\Entity\FileTargetLang;
use Matecat\Dqf\Model\Entity\ReviewSettings;
use Matecat\Dqf\Model\ValueObject\Severity;
use Matecat\Dqf\Repository\Api\ChildProjectRepository;

class CreateChildProjectCommandHandler extends AbstractCommandHandler {

    /**
     * @var ChildProjectRepository
     */
    private $childProjectRepository;

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
            throw new \InvalidArgumentException( 'Provided command is not a valid instance of ' . CreateChildProjectCommand::class . ' class' );
        }

        $allowed = [ Constants::PROJECT_TYPE_TRANSLATION, Constants::PROJECT_TYPE_REVIEW ];

        if ( false === in_array( $command->type, $allowed ) ) {
            throw new \DomainException( $command->type . 'is not a valid type. [Allowed: ' . implode( ',', $allowed ) . ']' );
        }

        $this->setUp( $command );

        /** @var ChildProject $childProject */
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
        $this->chunk   = \Chunks_ChunkDao::getByIdAndPassword( $command->job_id, $command->job_password );

        if(null === $this->chunk){
            throw new \InvalidArgumentException('Chunk with id ' . $command->job_id . ' and password ' . $command->job_password . ' does not exist.');
        }

        $dqfReference    = $this->getDqfReferenceEmailAndUserId();
        $this->userEmail = $dqfReference['email'];
        $this->userId    = $dqfReference['uid'];

        $sessionId    = $this->getSessionId( $this->userEmail, $this->userId );
        $genericEmail = (null !== $this->userId) ? $this->getGenericEmail( $this->userId ) : $this->userEmail;

        $this->childProjectRepository  = new ChildProjectRepository( ClientFactory::create(), $sessionId, $genericEmail );
    }

    /**
     * @return \Matecat\Dqf\Model\Entity\BaseApiEntity
     * @throws \Exception
     */
    private function createProject() {
        $id_project = \Database::obtain()->nextSequence( 'id_dqf_project' )[ 0 ];
        $clientId   = \Utils::createToken();

        //
        // set the parent project
        // ====================================
        // if the project is a 'translation', set the master root project
        // otherwise, if the project is a 'revision', set the 'translation'
        //
        if ( $this->command->type === Constants::PROJECT_TYPE_TRANSLATION ) {
            $parentProject = ( new DqfProjectMapDao() )->findRootProject( $this->chunk );
        } elseif ( $this->command->type === Constants::PROJECT_TYPE_REVIEW ) {
            $parentProject = end( ( new DqfProjectMapDao() )->getChildByChunkAndType( $this->chunk, Constants::PROJECT_TYPE_TRANSLATION ) );
        }

        $childProject = new ChildProject( $this->command->type );
        $childProject->setParentProjectUuid( $parentProject->dqf_project_uuid );
        $childProject->setClientId( $clientId );
        $childProject->setIsDummy( false );
        $childProject->setAssigner( $this->userEmail );
        $this->setReviewSettings( $childProject );

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
                'dqf_parent_id'    => $parentProject->dqf_project_id,
                'dqf_parent_uuid'  => $parentProject->dqf_project_uuid,
                'create_date'      => \Utils::mysqlTimestamp( time() ),
                'project_type'     => $this->command->type,
                'uid'              => $this->userId
        ] );

        DqfProjectMapDao::insertStructWithAutoIncrements( $struct );

        return $dqfChildProject;
    }

    private function setReviewSettings( ChildProject $childProject ) {
        $dqfQaModel           = new DqfQualityModel( $this->chunk->getProject() );
        $reviewSettingsStruct = $dqfQaModel->getReviewSettings();

        $reviewSettings = new ReviewSettings( $reviewSettingsStruct->reviewType );
        $reviewSettings->setTemplateName( $reviewSettingsStruct->templateName );
        $reviewSettings->setPassFailThreshold( floatval( $reviewSettingsStruct->passFailThreshold ) );
        $reviewSettings->setSampling( $reviewSettingsStruct->sampling );

        foreach ( $reviewSettingsStruct->errorCategoryIds as $errorCategoryId ) {
            $reviewSettings->addErrorCategoryId( $errorCategoryId );
        }

        $severityWeights = json_decode( $reviewSettingsStruct->severityWeights );
        foreach ( $severityWeights as $severityWeight ) {
            $reviewSettings->addSeverityWeight( new Severity( $severityWeight->severityId, $severityWeight->weight ) );
        }

        $childProject->setReviewSettings( $reviewSettings );
    }

    /**
     * @param ChildProject $childProject
     *
     * @throws \Exception
     */
    private function assocTargetLang( ChildProject $childProject ) {

        $i = null;

        foreach ( $this->chunk->getFiles() as $index => $file ) {
            $dqfFileMap = (new DqfFileMapDao())->findOne( $file->id, $this->chunk->id );
            $dqfFile = new File( $file->filename, $file->getSegmentsCount() );
            $dqfFile->setDqfId((int)$dqfFileMap->dqf_id);
            $childProject->assocTargetLanguageToFile( $this->chunk->target, $dqfFile );
            $i = $index;
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