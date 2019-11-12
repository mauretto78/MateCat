<?php

namespace Features\Dqf\Command;

use Features\Dqf\Command\AbstractCommand;
use Features\Dqf\Command\CommandInterface;

class UpdateSegmentTranslationCommand extends AbstractCommand implements CommandInterface {
    public $id_job;
    public $id_file;
    public $id_segment;
}