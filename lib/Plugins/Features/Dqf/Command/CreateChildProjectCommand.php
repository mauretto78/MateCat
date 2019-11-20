<?php

namespace Features\Dqf\Command;

class CreateChildProjectCommand extends AbstractCommand implements CommandInterface {
    public $job_id;
    public $job_password;
    public $type;
}