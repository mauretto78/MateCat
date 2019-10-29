<?php

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use Dqf\SessionProviderFactory;

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

        $command = new \Features\Dqf\Command\CreateMasterProjectCommand();
        $command->id_project = 1;
        $command->source_language = 'it-IT';
        $command->file_segments_count[1] = 4;

        (new \Features\Dqf\CommandHandler\CreateMasterProjectCommandHandler())->handle($command);

        echo 'dsadsa'; die();




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
