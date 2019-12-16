<?php

namespace Features;

use AbstractControllers\IController;
use AMQHandler;
use BasicFeatureStruct;
use Chunks_ChunkStruct;
use Exceptions\ValidationError;
use Features;
use Features\Dqf\Factory\UserRepositoryFactory;
use Features\Dqf\Model\RevisionChildProject;
use Features\Dqf\Utils\ProjectMetadata;
use Features\Dqf\Utils\SessionProviderService;
use Features\Dqf\Worker\CreateMasterProjectWorker;
use Features\Dqf\Worker\CreateTranslationOrRevisionProjectWorker;
use Features\Dqf\Worker\SubmitRevisionWorker;
use Features\Dqf\Worker\UpdateTranslationWorker;
use Features\ProjectCompletion\CompletionEventStruct;
use Features\ReviewExtended\Model\ArchivedQualityReportModel;
use INIT;
use Jobs_JobDao;
use Klein\Klein;
use Matecat\Dqf\Cache\BasicAttributes;
use Matecat\Dqf\Constants;
use Matecat\Dqf\Utils\DataEncryptor;
use Monolog\Logger;
use PHPTALWithAppend;
use Projects_ProjectDao;
use Projects_ProjectStruct;
use Translators\TranslatorsModel;
use Users_UserDao;
use Users_UserStruct;
use Utils;
use WorkerClient;

class Dqf extends BaseFeature {

    const FEATURE_CODE                      = 'dqf';
    const INTERMEDIATE_PROJECT_METADATA_KEY = 'dqf_intermediate_project';
    const INTERMEDIATE_USER_METADATA_KEY    = 'dqf_intermediate_user';

    protected $autoActivateOnProject = false;

    public static $dependencies = [
            Features::PROJECT_COMPLETION,
            Features::REVIEW_EXTENDED,
            Features::TRANSLATION_VERSIONS
    ];

    /**
     * @var Logger
     */
    protected static $logger;

    /**
     * @return \Monolog\Logger
     * @throws \Exception
     */
    public static function staticLogger() {
        if ( is_null( self::$logger ) ) {
            $feature      = new BasicFeatureStruct( [ 'feature_code' => self::FEATURE_CODE ] );
            self::$logger = ( new Dqf( $feature ) )->getLogger();
        }

        return self::$logger;
    }

    /**
     * @param PHPTALWithAppend $template
     * @param IController      $controller
     */
    public function decorateTemplate( PHPTALWithAppend $template, IController $controller ) {
        Features\Dqf\Utils\Functions::commonVarsForDecorator( $template );
    }

    public function filterUserMetadataFilters( $filters, $metadata ) {
        if ( isset( $metadata[ 'dqf_username' ] ) || isset( $metadata[ 'dqf_password' ] ) ) {
            $filters[ 'dqf_username' ] = [ 'filter' => FILTER_SANITIZE_STRING ];
            $filters[ 'dqf_password' ] = [ 'filter' => FILTER_SANITIZE_STRING ];
        }

        return $filters;
    }

    /**
     * @param $inputFilter
     *
     * @return array
     */
    public function filterCreateProjectInputFilters( $inputFilter ) {
        return array_merge( $inputFilter, ProjectMetadata::getInputFilter() );
    }

    /**
     * @param $metadata
     * @param $options
     *
     * @return array
     * @throws \Exception
     */
    public function createProjectAssignInputMetadata( $metadata, $options ) {
        $options = Utils::ensure_keys( $options, [ 'input' ] );

        $my_metadata = array_intersect_key( $options[ 'input' ], array_flip( ProjectMetadata::$keys ) );
        $my_metadata = array_filter( $my_metadata ); // <-- remove all `empty` array elements

        return array_merge( $my_metadata, $metadata );
    }

    /**
     * MARK AS COMPLETE
     *
     * @param Chunks_ChunkStruct    $chunk
     * @param CompletionEventStruct $params
     * @param                       $lastId
     *
     * @throws \Exception
     */
    public function project_completion_event_saved( Chunks_ChunkStruct $chunk, CompletionEventStruct $params, $lastId ) {
    }

    public function archivedQualityReportSaved( ArchivedQualityReportModel $archivedQRModel ) {

        // TODO: put this in a queue for background processing
        // CREATE CHILD REVIEW PROJECT HERE?????
        // IS ENQUEUING NEEDED (FOR BACKGROUND PROCESSING)?

        $revisionChildModel = new RevisionChildProject(
                $archivedQRModel->getChunk(),
                $archivedQRModel->getSavedRecord()->version
        );

        $revisionChildModel->createRemoteProjectsAndSubmit();
        $revisionChildModel->setCompleted();
    }

