<?php

namespace Features\Dqf\Command;

use Features\Dqf\Command\AbstractCommand;
use Features\Dqf\Command\CommandInterface;

class CreateReviewCommand extends AbstractCommand implements CommandInterface {
    public $id_job;
}