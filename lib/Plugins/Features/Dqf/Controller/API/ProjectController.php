<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/09/2017
 * Time: 15:57
 */

namespace Features\Dqf\Controller\API;

use API\App\AbstractStatefulKleinController;
use Features\Dqf\Factory\SessionProviderFactory;
use Features\Dqf\Worker\CreateTranslationOrRevisionProjectWorker;
use Matecat\Dqf\Constants;
use Translators\TranslatorsModel;

class ProjectController extends AbstractStatefulKleinController {

    /**
     * Login the translator and then
     * create a translation or revision project (in background)
     */
    public function create() {
        $params = $this->request->paramsPost();

        // params
        $dqfUser    = $params->get( 'translator_dqf_username' ); // OPTIONAL
        $dqfPass    = $params->get( 'translator_dqf_password' ); // OPTIONAL
        $email      = $params->get( 'translator_email' );        // REQUIRED
        $jobId      = $params->get( 'job_id' );                  // REQUIRED
        $jobPass    = $params->get( 'job_password' );            // REQUIRED
        $jobType    = $params->get( 'job_type' );                // REQUIRED
        $sourcePage = $params->get( 'source_page' );             // OPTIONAL

        if ( null === $email ) {
            $this->returnJsonReponse( 'User email was not provided', 403 );
        }

        if ( null === $jobId and null === $jobPass ) {
            $this->returnJsonReponse( 'Job Id and password were not provided', 403 );
        }

        $allowedJobTypes = [ Constants::PROJECT_TYPE_TRANSLATION, Constants::PROJECT_TYPE_REVIEW ];
        if ( null === $jobType or false === in_array( $allowedJobTypes, $jobType ) ) {
            $this->returnJsonReponse( 'Job type not specified or invalid. Allowed values are: ' . implode( ',', $allowedJobTypes ), 403 );
        }

        $chunk = \Chunks_ChunkDao::getByIdAndPassword( $jobId, $jobPass );
        if ( false === $chunk ) {
            $this->returnJsonReponse( 'No chunk found with id ' . $jobId . ' and password ' . $jobPass, 403 );
        }

        // get Matecat User
        $userDao         = new \Users_UserDao();
        $user            = $userDao->getByEmail( $email );
        $userId          = ( null !== $user ) ? $user->getUid() : null;
        $sessionProvider = SessionProviderFactory::create();

        try {
            if ( null !== $dqfUser and null !== $dqfPass ) {
                // login with credentials
                $sessionId = $sessionProvider->create( [
                        'externalReferenceId' => $userId,
                        'username'            => $dqfUser,
                        'password'            => $dqfPass,
                        'isGeneric'           => false,
                ] );
            } else {
                // anonymous login
                $sessionId = $sessionProvider->create( [
                        'externalReferenceId' => $userId,
                        'username'            => \INIT::$DQF_GENERIC_USERNAME,
                        'password'            => \INIT::$DQF_GENERIC_PASSWORD,
                        'isGeneric'           => true,
                        'genericEmail'        => $email,
                ] );
            }

            $translatorsModel = new TranslatorsModel( $chunk );
            $translatorsModel->setEmail( $email );
            $translatorsModel->saveJob();

            // create the translation child project on DQF
            $params = [
                    'job_type'     => $jobType,
                    'job_id'       => $jobId,
                    'job_password' => $jobPass,
                    'source_page'  => $sourcePage,
            ];

            \WorkerClient::init( new \AMQHandler() );
            \WorkerClient::enqueue( 'DQF', CreateTranslationOrRevisionProjectWorker::class, $params );

            $message = 'Successfully logged in';

            if ( null === $dqfUser and null === $dqfPass ) {
                $message .= 'as anonymous';
            }

            $message .= ' to DQF with sessionId: ' . $sessionId . '. The project creation was enqueued.';

            $this->returnJsonReponse( $message, 200 );
        } catch ( \Exception $e ) {
            $this->returnJsonReponse( $e->getMessage(), 500 );
        }
    }

    /**
     * @param string $message
     * @param int    $code
     */
    private function returnJsonReponse( $message, $code = 200 ) {
        $this->response->code( $code );
        $this->response->json( [
                'status'  => ( $code === 200 ) ? 'OK' : 'Error',
                'message' => $message
        ] );

        \Log::doJsonLog( [
                'request_uri'      => $this->request->uri(),
                'request_params'   => $this->request->paramsPost(),
                'response_code'    => $code,
                'response_message' => $message,
        ] );

        exit();
    }
}

