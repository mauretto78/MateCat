<?php

namespace Features\Dqf\Command;

use Features\Dqf\Command\AbstractCommand;
use Features\Dqf\Command\CommandInterface;

class UpdateSegmentTranslationCommand extends AbstractCommand implements CommandInterface {
    public $job_id;
    public $job_password;
    public $id_segment;
}