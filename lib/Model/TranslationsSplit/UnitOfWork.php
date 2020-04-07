<?php

namespace TranslationsSplit;

use Chunks_ChunkStruct;
use Constants_TranslationStatus;
use Database;
use Features\ReviewExtended\Model\ChunkReviewDao;
use Features\ReviewExtended\ReviewUtils;
use Features\SecondPassReview\Model\SegmentTranslationEventDao;
use Features\TranslationVersions\Model\SegmentTranslationEventStruct;
use IUnitOfWork;
use Jobs_JobDao;
use PDOException;
use TransactionableTrait;
use Translations_SegmentTranslationDao;
use TranslationsSplit_SplitDAO;
use TranslationsSplit_SplitStruct;
use Users_UserStruct;
use Utils;

class UnitOfWork implements IUnitOfWork {

    use TransactionableTrait;

    /**
     * @var TranslationsSplit_SplitStruct
     */
    private $translationSplitStruct;

    /**
     * @var Users_UserStruct
     */
    private $user;
    /**
     * @var \Translations_SegmentTranslationStruct
     */
    private $translationStruct;

    /**
     * UnitOfWork constructor.
     *
     * @param TranslationsSplit_SplitStruct $translationSplitStruct
     * @param Users_UserStruct              $user
     */
    public function __construct( TranslationsSplit_SplitStruct $translationSplitStruct, Users_UserStruct $user ) {
        $this->translationSplitStruct = $translationSplitStruct;
        $this->translationStruct      = Translations_SegmentTranslationDao::findBySegmentAndJob( $this->translationSplitStruct->id_segment, $this->translationSplitStruct->id_job );
        $this->user                   = $user;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function commit() {

        try {
            // commit the updates in a transaction
            $this->openTransaction();

            $translationDao = new TranslationsSplit_SplitDAO( Database::obtain() );
            $translationDao->atomicUpdate( $this->translationSplitStruct );
            $this->persistEvents();
            $this->updateTodoCounts();

            $this->commitTransaction();

        } catch ( PDOException $e ) {
            $this->rollback();

            \Log::doJsonLog( 'TranslationsSplit UnitOfWork transaction failed: ' . $e->getMessage() );

            $this->clearAll();

            return false;
        }

        $this->clearAll();

        return true;
    }

    /**
     * @throws \Exception
     */
    private function persistEvents() {
        $sourcePage         = Utils::getSourcePage();
        $targetChunkLengths = json_decode( $this->translationSplitStruct->target_chunk_lengths );

        foreach ( $targetChunkLengths->statuses as $status ) {
            $event                 = new SegmentTranslationEventStruct();
            $event->id_job         = $this->translationSplitStruct->id_job;
            $event->id_segment     = $this->translationSplitStruct->id_segment;
            $event->uid            = ( $this->user->uid != null ? $this->user->uid : 0 );
            $event->status         = $status;
            $event->version_number = $this->translationStruct->version_number;
            $event->source_page    = $sourcePage;
            $event->final_revision = 0;
            $event->time_to_edit   = 0;
            $event->setTimestamp( 'create_date', time() );

            SegmentTranslationEventDao::insertStruct( $event );
            Translations_SegmentTranslationDao::updateFields(
                    [ 'status' => $status ],
                    [
                            'id_segment' => $this->translationStruct->id_segment,
                            'id_job'     => $this->translationStruct->id_job
                    ]
            );
        }
    }

    /**
     * Update Todo Counts
     */
    private function updateTodoCounts() {
        $eqWordCount = $this->translationStruct->eq_word_count;
        $chunk       = new Chunks_ChunkStruct( $this->translationStruct->getChunk()->toArray() );
        $data        = [];

        if ( $this->translationStruct->status === Constants_TranslationStatus::STATUS_TRANSLATED ) {
            $data = [
                    'translated_words' => $chunk->translated_words - $eqWordCount,
                    'new_words'        => $chunk->new_words + $eqWordCount,
            ];
        } elseif ( $this->translationStruct->status === Constants_TranslationStatus::STATUS_APPROVED ) {
            $data = [
                    'approved_words' => $chunk->approved_words - $eqWordCount,
                    'new_words'      => $chunk->new_words + $eqWordCount,
            ];
        }

        if(false === empty($data)){
            Jobs_JobDao::updateFields( $data, [ 'id' => $chunk->id ] );

            $chunkReviewDao = new ChunkReviewDao();
            $chunkReviews   = $chunkReviewDao->findChunkReviews( $chunk );

            foreach ( $chunkReviews as $chunkReview ) {
                $datum[ 'reviewed_words_count' ] = 0 - $eqWordCount;
                $datum[ 'penalty_points' ]       = 0;
                $datum[ 'advancement_wc' ]       = 0;
                $datum[ 'total_tte' ]            = 0;

                $chunkReview->reviewed_words_count += $eqWordCount;
                $lqaModelLimit                     = ReviewUtils::filterLQAModelLimit( $chunkReview->getChunk()->getProject()->getLqaModel(), $chunkReview->source_page );
                $score                             = ( $chunkReview->reviewed_words_count == 0 ) ? 0 : $chunkReview->penalty_points / $chunkReview->reviewed_words_count * 1000;
                $datum[ 'is_pass' ]                = ( $score <= $lqaModelLimit );

                $chunkReviewDao->passFailCountsAtomicUpdate( $chunkReview->id, $datum );
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function rollback() {
        $this->rollbackTransaction();
    }

    /**
     * @inheritDoc
     */
    public function clearAll() {
        $this->translationSplitStruct = new self( $this->translationSplitStruct, $this->user );
    }
}