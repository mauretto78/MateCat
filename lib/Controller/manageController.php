<?php

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use Features\Dqf\Command\CreateMasterProjectCommand;
use Features\Dqf\Command\CreateChildProjectCommand;
use Features\Dqf\CommandHandler\CreateMasterProjectCommandHandler;
use Features\Dqf\CommandHandler\CreateChildProjectCommandHandler;
use Features\Dqf\CommandHandler\CreateTranslationBatchCommandHandler;
use Features\Dqf\CommandHandler\UpdateSegmentTranslationCommandHandler;
use Features\Dqf\Command\CreateTranslationBatchCommand;
use Features\Dqf\Command\SubmitRevisionCommand;
use Features\Dqf\CommandHandler\SubmitRevisionCommandHandler;
use Features\Dqf\Command\UpdateSegmentTranslationCommand;

class manageController extends viewController {

    public $notAllCancelled = 0;

    protected $_outsource_login_API = '//signin.translated.net/';

    protected $login_required = true;

    public function __construct() {
        parent::__construct();

        parent::makeTemplate( "manage.html" );

        $this->lang_handler = Langs_Languages::getInstance();

        $this->featureSet->loadFromUserEmail( $this->user->email );
    }

    public function doAction() {

        //d31d5e62e491

        try {

            // 1. create Master Project
            $command = new CreateMasterProjectCommand([
                    'id_project' => 9,
                    'source_language' => 'it-IT',
                    'file_segments_count' => [
                            9 => 4
                    ],
            ]);
            (new CreateMasterProjectCommandHandler())->handle($command);

//            // 2. Create Child Project translation
//            $command = new CreateChildProjectCommand([
//                    'job_id' => 9,
//                    'job_password' =>'d31d5e62e491',
//                    'type' => 'translation',
//            ]);
//            (new CreateChildProjectCommandHandler())->handle($command);
//
//            // 3. Update translation batch
//            $command = new CreateTranslationBatchCommand([
//                    'job_id' => 9,
//                    'job_password' => 'd31d5e62e491',
//            ]);
//            (new CreateTranslationBatchCommandHandler())->handle($command);
//
//            // 4. Update a single translation
//            $command = new UpdateSegmentTranslationCommand([
//                'job_id' => 9,
//                'job_password' => 'd31d5e62e491',
//                'id_segment' => 39,
//            ]);
//            (new UpdateSegmentTranslationCommandHandler())->handle($command);
//
//            // 5. Create Child Project review (R1)
//            $command = new CreateChildProjectCommand([
//                    'job_id' => 9,
//                    'job_password' => 'd31d5e62e491',
//                    'type' => 'review',
//            ]);
//            (new CreateChildProjectCommandHandler())->handle($command);
//
//            // 6. Update a review
//            $command = new CreateRevisionCommand([
//                    'job_id' => 9,
//                    'job_password' => 'd31d5e62e491',
//                    'source_page' => 2,
//                    'id_segment' => 41,
//            ]);
//            (new CreateRevisionCommandHandler())->handle($command);
//
//            // 7. Create another Child Project review (R2)
//            $command = new CreateChildProjectCommand([
//                    'job_id' => 9,
//                    'job_password' => 'd31d5e62e491',
//                    'type' => 'review',
//            ]);
//            (new CreateChildProjectCommandHandler())->handle($command);
//
//            // 8. Update reviews
//            $segment_ids = [41];
//            $reviewCommandHandler = new SubmitRevùisionCommandHandler();
//            foreach ($segment_ids as $segment_id){
//                $command = new SubmitRevisionCommand([
//                        'job_id' => 9,
//                        'job_password' => 'd31d5e62e491',
//                        'source_page' => 2,
//                        'id_segment' => $segment_id,
//                ]);
//
//                $reviewCommandHandler->handle($command);
//            }

        } catch (\Exception $exception){
            echo $exception->getMessage();
        }

        var_dump('OK');
        die();


        $this->featureSet->filter( 'beginDoAction', $this );

        $this->checkLoginRequiredAndRedirect();

        $activity             = new ActivityLogStruct();
        $activity->action     = ActivityLogStruct::ACCESS_MANAGE_PAGE;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );
    }

    public function setTemplateVars() {

        $this->template->outsource_service_login = $this->_outsource_login_API;
        $this->decorator = new ManageDecorator( $this, $this->template );
        $this->decorator->decorate();

        $this->featureSet->appendDecorators(
                'ManageDecorator',
                $this,
                $this->template
        );
    }

}
