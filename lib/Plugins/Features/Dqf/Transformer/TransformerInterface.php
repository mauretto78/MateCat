<?php

namespace Features\Dqf\Transformer;

use Translations_SegmentTranslationStruct;

interface TransformerInterface {

    /**
     * Transform a struct into an array structure suitable for DQF analysis
     *
     * @param \DataAccess_AbstractDaoObjectStruct $struct
     *
     * @return array
     */
    public function transform( \DataAccess_AbstractDaoObjectStruct $struct );
}
