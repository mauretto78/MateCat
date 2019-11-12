<?php

namespace Features\Dqf\Transformer;

use Chunks_ChunkStruct;
use Features\Dqf\Model\CachedAttributes\SegmentOrigin;
use Features\Dqf\Model\DqfProjectMapDao;
use Features\Dqf\Model\DqfSegmentsDao;
use Features\Dqf\Model\ExtendedTranslationStruct;
use Features\Dqf\Model\TranslationVersionDao;
use Features\Dqf\Service\FileIdMapping;
use Features\Dqf\Service\ISession;
use Files_FileDao;
use INIT;
use Translations_SegmentTranslationStruct;

class ReviewTranslationTransformer implements TranslationTransformerInterface {

    public function transform( Translations_SegmentTranslationStruct $translation ) {

    }
}