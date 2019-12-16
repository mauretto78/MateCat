<?php

namespace Features\Dqf\Command;

class UpdateSegmentTranslationCommand extends AbstractCommand implements CommandInterface {
    public $job_id;
    public $job_password;
    public $id_segment;
    public $logged_user_id;
}