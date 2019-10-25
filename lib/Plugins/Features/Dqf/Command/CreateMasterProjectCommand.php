<?php

namespace Features\Dqf\Command;

class CreateMasterProjectCommand extends AbstractCommand implements CommandInterface {
    public $id_project ;
    public $source_language ;
    public $file_segments_count ;
}