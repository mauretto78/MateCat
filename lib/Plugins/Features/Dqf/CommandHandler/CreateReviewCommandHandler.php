<?php

namespace Features\Dqf\CommandHandler;

use Features\Dqf\Command\CommandInterface;
use Features\Dqf\Command\UpdateSegmentTranslationCommand;

class CreateReviewCommandHandler extends AbstractCommandHanlder {

    /**
     * @var CreateReviewCommand
     */
    private $command;

    /**
     * @param CreateReviewCommand $command
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle( $command ) {
        if ( false === $command instanceof CreateReviewCommand ) {
            throw new \Exception( 'Provided command is not a valid instance of UpdateSegmentTranslationCommand class' );
        }

        $this->setUp( $command );
        $this->submit();
    }

    private function setUp( CreateReviewCommand $command ) {

    }

    private function submit() {



        $correction = new RevisionCorrection('Another review comment', 10000);
        $correction->addItem(new RevisionCorrectionItem('review', 'deleted'));
        $correction->addItem(new RevisionCorrectionItem('Another comment', 'unchanged'));

        $reviewedSegment = new ReviewedSegment('this is a comment');
        $reviewedSegment->addError(new RevisionError(11, 2));
        $reviewedSegment->addError(new RevisionError(9, 1, 1, 5));
        $reviewedSegment->setCorrection($correction);

        $reviewedSegment2 = new ReviewedSegment('this is another comment');
        $reviewedSegment2->addError(new RevisionError(10, 2));
        $reviewedSegment2->addError(new RevisionError(11, 1, 1, 5));
        $reviewedSegment2->setCorrection($correction);

        $batchId = Uuid::uuid4()->toString();
        $reviewBatch = new ReviewBatch($childReview, $file, 'en-US', $segment, $batchId);
        $reviewBatch->addReviewedSegment($reviewedSegment);
        $reviewBatch->addReviewedSegment($reviewedSegment2);

        $reviewRepository->save($reviewBatch);
    }
}