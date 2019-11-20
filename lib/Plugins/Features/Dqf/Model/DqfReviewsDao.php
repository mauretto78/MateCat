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

class DqfReviewsDao extends DataAccess_AbstractDao {

    const TABLE = 'dqf_reviews';

    protected static $primary_keys         = [ 'dqf_translation_id' ];
    protected static $auto_increment_field = [];

    /**
     * @param array $values
     *
     * @throws \Exception
     */
    public function insertOrUpdateInATransaction( array $values ) {
        $sql = " INSERT INTO dqf_reviews (dqf_translation_id, dqf_review_id, dqf_parent_project_id) 
                VALUES ( ?, ?, ? )  
                ON DUPLICATE 
                KEY UPDATE 
                dqf_reviews.dqf_translation_id = VALUES(dqf_reviews.dqf_translation_id)
                 ";

        $conn = $this->getDatabaseHandler()->getConnection();

        $conn->beginTransaction();

        $stmt  = $conn->prepare( $sql );

        foreach ($values as $value) {
            $result = $stmt->execute( $value );

            if ( !$result ) {
                throw new \Exception( 'Error during bulk save of dqf_reviews: ' . var_export( $value, true ) );
            }
        }

        $conn->commit();
    }
}