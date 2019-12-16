<?php

namespace Features\Dqf\Command;

class SubmitRevisionCommand extends AbstractCommand implements CommandInterface {
    public $job_id;
    public $job_password;
    public $source_page;
    public $id_segment;
}
