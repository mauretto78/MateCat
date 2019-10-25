<?php

namespace Features\Dqf\CommandHandler;

use Features\Dqf\Command\CreateMasterProjectCommand;
use Features\Dqf\Model\DqfProjectMapDao;
use Features\Dqf\Model\DqfProjectMapStruct;
use Features\Dqf\Model\DqfQualityModel;
use Features\Dqf\Utils\Factory\ClientFactory;
use Features\Dqf\Utils\Functions;
use Features\Dqf\Utils\ProjectMetadata;
use Features\Dqf\Utils\SessionProviderService;
use Matecat\Dqf\Model\Entity\File;
use Matecat\Dqf\Model\Entity\MasterProject;
use Matecat\Dqf\Model\Entity\ReviewSettings;
use Matecat\Dqf\Model\Entity\SourceSegment;
use Matecat\Dqf\Model\ValueObject\Severity;
use Matecat\Dqf\Repository\Api\MasterProjectRepository;
use Ramsey\Uuid\Uuid;

class CreateMasterProjectCommandHandler implements DqfCommandHandlerInterface {

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
    public function handle($command) {

        if(false === $command instanceof CreateMasterProjectCommand){
            throw new \Exception('Provided command is not a valid instance of CreateMasterProjectCommand class');
        }

        $this->setUp($command);

        $masterProject = $this->createProject();
        $this->submitProjectFiles($masterProject);
        $this->submitReviewSettings($masterProject);
        $this->submitSourceSegments($masterProject);
        //$this->saveCompletion();
    }

    /**
     * @param CreateMasterProjectCommand $command
     *
     * @throws \Exception
     */
    private function setUp(CreateMasterProjectCommand $command) {
        $this->command     = $command;
        $this->project     = \Projects_ProjectDao::findById( $command->id_project );
        $this->repo        = new MasterProjectRepository( ClientFactory::create(), $this->getSessionId(), 'mauro@translated.net' );
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    private function getSessionId() {

        return SessionProviderService::getAnonymous('mauro@translated.net');

        //return SessionProviderService::get( $this->project->getOriginalOwner()->getUid() );
    }

    /**
     * @return MasterProject
     * @throws \Exception
     */
    private function createProject() {
        $projectInputParams = ProjectMetadata::extractProjectParameters( $this->project->getMetadataAsKeyValue() );
        $id_project = \Database::obtain()->nextSequence( 'id_dqf_project' )[ 0 ];
        $clientId = Uuid::uuid4()->toString();

//        $masterProject = new MasterProject(
//                $this->project->name,
//                $this->command->source_language,
//                $projectInputParams['contentTypeId'],
//                $projectInputParams['industryId'],
//                $projectInputParams['processId'],
//                $projectInputParams['qualityLevelId']
//        );
        $masterProject = new MasterProject(
                $this->project->name,
                $this->command->source_language,
                1, 1, 1,1
        );
        $masterProject->setClientId($clientId);

        $dqfMasterProject = $this->repo->save($masterProject);

        foreach ( $this->project->getChunks() as $chunk ) {
            $struct = new DqfProjectMapStruct( [
                    'id'               => $id_project,
                    'id_job'           => $chunk->id,
                    'password'         => $chunk->password,
                    'first_segment'    => $chunk->job_first_segment,
                    'last_segment'     => $chunk->job_last_segment,
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
    private function submitProjectFiles(MasterProject $masterProject) {
        $files = \Files_FileDao::getByProjectId( $this->project->id );

        foreach ($files as $file) {
            $segmentsCount = $this->command->file_segments_count[ $file->id ];
            $clientId = Uuid::uuid4()->toString();

            $dqfFile = new File($file->filename, $segmentsCount);
            $dqfFile->setClientId($clientId);
            $masterProject->addFile($dqfFile);

            foreach ($this->project->getTargetLanguages() as $targetLanguage){
                $masterProject->assocTargetLanguageToFile($targetLanguage, $dqfFile);
            }
        }

        $this->repo->update($masterProject);
    }

    /**
     * @param MasterProject $masterProject
     */
    private function submitReviewSettings(MasterProject $masterProject) {
        $dqfQaModel = new DqfQualityModel($this->project);
        $reviewSettingsStruct = $dqfQaModel->getReviewSettings();

        $reviewSettings = new ReviewSettings($reviewSettingsStruct->reviewType);
        $reviewSettings->setTemplateName($reviewSettingsStruct->templateName);
        $reviewSettings->setPassFailThreshold(floatval($reviewSettingsStruct->passFailThreshold));
        $reviewSettings->setSampling($reviewSettingsStruct->sampling);

        foreach ($reviewSettingsStruct->errorCategoryIds as $errorCategoryId){
            $reviewSettings->addErrorCategoryId($errorCategoryId);
        }

        $severityWeights = json_decode($reviewSettingsStruct->severityWeights);
        foreach ($severityWeights as $severityWeight){
            $reviewSettings->addSeverityWeight(new Severity($severityWeight->severityId, $severityWeight->weight));
        }

        $masterProject->setReviewSettings($reviewSettings);

        $this->repo->update($masterProject);
    }

    /**
     * @param MasterProject $masterProject
     */
    private function submitSourceSegments(MasterProject $masterProject){

        $files = \Files_FileDao::getByProjectId( $this->project->id );

        $global_index = 1;
        foreach ($files as $index => $file) {
            $segments = ( new \Segments_SegmentDao())->getByFileId( $file->id ) ;
            $dqfFile = $masterProject->getFiles()[$index];

            foreach ( $segments as $segment ) {
                $masterProject->addSourceSegment(new SourceSegment(
                    $dqfFile,
                    $global_index,
                    $segment->segment
                ));

                $global_index++;
            }
        }

        $this->repo->update($masterProject);
    }
}



