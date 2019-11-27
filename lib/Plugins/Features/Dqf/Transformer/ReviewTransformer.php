<?php

namespace Features\Dqf\Transformer;

use LQA\CategoryDao;
use LQA\EntryStruct;

class ReviewTransformer implements TransformerInterface {

    /**
     * @var array
     */
    private $qaModel;

    public function __construct() {
        $this->setDqfQaModel();
    }

    /**
     * set the $qa_model
     */
    private function setDqfQaModel() {

//        if ( $qa_model === false ) {
//            if( $jsonPath == null ){
//                $qa_model = file_get_contents( \INIT::$ROOT . '/inc/dqf/qa_model.json');
//            } else {
//                $qa_model = file_get_contents( $jsonPath );
//            }
//        }

        $file = \INIT::$ROOT . '/inc/dqf/qa_model.json';

        $qa_model = json_decode( file_get_contents( $file ), true );

        $this->qaModel = $qa_model['model'];
    }

    /**
     * Transform a struct into an array structure suitable for DQF analysis
     *
     * @param \DataAccess_AbstractDaoObjectStruct $struct
     *
     * @return array
     */
    public function transform( \DataAccess_AbstractDaoObjectStruct $struct ) {

        if ( false === $struct instanceof EntryStruct ) {
            throw new \InvalidArgumentException( 'Provided struct is not a valid instance of ' . EntryStruct::class );
        }

        /** @var EntryStruct $struct */

        $transformedArray                      = [];
        $transformedArray[ 'errorCategoryId' ] = $this->getErrorCategoryId($struct);
        $transformedArray[ 'severityId' ]      = $this->getSeverityId($struct);
        $transformedArray[ 'charPosStart' ]    = $this->getCharPosStart($struct);
        $transformedArray[ 'charPosEnd' ]      = $this->getCharPosEnd($struct);
        $transformedArray[ 'isRepeated' ]      = $this->isRepeated($struct);

        return $transformedArray;
    }

    /**
     * @param EntryStruct $struct
     *
     * @return int
     */
    private function getErrorCategoryId(EntryStruct $struct) {
        $category = CategoryDao::findById($struct->id_category);
        $label = $category->label;

        foreach ($this->qaModel['categories'] as $category){
            if($label === $category['label']){
                return $category['dqf_id'];
            }
        }
    }

    /**
     * @param EntryStruct $struct
     *
     * @return int
     */
    private function getSeverityId(EntryStruct $struct) {
        foreach ($this->qaModel['severities'] as $severity){
            if($struct->severity === $severity['label']){
                return $severity['dqf_id'];
            }
        }
    }

    // @TODO is null for the moment. Waiting for developing of this feature
    private function getCharPosStart(EntryStruct $struct) {
        return null;
    }

    // @TODO is null for the moment. Waiting for developing of this feature
    private function getCharPosEnd(EntryStruct $struct) {
        return null;
    }

    private function isRepeated(EntryStruct $struct) {
        return false;
    }
}
