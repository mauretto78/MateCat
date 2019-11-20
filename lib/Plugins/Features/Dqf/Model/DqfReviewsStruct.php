<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/07/2017
 * Time: 12:11
 */

namespace Features\Dqf\Model;

use DataAccess_AbstractDaoSilentStruct;

class DqfReviewsStruct extends DataAccess_AbstractDaoSilentStruct {
    public $dqf_translation_id;
    public $dqf_review_id;
    public $dqf_parent_project_id;
}
