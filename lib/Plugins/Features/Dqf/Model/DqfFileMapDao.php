<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 11/07/2017
 * Time: 15:19
 */

namespace Features\Dqf\Model;

use DataAccess_AbstractDao;
use Database;
use PDO;

class DqfFileMapDao extends DataAccess_AbstractDao  {
    const TABLE       = "dqf_files_map";
    const STRUCT_TYPE = "\Features\Dqf\Model\DqfFileMapStruct";


    /**
     * @param $id_file
     * @param $id_job
     *
     * @return DqfFileMapStruct
     */
    public function findOne( $id_file, $id_job ) {

        $sql = "SELECT * FROM ".self::TABLE."
                  JOIN files ON ".self::TABLE.".file_id = files.id
                  JOIN projects ON files.id_project=projects.id
                  JOIN jobs ON projects.id=jobs.id_project
                  WHERE file_id = :file_id
                  AND jobs.id = :job_id
                  ORDER BY ".self::TABLE.".id " ;

        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, self::STRUCT_TYPE );

        $stmt->execute([
            'file_id' => $id_file,
            'job_id' => $id_job,
        ]);

        return $stmt->fetch();
    }
}