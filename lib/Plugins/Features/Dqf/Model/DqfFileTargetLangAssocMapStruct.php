<?php

namespace Features\Dqf\Model;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class DqfFileTargetLangAssocMapStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {
    public $dqf_id;
    public $dqf_file_id;
    public $dqf_target_lang_id;
    public $target_lang;
}