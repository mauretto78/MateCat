<?php


namespace Features\Dqf\Worker;

use Features\Dqf\Command\CreateMasterProjectCommand;
use Features\Dqf\CommandHandler\CreateMasterProjectCommandHandler;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\EndQueueException;

class CreateProjectWorker extends AbstractWorker {

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

        /** Wait to ensure slave databases are up to date. */
        sleep( 4 );

        try {
            $command = new CreateMasterProjectCommand( json_decode( $queueElement->params, true ) );
            ( new CreateMasterProjectCommandHandler() )->handle( $command );
        } catch ( \Exception $e ) {
            throw new EndQueueException( $e->getMessage() );
        }
    }
}
