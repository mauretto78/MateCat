<?php

namespace Features\Dqf\CommandHandler;

use Features\Dqf\Command\CreateMasterProjectCommand;
use Features\Dqf\Factory\ClientFactory;
use Features\Dqf\Model\DqfFileMapDao;
use Features\Dqf\Model\DqfFileMapStruct;
use Features\Dqf\Model\DqfFileTargetLangAssocMapDao;
use Features\Dqf\Model\DqfFileTargetLangAssocMapStruct;
use Features\Dqf\Model\DqfProjectMapDao;
use Features\Dqf\Model\DqfProjectMapStruct;
use Features\Dqf\Model\DqfQualityModel;
use Features\Dqf\Model\DqfSegmentsDao;
use Features\Dqf\Utils\ProjectMetadata;
use Matecat\Dqf\Model\Entity\File;
use Matecat\Dqf\Model\Entity\FileTargetLang;
use Matecat\Dqf\Model\Entity\MasterProject;
use Matecat\Dqf\Model\Entity\ReviewSettings;
use Matecat\Dqf\Model\Entity\SourceSegment;
use Matecat\Dqf\Model\ValueObject\Severity;
use Matecat\Dqf\Repository\Api\MasterProjectRepository;

class CreateMasterProjectCommandHandler extends AbstractCommandHandler {

    /**
     * @var \Projects_ProjectStruct
     */
    private $project;

    /**
     * @var CreateMasterProjectCommand
     */
    private $command;

    /**
     * @var MasterProjectRepository
     */
    private $repo;

    /**
     * @param CreateMasterProjectCommand $command
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle( $command ) {

        if ( false === $command instanceof CreateMasterProjectCommand ) {
            throw new \Exception( 'Provided command is not a valid instance of CreateMasterProjectCommand class' );
        }

        $this->setUp( $command );
        $masterProject = $this->createProject();
        $this->submitProjectFiles( $masterProject );
        $this->submitReviewSettings( $masterProject );
        $this->submitSourceSegments( $masterProject );
        $this->saveCompletion();
    }

    /**
     * @param CreateMasterProjectCommand $command
     *
     * @throws \Exception
     */
    private function setUp( CreateMasterProjectCommand $command ) {

        $this->command = $command;
        $this->project = \Projects_ProjectDao::findById( $command->id_project );

        if ( empty( $this->project->getOriginalOwner()->getUid() ) ) {
            throw new \Exception( 'The project has not an owner' );
        }

        $sessionId    = $this->getSessionId( $this->project->getOriginalOwner()->getUid() );
        $genericEmail = $this->getGenericEmail( $this->project->getOriginalOwner()->getUid() );

        $this->repo = new MasterProjectRepository( ClientFactory::create(), $sessionId, $genericEmail );
    }

    /**
     * @return MasterProject
     * @throws \Exception
     */
    private function createProject() {
        $projectInputParams = ProjectMetadata::extractProjectParameters( $this->project->getMetadataAsKeyValue() );
        $id_project         = \Database::obtain()->nextSequence( 'id_dqf_project' )[ 0 ];
        $clientId           = \Utils::createToken();

        $masterProject = new MasterProject(
                $this->project->name,
                $this->command->source_language,
                (int)$projectInputParams[ 'contentTypeId' ],
                (int)$projectInputParams[ 'industryId' ],
                (int)$projectInputParams[ 'processId' ],
                (int)$projectInputParams[ 'qualityLevelId' ]
        );

        $masterProject->setClientId( $clientId );

        $dqfMasterProject = $this->repo->save( $masterProject );

        foreach ( $this->project->getChunks() as $chunk ) {
            $struct = new DqfProjectMapStruct( [
                    'id'               => $id_project,
                    'id_job'           => $chunk->id,
                    'password'         => $chunk->password,
                    'first_segment'    => $chunk->job_first_segment,
                    'last_segment'     => $chunk->job_last_segment,
                    'dqf_client_id'    => $clientId,
                    'dqf_project_id'   => $dqfMasterProject->getDqfId(),
                    'dqf_project_uuid' => $dqfMasterProject->getDqfUuid(),
                    'create_date'      => \Utils::mysqlTimestamp( time() ),
                    'project_type'     => 'master',
                    'uid'              => $this->project->getOriginalOwner()->uid
            ] );

            DqfProjectMapDao::insertStructWithAutoIncrements( $struct );
        }

        return $dqfMasterProject;
    }

