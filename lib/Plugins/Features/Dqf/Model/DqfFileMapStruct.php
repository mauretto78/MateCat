<?php

namespace Features\Dqf\Model;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class DqfFileMapStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {
    public $id;
    public $file_id;
    public $file_name;
    public $dqf_id;
    public $dqf_client_id;
}