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
