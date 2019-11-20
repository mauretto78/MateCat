<?php

namespace Features\Dqf\Transformer;

use DataAccess\ShapelessConcreteStruct;

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

        if ( false === $struct instanceof ShapelessConcreteStruct ) {
            throw new \InvalidArgumentException( 'Provided struct is not a valid instance of ' . ShapelessConcreteStruct::class );
        }

        /** @var ShapelessConcreteStruct $struct */

        $transformedArray                      = [];
        $transformedArray[ 'errorCategoryId' ] = $this->getErrorCategoryId($struct);
        $transformedArray[ 'severityId' ]      = $this->getSeverityId($struct);
        $transformedArray[ 'charPosStart' ]    = $this->getCharPosStart($struct);
        $transformedArray[ 'charPosEnd' ]      = $this->getCharPosEnd($struct);
        $transformedArray[ 'isRepeated' ]      = $this->isRepeated($struct);

        return $transformedArray;
    }

    /**
     * @param ShapelessConcreteStruct $struct
     *
     * @return int
     */
    private function getErrorCategoryId(ShapelessConcreteStruct $struct) {
        foreach ($this->qaModel['categories'] as $category){
            if($struct->issue_category_label === $category['label']){
                return $category['dqf_id'];
            }
        }
    }

    /**
     * @param ShapelessConcreteStruct $struct
     *
     * @return int
     */
    private function getSeverityId(ShapelessConcreteStruct $struct) {
        foreach ($this->qaModel['severities'] as $severity){
            if($struct->issue_severity === $severity['label']){
                return $severity['dqf_id'];
            }
        }
    }

    private function getCharPosStart(ShapelessConcreteStruct $struct) {
        return null;
    }

    private function getCharPosEnd(ShapelessConcreteStruct $struct) {
        return null;
    }

    private function isRepeated(ShapelessConcreteStruct $struct) {
        return false;
    }
}
