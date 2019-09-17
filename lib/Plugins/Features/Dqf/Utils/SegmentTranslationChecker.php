<?php

namespace Features\Dqf\Utils;

use Features\Dqf\Model\DqfProjectMapDao;
use Translations_SegmentTranslationStruct;

class SegmentTranslationChecker {

    /**
     * ----------------------------------------------------------
     * Check if the single segment translation is allowed to be sent to DQF
     * ----------------------------------------------------------
     *
     * The segment translation single update is allowed only if:
     * - the segment translation is in TRANSLATION status
     * - these is already a DQF project including the segment translation
     *
     * @param Translations_SegmentTranslationStruct $translation
     *
     * @return bool
     */
    public function isSingleUpdateAllowed( Translations_SegmentTranslationStruct $translation ) {
        $type       = ( $translation->status === \Constants_TranslationStatus::STATUS_APPROVED ) ? DqfProjectMapDao::PROJECT_TYPE_REVISE : DqfProjectMapDao::PROJECT_TYPE_TRANSLATE;
        $dqfProject = ( new DqfProjectMapDao() )->getByType( $translation->getChunk(), $type, true );

        return ( $dqfProject !== null && $translation->status === \Constants_TranslationStatus::STATUS_TRANSLATED );
    }
}