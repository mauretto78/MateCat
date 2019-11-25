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
use Translators\TranslatorsModel;

class TranslatorController extends AbstractStatefulKleinController {

    /**
     * Assign a translator to a job
     * and obtain a valid DQF sessionId
     */
    public function assign() {
        $params = $this->request->paramsPost();

        // params
        $dqfUser   = $params->get( 'translator_dqf_username' );
        $dqfPass   = $params->get( 'translator_dqf_password' );
        $email     = $params->get( 'translator_email' );
        $firstName = $params->get( 'translator_first_name' );
        $lastName  = $params->get( 'translator_last_name' );
        $jobId     = $params->get( 'job_id' );
        $jobPass   = $params->get( 'job_password' );

        if ( null === $email ) {
            $this->returnJsonReponse( 'User email was not provided', 403 );
        }

        if ( null === $jobId and null === $jobPass ) {
            $this->returnJsonReponse( 'Job Id and password were not provided', 403 );
        }

        // get user
        $userDao = new \Users_UserDao();
        $user    = $userDao->getByEmail( $email );

        // create a new user if it was not found
        if ( null === $user ) {
            $user = $userDao->createUser($this->createUserStruct($email, $firstName, $lastName));
        }

        try {
            $sessionProvider = SessionProviderFactory::create();
            if( null !== $dqfUser and null !== $dqfPass ){
                // perform login
                $sessionId = $sessionProvider->create( [
                        'externalReferenceId' => $user->getUid(),
                        'username'            => $dqfUser,
                        'password'            => $dqfPass,
                        'isGeneric'           => false,
                ] );
            } else {
                // perform anonymous login
                $sessionId = $sessionProvider->create( [
                        'externalReferenceId' => $user->getUid(),
                        'username'            => \INIT::$DQF_GENERIC_USERNAME,
                        'password'            => \INIT::$DQF_GENERIC_PASSWORD,
                        'isGeneric'           => true,
                        'genericEmail'        => $user->getEmail(),
                ] );
            }

            // assign the job to the translator
            $chunk = \Chunks_ChunkDao::getByIdAndPassword($jobId, $jobPass);

            $translatorsModel = new TranslatorsModel($chunk);
            $translatorsModel->setEmail($user->getEmail());
            $translatorsModel->saveJob();

            $this->returnJsonReponse( 'Successfully logged in as anonymous to DQF with sessionId: ' . $sessionId, 200 );
        } catch ( \Exception $e ) {
            $this->returnJsonReponse( $e->getMessage(), 500 );
        }
    }

    /**
     * @param $email
     * @param $firstName
     * @param $lastName
     *
     * @return \Users_UserStruct
     */
    private function createUserStruct($email, $firstName, $lastName) {
        $user = new \Users_UserStruct();
        $user->email = $email;
        $user->first_name = $firstName;
        $user->last_name = $lastName;

        $salt = \Utils::randomString() ;
        $pass = \Utils::encryptPass( 'cambiami123', $salt ) ;

        $user->salt = $salt;
        $user->pass = $pass;

        return $user;
    }

    /**
     * @param string $message
     * @param int    $code
     */
    private function returnJsonReponse( $message, $code = 200 ) {
        $this->response->code( $code );
        $this->response->json( [
                'status' => ($code === 200) ? 'OK': 'Error',
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