    public function filterCreationStatus( $result, Projects_ProjectStruct $project ) {
        $master_project_created = $project->getMetadataValue( 'dqf_master_project_creation_completed_at' );

        if ( $master_project_created ) {
            return $result;
        }

        return null;
    }

    /**
     * @param $features
     * @param $controller \NewController|\createProjectController
     *
     * @return mixed
     * @throws ValidationError
     */
    public function filterCreateProjectFeatures( $features, $controller ) {
        if ( isset( $controller->postInput[ 'dqf' ] ) && !!$controller->postInput[ 'dqf' ] ) {

            $attributesArray = [
                    BasicAttributes::CONTENT_TYPE  => $controller->postInput[ 'dqf_content_type' ],
                    BasicAttributes::INDUSTRY      => $controller->postInput[ 'dqf_industry' ],
                    BasicAttributes::PROCESS       => $controller->postInput[ 'dqf_process' ],
                    BasicAttributes::QUALITY_LEVEL => $controller->postInput[ 'dqf_quality_level' ],
            ];

            foreach ( $attributesArray as $key => $id ) {
                if ( false === BasicAttributes::existsById( $key, $id ) ) {
                    throw new ValidationError( 'input validation failed: ' . $id . ' is not a valid value for ' . $key . ' attribute.' );
                }
            }

            $features[ Features::DQF ] = new BasicFeatureStruct( [ 'feature_code' => Features::DQF ] );
        }

        return $features;
    }

    public function filterNewProjectInputFilters( $inputFilter ) {
        return array_merge( $inputFilter, ProjectMetadata::getInputFilter() );
    }

    /**
     * Create a Master Project on DQF
     *
     * @param $projectStructure
     *
     * @throws \Exception
     */
    public function postProjectCommit( $projectStructure ) {
        $params = [
                'id_project'          => $projectStructure[ 'id_project' ],
                'source_language'     => $projectStructure[ 'source_language' ],
                'file_segments_count' => $projectStructure[ 'file_segments_count' ]
        ];

        WorkerClient::init( new AMQHandler() );
        WorkerClient::enqueue( 'DQF', CreateMasterProjectWorker::class, $params );
    }

    public static function loadRoutes( Klein $klein ) {
    }

    /**
     * Define if a project is completable.
     *
     * @param                    $value
     * @param Chunks_ChunkStruct $chunk
     * @param Users_UserStruct   $user
     *
     * @return bool
     */
    public function filterJobCompletable( $value, Chunks_ChunkStruct $chunk, Users_UserStruct $user, $isRevision ) {
        $authModel = new Features\Dqf\Model\CatAuthorizationModel( $chunk, $isRevision );

        return $value && $authModel->isUserAuthorized( $user );
    }

    /**
     * Check the input metadata array to see if this feature is enabled for a given project.
     * If so, include the project dependencies in the list.
     *
     * @param $dependencies
     * @param $metadata
     *
     * @return array
     */
    public function filterProjectDependencies( $dependencies, $metadata ) {
        if ( isset( $metadata[ self::FEATURE_CODE ] ) && $metadata[ self::FEATURE_CODE ] ) {
            $dependencies = array_merge( $dependencies, static::getDependencies() );
        }

        return $dependencies;
    }

