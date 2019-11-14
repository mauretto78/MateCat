<?php

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use Features\Dqf\Command\CreateMasterProjectCommand;
use Features\Dqf\Command\CreateChildProjectCommand;
use Features\Dqf\CommandHandler\CreateMasterProjectCommandHandler;
use Features\Dqf\CommandHandler\CreateChildProjectCommandHandler;
use Features\Dqf\CommandHandler\CreateTranslationBatchCommandHandler;
use Features\Dqf\Command\CreateTranslationBatchCommand;

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

        try {

            // 1. create Master Project
            $command = new CreateMasterProjectCommand([
                    'id_project' => 2,
                    'source_language' => 'en-US',
                    'file_segments_count' => [
                            2 => 4
                    ],
            ]);

            (new CreateMasterProjectCommandHandler())->handle($command);


            // 2. Create Child Project translation
            $command = new CreateChildProjectCommand([
                    'id_job' => 2,
                    'type' => 'translation',

            ]);
            (new CreateChildProjectCommandHandler())->handle($command);

            // 3. Update translation batch
            $command = new CreateTranslationBatchCommand([
                    'id_job' => 2,
            ]);
            (new CreateTranslationBatchCommandHandler())->handle($command);

            // 4. Create Child Project review


            // 5. Update reviews

        } catch (\Exception $exception){
            echo $exception->getMessage();
        }




//        $command = new CreateTranslationBatchCommand([
//                'id_job' => 6,
//                'id_file' => 6,
//        ]);
//
//        (new \Features\Dqf\CommandHandler\CreateTranslationBatchCommandHandler())->handle($command);

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
