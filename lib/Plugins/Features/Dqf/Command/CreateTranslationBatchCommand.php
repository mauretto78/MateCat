<?php

namespace Features\Dqf\Command;

use Features\Dqf\Command\AbstractCommand;
use Features\Dqf\Command\CommandInterface;

class CreateTranslationBatchCommand extends AbstractCommand implements CommandInterface {
    public $job_id;
    public $job_password;
}