    /**
     * @param $projectStructure
     *
     * @throws \Exception
     */
    public function validateProjectCreation( $projectStructure ) {

        // define errors
        $error_user_not_set       = [ 'code' => -1000, 'message' => 'DQF user is not set' ];
        $error_session_id_not_set = [ 'code' => -1000, 'message' => 'DQF sessionId not set.' ];
        $error_on_remote_login    = [ 'code' => -1000, 'message' => 'DQF credentials are not correct.' ];
        $multilang_error          = [ 'code' => -1000, 'message' => 'Cannot create multilanguage projects when DQF option is enabled' ];

        if ( count( $projectStructure[ 'target_language' ] ) > 1 ) {
            $projectStructure[ 'result' ][ 'errors' ][] = $multilang_error;

            return;
        }

        if ( empty( $projectStructure[ 'id_customer' ] ) ) {
            $projectStructure[ 'result' ][ 'errors' ][] = $error_user_not_set;

            return;
        }

        $user = ( new Users_UserDao() )->setCacheTTL( 3600 )->getByEmail( $projectStructure[ 'id_customer' ] );

        if ( !$user ) {
            $projectStructure[ 'result' ][ 'errors' ][] = $error_user_not_set;

            return;
        }

        $userRepo = UserRepositoryFactory::create();
        $dqfUser  = $userRepo->getByExternalId( $user->getUid() );

        if ( false === $dqfUser or null === $dqfUser ) {
            $projectStructure[ 'result' ][ 'errors' ][] = $error_user_not_set;

            return;
        }

        if ( empty( $dqfUser->getSessionId() ) ) {
            $projectStructure[ 'result' ][ 'errors' ][] = $error_session_id_not_set;

            return;
        }

        if ( false === $dqfUser->isSessionStillValid() ) {

            $dataEncryptor = new DataEncryptor( \INIT::$DQF_ENCRYPTION_KEY, \INIT::$DQF_ENCRYPTION_IV );

            try {
                if ( $dqfUser->isGeneric() ) {
                    SessionProviderService::getAnonymous( $dataEncryptor->decrypt( $dqfUser->getGenericEmail() ), $dqfUser->getExternalReferenceId() );
                } else {
                    SessionProviderService::get( $dqfUser->getExternalReferenceId(), $dataEncryptor->decrypt( $dqfUser->getUsername() ), $dataEncryptor->decrypt( $dqfUser->getPassword() ) );
                }
            } catch ( \Exception $exception ) {
                $projectStructure[ 'result' ][ 'errors' ][] = $error_on_remote_login;

                return;
            }
        }

        // At this point we are sure ReviewExtended::loadAndValidateModelFromJsonFile was called already
        // @see FeatureSet::getSortedFeatures

        if ( $projectStructure[ 'features' ][ 'review_extended' ][ '__meta' ][ 'qa_model' ] ) {
            // override QA model
            $projectStructure[ 'features' ][ 'review_extended' ][ '__meta' ][ 'qa_model' ] = json_decode(
                    file_get_contents( INIT::$ROOT . '/inc/dqf/qa_model.json' ), true
            );
        }
    }

    /**
     * DQF projects can be split only one time.
     *
     * @param $projectStructure
     * @throws \Exception
     */
    public function postJobSplitted( $projectStructure ) {
        $params = [
                'job_type'     => Constants::PROJECT_TYPE_TRANSLATION,
                'job_id'       => $projectStructure[ 'job_to_split' ],
                'job_password' => $projectStructure[ 'job_to_split_pass' ],
        ];

        \WorkerClient::init( new \AMQHandler() );
        \WorkerClient::enqueue( 'DQF', CreateTranslationOrRevisionProjectWorker::class, $params );
    }

    /**
     * Create a translation project on DQF after analysis is completed
     *
     * @param $project_id
     * @param $_analyzed_report
     *
     * @throws \Exception
     * @see TMAnalysisWorker::_tryToCloseProject()
     */
    public function afterTMAnalysisCloseProject( $project_id, $_analyzed_report ) {
        $project = Projects_ProjectDao::findById( $project_id, 300 );
        $emailOwner = $project->getOriginalOwner()->getEmail();

        foreach ( $project->getChunks() as $chunk ) {
            $params = [
                    'job_type'     => Constants::PROJECT_TYPE_TRANSLATION,
                    'job_id'       => $chunk->id,
                    'job_password' => $chunk->password,
            ];

            // set the original owner as the translator of the project
            $translatorsModel = new TranslatorsModel( $chunk );
            $translatorsModel->setEmail( $emailOwner );
            $translatorsModel->saveJob();

            \WorkerClient::init( new \AMQHandler() );
            \WorkerClient::enqueue( 'DQF', CreateTranslationOrRevisionProjectWorker::class, $params );
        }
    }

    /**
     * @param $params
     *
     * @throws \StompException
     * @throws \Exception
     */
    public function setTranslationCommitted( $params ) {

        /** @var Chunks_ChunkStruct $chunk */
        $chunk = $params[ 'chunk' ];

        /** @var \Segments_SegmentStruct $segment */
        $segment = $params[ 'segment' ];

        $workerParams = [
                'job_id'       => (int)$chunk->id,
                'job_password' => $chunk->password,
                'id_segment'   => (int)$segment->id
        ];

        WorkerClient::init( new AMQHandler() );

        if ( $params[ 'source_page_code' ] === 1 ) {
            WorkerClient::enqueue( 'DQF', UpdateTranslationWorker::class, $workerParams );
        } else {
            $workerParams = array_merge( [ 'source_page' => $params[ 'source_page_code' ] ], $workerParams );
            WorkerClient::enqueue( 'DQF', SubmitRevisionWorker::class, $workerParams );
        }
    }
}
