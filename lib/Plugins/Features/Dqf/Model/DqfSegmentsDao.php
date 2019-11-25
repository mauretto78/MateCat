<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/07/2017
 * Time: 12:10
 */

namespace Features\Dqf\Model;


use DataAccess_AbstractDao;
use PDO;

class DqfSegmentsDao extends DataAccess_AbstractDao {

    const TABLE = 'dqf_segments';

    protected static $primary_keys         = [ 'id_segment' ];
    protected static $auto_increment_field = [];

    /**
     * @param $id_segment
     *
     * @return DqfSegmentsStruct
     */
    public function getByIdSegment( $id_segment ) {
        $sql = "SELECT * FROM dqf_segments WHERE id_segment = ?";

        $conn = $this->getDatabaseHandler()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [ $id_segment ] );

        $stmt->setFetchMode( PDO::FETCH_CLASS, '\Features\Dqf\Model\DqfSegmentsStruct' );

        return $stmt->fetch();
    }

    /**
     * Returns a map that is an array whith key = id_segment and value = dqf_id_seg ;
     *
     * @param $min
     * @param $max
     *
     * @return array
     */
    public function getByIdSegmentRange( $min, $max ) {
        $sql = "SELECT * FROM dqf_segments WHERE id_segment >= ? AND id_segment <= ? ";

        $conn = $this->getDatabaseHandler()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [ $min, $max ] );

        $stmt->setFetchMode( PDO::FETCH_CLASS, '\Features\Dqf\Model\DqfSegmentsStruct' );

        $result = [];
        while ( $row = $stmt->fetch() ) {
            $result[ $row->id_segment ] = [
                    'dqf_segment_id'     => $row->dqf_segment_id,
                    'dqf_translation_id' => $row->dqf_translation_id
            ];
        }

        return $result;
    }

    /**
     * @param array $structs
     *
     * @throws \Exception
     */
    public function insertBulkMapForTranslationId( array $structs ) {
        $sql = " INSERT INTO dqf_segments (id_segment, dqf_translation_id) VALUES ";
        $sql .= implode( ', ', array_fill( 0, count( $structs ), " ( ?, ? ) " ) );
        $sql .= " ON DUPLICATE KEY UPDATE dqf_segments.dqf_translation_id = VALUES(dqf_segments.dqf_translation_id) ";

        $conn = $this->getDatabaseHandler()->getConnection();

        $stmt             = $conn->prepare( $sql );
        $flattened_values = array_reduce( $structs, 'array_merge', [] );

        $result = $stmt->execute( $flattened_values );

        if ( !$result ) {
            throw new \Exception( 'Error during bulk save of dqf_segments: ' . var_export( $flattened_values, true ) );
        }
    }

    /**
     * @param array $structs
     *
     * @throws \Exception
     */
    public function insertBulkMap( array $structs ) {
        $sql = " INSERT INTO dqf_segments (id_segment, dqf_segment_id, dqf_translation_id) VALUES ";
        $sql .= implode( ', ', array_fill( 0, count( $structs ), " ( ?, ?, ? ) " ) );

        $conn = $this->getDatabaseHandler()->getConnection();

        $stmt             = $conn->prepare( $sql );
        $flattened_values = array_reduce( $structs, 'array_merge', [] );
        $result           = $stmt->execute( $flattened_values );

        if ( !$result ) {
            throw new \Exception( 'Error during bulk save of dqf_segments: ' . var_export( $flattened_values, true ) );

        }
    }

    /**
     * @param array $values
     *
     * @throws \Exception
     */
    public function insertOrUpdateInATransaction( array $values ) {
        $sql = " INSERT INTO dqf_segments (id_segment, dqf_segment_id, dqf_translation_id, dqf_parent_project_id) 
                VALUES ( ?, ?, ?, ? )  
                ON DUPLICATE 
                KEY UPDATE 
                dqf_segments.dqf_translation_id = VALUES(dqf_segments.dqf_translation_id),
                dqf_segments.dqf_parent_project_id = VALUES(dqf_segments.dqf_parent_project_id) 
                 ";

        $conn = $this->getDatabaseHandler()->getConnection();

        $conn->beginTransaction();

        $stmt  = $conn->prepare( $sql );

        foreach ($values as $value) {
            $result = $stmt->execute( $value );

            if ( !$result ) {
                throw new \Exception( 'Error during bulk save of dqf_segments: ' . var_export( $value, true ) );
            }
        }

        $conn->commit();
    }

    /**
     * @param int $id_segment
     * @param int $dqf_parent_project_id
     *
     * @return DqfSegmentsStruct
     */
    public function getByIdSegmentAndDqfProjectId($id_segment, $dqf_parent_project_id){
        $sql = " SELECT * FROM dqf_segments
                WHERE id_segment = :id_segment 
                AND dqf_parent_project_id = :dqf_parent_project_id ";

        $conn = $this->getDatabaseHandler()->getConnection();
        $stmt  = $conn->prepare( $sql );

        $stmt->execute( [
            ':id_segment' => $id_segment,
            ':dqf_parent_project_id' => $dqf_parent_project_id,
        ] );

        $stmt->setFetchMode( PDO::FETCH_CLASS, '\Features\Dqf\Model\DqfSegmentsStruct' );

        return $stmt->fetch();
    }
}