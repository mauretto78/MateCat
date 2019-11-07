<?php

namespace Features\Dqf\Transformer;

use Translations_SegmentTranslationStruct;

interface TransformerInterface {

    /**
     * @param Translations_SegmentTranslationStruct $translation
     *
     * @return array
     */
    public function transform( Translations_SegmentTranslationStruct $translation );
}
