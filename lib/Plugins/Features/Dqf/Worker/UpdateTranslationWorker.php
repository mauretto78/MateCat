<?php


namespace Features\Dqf\Worker;

use Features\Dqf\Command\CreateChildProjectCommand;
use Features\Dqf\Command\CreateTranslationBatchCommand;
use Features\Dqf\Command\UpdateSegmentTranslationCommand;
use Features\Dqf\CommandHandler\CreateChildProjectCommandHandler;
use Features\Dqf\CommandHandler\CreateTranslationBatchCommandHandler;
use Features\Dqf\CommandHandler\UpdateSegmentTranslationCommandHandler;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\EndQueueException;

class UpdateTranslationWorker extends AbstractWorker {

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

        /** Wait to ensure slave databases are up to date. */
        sleep( 4 );

        try {
            $command = new UpdateSegmentTranslationCommand($params);
            (new UpdateSegmentTranslationCommandHandler())->handle($command);
        } catch ( \Exception $e ) {
            throw new EndQueueException( $e->getMessage() );
        }
    }
}
