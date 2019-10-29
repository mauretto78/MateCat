<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 11/07/2017
 * Time: 15:19
 */

namespace Features\Dqf\Model;

use Chunks_ChunkStruct;
use DataAccess_AbstractDao;
use Database;
use PDO;

class DqfFileMapDao extends DataAccess_AbstractDao  {
    const TABLE       = "dqf_files_map";
    const STRUCT_TYPE = "\Features\Dqf\Model\DqfFileMapStruct";
}