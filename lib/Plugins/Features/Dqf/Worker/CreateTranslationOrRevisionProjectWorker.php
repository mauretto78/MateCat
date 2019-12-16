<?php


namespace Features\Dqf\Worker;

use Features\Dqf\Command\CreateChildProjectCommand;
use Features\Dqf\Command\CreateTranslationBatchCommand;
use Features\Dqf\Command\SubmitRevisionCommand;
use Features\Dqf\CommandHandler\CreateChildProjectCommandHandler;
use Features\Dqf\CommandHandler\CreateTranslationBatchCommandHandler;
use Features\Dqf\CommandHandler\SubmitRevisionCommandHandler;
use Matecat\Dqf\Constants;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\EndQueueException;

class CreateTranslationOrRevisionProjectWorker extends AbstractWorker {

    /**
     * @var string
     */
    protected $sourceLanguageCode;

    /**
     * @var int
     */
    protected $reQueueNum = 0; // stop at first error

    /**
     * @var QueueElement
     */
    protected $queueElement;

    /**
     * @param AbstractElement $queueElement
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function process( AbstractElement $queueElement ) {

        $this->queueElement = $queueElement;
        $this->_checkForReQueueEnd( $this->queueElement );
        $this->_checkDatabaseConnection();
        $params = json_decode( $queueElement->params, true );

        $type       = $params[ 'job_type' ];
        $jobId      = $params[ 'job_id' ];
        $jobPass    = $params[ 'job_password' ];
        $sourcePage = (isset($params[ 'source_page' ])) ? $params[ 'source_page' ] : 2;

        /** Wait to ensure slave databases are up to date. */
        sleep( 4 );

        try {
            // Create translation project
            // Update the translation batch
            if ( $type === Constants::PROJECT_TYPE_TRANSLATION ) {
                $command = new CreateChildProjectCommand( [
                        'type'         => $type,
                        'job_id'       => $jobId,
                        'job_password' => $jobPass,
                ] );
                ( new CreateChildProjectCommandHandler() )->handle( $command );

                $command = new CreateTranslationBatchCommand( [
                        'job_id'       => $jobId,
                        'job_password' => $jobPass,
                ] );
                ( new CreateTranslationBatchCommandHandler() )->handle( $command );
            } // Create Revision project
            elseif ( $type === Constants::PROJECT_TYPE_REVIEW ) {
                $command = new CreateChildProjectCommand( [
                        'type'         => $type,
                        'job_id'       => $jobId,
                        'job_password' => $jobPass,
                ] );
                ( new CreateChildProjectCommandHandler() )->handle( $command );

                $chunk =  \Chunks_ChunkDao::getByIdAndPassword($jobId, $jobPass);

                // loop all segments
                foreach ($chunk->getSegments() as $segment){
                    $command = new SubmitRevisionCommand( [
                            'source_page'  => $sourcePage,
                            'id_segment'   => $segment->id,
                            'job_id'       => $jobId,
                            'job_password' => $jobPass,
                    ] );
                    ( new SubmitRevisionCommandHandler() )->handle( $command );
                }
            }
        } catch ( \Exception $e ) {
            throw new EndQueueException( $e->getMessage() );
        }
    }
}
