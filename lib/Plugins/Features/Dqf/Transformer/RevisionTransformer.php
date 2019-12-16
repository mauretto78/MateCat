<?php

namespace Features\Dqf\Transformer;

use LQA\CategoryDao;
use LQA\EntryStruct;

class RevisionTransformer implements TransformerInterface {

    /**
     * @var array
     */
    private $qaModel;

    /**
     * ReviewTransformer constructor.
     *
     * @param null $qaModelFile
     * @throws \Exception
     */
    public function __construct( $qaModelFile = null ) {
        $file          = ( null !== $qaModelFile ) ? $qaModelFile : \INIT::$ROOT . '/inc/dqf/qa_model.json';
        $this->qaModel = $this->getQaModelFromFile( $file );
    }

    /**
     * @param $file
     *
     * @return array
     * @throws \Exception
     */
    private function getQaModelFromFile( $file ) {
        $qa_model = json_decode( file_get_contents( $file ), true );

        if( count($errors = $this->validateQaModel($qa_model)) > 0 ){
            throw new \Exception('The loaded QA model is not valid: ' . implode(', ', $errors));
        }

        return $qa_model[ 'model' ];
    }

    /**
     * @param array $qa_model
     *
     * @return array
     */
    private function validateQaModel(array $qa_model)
    {
        $errors = [];

        if ( false === isset( $qa_model[ 'model' ]  ) ) {
            $errors[] = 'The QA model does not contain \'model\' array.';

            return $errors;
        }

        $neededKeys = [ 'categories', 'severities' ];
        foreach ( $neededKeys as $neededKey ) {
            if ( false === isset( $qa_model[ 'model' ][ $neededKey ] ) ) {
                $errors[] = 'The QA model does not contain \''.$neededKey.'\' array.';
            } else {
                foreach ($qa_model[ 'model' ][ $neededKey ]  as $index => $qa_model_entry) {
                    if(false === isset($qa_model_entry['dqf_id'])) {
                        $errors[] = 'The QA model entry ['.$neededKey.']['.$index.'] does not contain \'dqf_id\' key.';
                    }

                    if(false === isset($qa_model_entry['label'])) {
                        $errors[] = 'The QA model entry ['.$neededKey.']['.$index.'] does not contain \'label\' key.';
                    }
                }
            }
        }

        return $errors;
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
        $transformedArray[ 'errorCategoryId' ] = $this->getErrorCategoryId( $struct );
        $transformedArray[ 'severityId' ]      = $this->getSeverityId( $struct );
        $transformedArray[ 'charPosStart' ]    = $this->getCharPosStart( $struct );
        $transformedArray[ 'charPosEnd' ]      = $this->getCharPosEnd( $struct );
        $transformedArray[ 'isRepeated' ]      = $this->isRepeated( $struct );

        return $transformedArray;
    }

    /**
     * @param EntryStruct $struct
     *
     * @return int
     */
    private function getErrorCategoryId( EntryStruct $struct ) {
        $category = CategoryDao::findById( $struct->id_category );
        $label    = $category->label;

        foreach ( $this->qaModel[ 'categories' ] as $category ) {
            if ( $label === $category[ 'label' ] ) {
                return $category[ 'dqf_id' ];
            }
        }
    }

    /**
     * @param EntryStruct $struct
     *
     * @return int
     */
    private function getSeverityId( EntryStruct $struct ) {
        foreach ( $this->qaModel[ 'severities' ] as $severity ) {
            if ( $struct->severity === $severity[ 'label' ] ) {
                return $severity[ 'dqf_id' ];
            }
        }
    }

    // @TODO is null for the moment. Waiting for the development of this feature
    private function getCharPosStart( EntryStruct $struct ) {
        return null;
    }

    // @TODO is null for the moment. Waiting for the development of this feature
    private function getCharPosEnd( EntryStruct $struct ) {
        return null;
    }

    private function isRepeated( EntryStruct $struct ) {
        return false;
    }
}
