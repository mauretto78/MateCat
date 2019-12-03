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
use Features\Dqf\Command\CreateReviewCommand;
use Features\Dqf\CommandHandler\CreateReviewCommandHandler;
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

//        try {
//
//            // 1. create Master Project
//            $command = new CreateMasterProjectCommand([
//                    'id_project' => 2,
//                    'source_language' => 'en-US',
//                    'file_segments_count' => [
//                            2 => 4
//                    ],
//            ]);
//            (new CreateMasterProjectCommandHandler())->handle($command);
//
//            // 2. Create Child Project translation
//            $command = new CreateChildProjectCommand([
//                    'job_id' => 2,
//                    'job_password' =>'aa28a6501983',
//                    'type' => 'translation',
//            ]);
//            (new CreateChildProjectCommandHandler())->handle($command);
//
//            // 3. Update translation batch
//            $command = new CreateTranslationBatchCommand([
//                    'job_id' => 2,
//                    'job_password' => 'aa28a6501983',
//            ]);
//            (new CreateTranslationBatchCommandHandler())->handle($command);
//
//            // 4. Update a single translation
//            $command = new UpdateSegmentTranslationCommand([
//                'job_id' => 2,
//                'job_password' => 'aa28a6501983',
//                'id_segment' => 6,
//            ]);
//            (new UpdateSegmentTranslationCommandHandler())->handle($command);
//
//            // 5. Create Child Project review (R1)
//            $command = new CreateChildProjectCommand([
//                    'job_id' => 2,
//                    'job_password' =>'aa28a6501983',
//                    'type' => 'review',
//            ]);
//            (new CreateChildProjectCommandHandler())->handle($command);
//
//            // 6. Update reviews
//            $command = new CreateReviewCommand([
//                    'job_id' => 2,
//                    'job_password' => 'aa28a6501983',
//                    'source_page' => 2,
//            ]);
//            (new CreateReviewCommandHandler())->handle($command);
//
//            // 7. Create another Child Project review (R2)
//            $command = new CreateChildProjectCommand([
//                    'job_id' => 2,
//                    'job_password' =>'aa28a6501983',
//                    'type' => 'review',
//            ]);
//            (new CreateChildProjectCommandHandler())->handle($command);
//
//            // 8. Update reviews
//            $command = new CreateReviewCommand([
//                    'job_id' => 2,
//                    'job_password' =>'aa28a6501983',
//                    'source_page' => 3,
//                    'id_segment' => 6,
//            ]);
//            (new CreateReviewCommandHandler())->handle($command);
//
//        } catch (\Exception $exception){
//            echo $exception->getMessage();
//        }
//
//        var_dump('OK');
//        die();


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
