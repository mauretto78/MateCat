<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/09/2017
 * Time: 15:57
 */

namespace Features\Dqf\Controller\API;

use API\App\AbstractStatefulKleinController;
use Chunks_ChunkDao;
use Exceptions\NotFoundException;
use Features\Dqf\Model\CatAuthorizationModel;
use Features\Dqf\Model\UserModel;
use Features\Dqf\Service\Authenticator;
use Features\Dqf\Utils\UserMetadata;
use LQA\ChunkReviewDao;
use Users\MetadataDao;

class GenericController extends AbstractStatefulKleinController {

    // Lock the job to the DQF user
    public function assignProject() {
        $aut = $this->getAuthorizationModelByCurrentStatus();
        $aut->assignJobToUser( $this->getUser() );
        $this->response->code( 200 );
    }

    /**
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exception
     */
    public function clearCredentials() {
//        $user =  $this->getUser() ;
//        $dao = new MetadataDao();
//
//        $dqfSession  = $dao->get( $user->getUid(), UserMetadata::DQF_SESSION_ID )->value;
//        $dqfEmail  = $dao->get( $user->getUid(), UserMetadata::DQF_USERNAME_KEY )->value;
//
//        $logout = ( new Authenticator() )->logout($dqfEmail, $dqfSession);

        UserMetadata::clearCredentials( $this->getUser() );
        $this->response->code( 200 );
    }

    public function revokeAssignment() {
        if ( !in_array( $this->request->page, [ 'translate', 'revise' ] ) ) {
            throw new NotFoundException();
        }

        $chunk = Chunks_ChunkDao::getByIdAndPassword( $this->request->id_job, $this->request->password );

        $is_review = ( $this->request->page == 'translate' ? false : true );

        $aut = new CatAuthorizationModel( $chunk->getJob(), $is_review );
        $aut->revokeAssignment();
        $this->response->code( 200 );
    }

    private function getAuthorizationModelByCurrentStatus() {
        $reviewItem = ChunkReviewDao::findByReviewPasswordAndJobId( $this->request->password, $this->request->id_job );

        if ( !$reviewItem ) {
            $chunk = Chunks_ChunkDao::getByIdAndPassword( $this->request->id_job, $this->request->password );
        } else {
            $chunk = $reviewItem->getChunk();
        }

        return new CatAuthorizationModel( $chunk->getJob(), !!$reviewItem );
    }

}