    /**
     * @param MasterProject $masterProject
     *
     * @throws \Exception
     */
    private function submitProjectFiles( MasterProject $masterProject ) {
        $files = \Files_FileDao::getByProjectId( $this->project->id );

        foreach ( $files as $file ) {
            $segmentsCount = $this->command->file_segments_count[ $file->id ];
            $clientId      = \Utils::createToken();

            $dqfFile = new File( $file->filename, $segmentsCount );
            $dqfFile->setClientId( $clientId );
            $masterProject->addFile( $dqfFile );

            $this->repo->update( $masterProject );

            $struct = new DqfFileMapStruct( [
                    'file_id'                 => $file->id,
                    'file_name'               => $file->filename,
                    'dqf_id'                  => $dqfFile->getDqfId(),
                    'dqf_client_id'           => $clientId,
                    'dqf_number_of_segments'  => $segmentsCount,
                    'dqf_parent_project_id'   => $masterProject->getDqfId(),
                    'dqf_parent_project_uuid' => $masterProject->getDqfUuid(),
            ] );

            DqfFileMapDao::insertStructWithAutoIncrements( $struct );

            foreach ( $this->project->getTargetLanguages() as $index => $targetLanguage ) {
                $masterProject->assocTargetLanguageToFile( $targetLanguage, $dqfFile );

                $this->repo->update( $masterProject );

                /** @var FileTargetLang $dqfTargetLangAssoc */
                $dqfTargetLangAssoc = $masterProject->getTargetLanguageAssoc()[ $targetLanguage ][ $index ];

                $struct = new DqfFileTargetLangAssocMapStruct( [
                        'dqf_id'             => $dqfTargetLangAssoc->getDqfId(),
                        'dqf_file_id'        => $dqfTargetLangAssoc->getFile()->getDqfId(),
                        'dqf_target_lang_id' => $dqfTargetLangAssoc->getLanguage()->getDqfId(),
                        'target_lang'        => $targetLanguage,
                ] );

                DqfFileTargetLangAssocMapDao::insertStruct( $struct );
            }
        }
    }

    /**
     * @param MasterProject $masterProject
     */
    private function submitReviewSettings( MasterProject $masterProject ) {
        $dqfQaModel           = new DqfQualityModel( $this->project );
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

        $masterProject->setReviewSettings( $reviewSettings );

        $this->repo->update( $masterProject );
    }

    /**
     * @param MasterProject $masterProject
     *
     * @throws \Exception
     */
    private function submitSourceSegments( MasterProject $masterProject ) {

        $files = \Files_FileDao::getByProjectId( $this->project->id );

        // array map needed to persist source segments on DB after sending them to DQF
        $arrayMapOfIds = [];

        foreach ( $files as $index => $file ) {
            $segments     = ( new \Segments_SegmentDao() )->getByFileId( $file->id );
            $dqfFile      = $masterProject->getFiles()[ $index ];
            $global_index = 1;

            foreach ( $segments as $segment ) {
                $masterProject->addSourceSegment( new SourceSegment(
                        $dqfFile,
                        $global_index,
                        $segment->segment
                ) );

                $arrayMapOfIds[ $file->filename ][] = [
                        $segment->id,
                        $global_index
                ];

                $global_index++;
            }
        }

        $this->repo->update( $masterProject );

        $this->writeSourceSegments( $masterProject, $arrayMapOfIds );
    }

    /**
     * save project_completion metadata
     */
    private function saveCompletion() {
        $this->project->setMetadata( 'dqf_master_project_creation_completed_at', time() );
    }

    /**
     * @param MasterProject $masterProject
     * @param array         $arrayMapOfIds
     *
     * @throws \Exception
     */
    private function writeSourceSegments( MasterProject $masterProject, $arrayMapOfIds = [] ) {

        $values = [];

        foreach ( $masterProject->getSourceSegments() as $file => $filesSourceSegments ) {

            /** @var SourceSegment $sourceSegment */
            foreach ( $filesSourceSegments as $i => $sourceSegment ) {

                $matecatId = $arrayMapOfIds[ $file ][ $i ][ 0 ];
                $index     = $arrayMapOfIds[ $file ][ $i ][ 1 ];

                if ( $sourceSegment->getIndexNo() === $index ) {
                    // id_segment, dqf_segment_id, dqf_translation_id, dqf_parent_project_id
                    $values[] = [
                            $matecatId,
                            $sourceSegment->getDqfId(),
                            null,
                            $masterProject->getDqfId()
                    ];
                }
            }
        }

        $dqfSegmentsDao = new DqfSegmentsDao();
        $dqfSegmentsDao->insertOrUpdateInATransaction($values);
    }
}
