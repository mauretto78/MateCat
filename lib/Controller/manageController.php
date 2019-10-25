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

        // login
        $sessionId = \Features\Dqf\Utils\SessionProviderService::getAnonymous('mauro@translated.net');
        //$sessionId = \Dqf\SessionProviderService::get(23, 'luca.defranceschi@translated.net', 'lucFran@Tran!1');

        // save user in session



        // repo
        $client = \Features\Dqf\Utils\Factory\ClientFactory::create();
        $repo = new \Matecat\Dqf\Repository\Api\MasterProjectRepository($client, $sessionId, 'mauro@translated.net');

        $masterProject = new \Matecat\Dqf\Model\Entity\MasterProject(
                'eeeeee',
                'it-IT',
                1,2,1,1
        );

        // file(s)
        $file = new \Matecat\Dqf\Model\Entity\File('test-file', 3);
        $file->setClientId('rewrewrew');
        $masterProject->addFile($file);

        // assoc targetLang to file(s)
        $masterProject->assocTargetLanguageToFile('en-US', $file);
        $masterProject->assocTargetLanguageToFile('fr-FR', $file);

        // review settings
        $reviewSettings = new \Matecat\Dqf\Model\Entity\ReviewSettings(\Matecat\Dqf\Constants::REVIEW_TYPE_COMBINED);
        $reviewSettings->setErrorCategoryIds0(1);
        $reviewSettings->setErrorCategoryIds1(2);
        $reviewSettings->setErrorCategoryIds2(3);
        $reviewSettings->setSeverityWeights('[{"severityId":"1","weight":1}, {"severityId":"2","weight":2}, {"severityId":"3","weight":3}, {"severityId":"4","weight":4}]');
        $reviewSettings->setPassFailThreshold(0.00);
        $masterProject->setReviewSettings($reviewSettings);

        $project = $repo->save($masterProject);

        var_dump($project->getTargetLanguageAssoc() );
        $repo->delete($project);

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
