<?php

namespace Features\Dqf\CommandHandler;

use Features\Dqf\Command\CreateChildProjectCommand;
use Features\Dqf\Factory\ClientFactory;
use Features\Dqf\Model\DqfFileTargetLangAssocMapDao;
use Features\Dqf\Model\DqfFileTargetLangAssocMapStruct;
use Features\Dqf\Model\DqfProjectMapDao;
use Features\Dqf\Model\DqfProjectMapStruct;
use Features\Dqf\Model\DqfQualityModel;
use Matecat\Dqf\Constants;
use Matecat\Dqf\Model\Entity\ChildProject;
use Matecat\Dqf\Model\Entity\FileTargetLang;
use Matecat\Dqf\Model\Entity\ReviewSettings;
use Matecat\Dqf\Model\ValueObject\Severity;
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

        $allowed = [ Constants::PROJECT_TYPE_TRANSLATION, Constants::PROJECT_TYPE_REVIEW ];

        if ( false === in_array( $command->type, $allowed ) ) {
            throw new \DomainException( $command->type . 'is not a valid type. [Allowed: ' . implode( ',', $allowed ) . ']' );
        }

        $this->setUp( $command );

        /** @var ChildProject $childProject */
        $childProject = $this->createProject();

        $this->assocTargetLang($childProject);
    }

    /**
     * @param CreateChildProjectCommand $command
     *
     * @throws \Exception
     */
    private function setUp( CreateChildProjectCommand $command ) {
        $this->command = $command;
        $this->chunk   = \Chunks_ChunkDao::getByIdAndPassword( $command->job_id, $command->job_password );

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
            $parentProjectRepo = $this->masterProjectRepository;
        } elseif ( $this->command->type === Constants::PROJECT_TYPE_REVIEW ) {
            $parentProject = end( ( new DqfProjectMapDao() )->getChildByChunk( $this->chunk ) ); // get the last project saved on DQF by chunk
            $parentProjectRepo = $this->childProjectRepository;
        }

        $dqfParentProject = $parentProjectRepo->get( (int)$parentProject->dqf_project_id, $parentProject->dqf_project_uuid );

        $childProject = new ChildProject( $this->command->type );
        $childProject->setParentProject( $dqfParentProject );
        $childProject->setClientId( $clientId );
        $childProject->setIsDummy(false);
        $this->setReviewSettings($childProject);

        $dqfChildProject = $this->childProjectRepository->save( $childProject );

        // REFACTOR THIS!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        $uid = $this->chunk->getProject()->getOriginalOwner()->getUid();
        // THIS MUST BE CHANGED

        $struct = new DqfProjectMapStruct( [
                'id'               => $id_project,
                'id_job'           => $this->chunk->id,
                'password'         => $this->chunk->password,
                'first_segment'    => $this->chunk->job_first_segment,
                'last_segment'     => $this->chunk->job_last_segment,
                'dqf_client_id'    => $clientId,
                'dqf_project_id'   => $dqfChildProject->getDqfId(),
                'dqf_project_uuid' => $dqfChildProject->getDqfUuid(),
                'dqf_parent_id'    => $dqfParentProject->getDqfId(),
                'dqf_parent_uuid'  => $dqfParentProject->getDqfUuid(),
                'create_date'      => \Utils::mysqlTimestamp( time() ),
                'project_type'     => $this->command->type,
                'uid'              => $uid
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