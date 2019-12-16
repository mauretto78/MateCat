<?php

namespace Features\Dqf\Command;

class CreateTranslationBatchCommand extends AbstractCommand implements CommandInterface {
    public $job_id;
    public $job_password;
}