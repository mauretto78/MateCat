<?php

use SubFiltering\Filter;
use TranslationsSplit\UnitOfWork;

class setSegmentSplitController extends ajaxController {

    private $id_job;
    private $job_pass;
    private $segment;
    private $target;
    private $exec;

    public function __construct() {

        parent::__construct();

        //Session Enabled
        $this->readLoginInfo();
        //Session Disabled

        $filterArgs = [
                'id_job'     => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_segment' => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password'   => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'segment'    => [
                        'filter' => FILTER_UNSAFE_RAW
                ],
                'target'     => [
                        'filter' => FILTER_UNSAFE_RAW
                ],
                'exec'       => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ]
        ];

        $postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_job     = $postInput[ 'id_job' ];
        $this->id_segment = $postInput[ 'id_segment' ];
        $this->job_pass   = $postInput[ 'password' ];
        $this->segment    = $postInput[ 'segment' ];
        $this->target     = $postInput[ 'target' ];
        $this->exec       = $postInput[ 'exec' ];

        if ( empty( $this->id_job ) ) {
            $this->result[ 'errors' ][] = [
                    'code'    => -3,
                    'message' => 'Invalid job id'
            ];
        }

        if ( empty( $this->id_segment ) ) {
            $this->result[ 'errors' ][] = [
                    'code'    => -4,
                    'message' => 'Invalid segment id'
            ];
        }

        if ( empty( $this->job_pass ) ) {
            $this->result[ 'errors' ][] = [
                    'code'    => -5,
                    'message' => 'Invalid job password'
            ];
        }

        //this checks that the json is valid, but not its content
        if ( is_null( $this->segment ) ) {
            $this->result[ 'errors' ][] = [
                    'code'    => -6,
                    'message' => 'Invalid source_chunk_lengths json'
            ];
        }

        //check Job password
        $jobStruct = Chunks_ChunkDao::getByIdAndPassword( $this->id_job, $this->job_pass );
        $this->featureSet->loadForProject( $jobStruct->getProject() );

    }

    public function doAction() {

        if ( !empty( $this->result[ 'errors' ] ) ) {
            return;
        }

        $translationSplitStruct = (new TranslationsSplit_SplitDAO())->getByIdSegmentAndIdJob($this->id_segment, $this->id_job);

        if(null === $translationSplitStruct){
            $translationSplitStruct = TranslationsSplit_SplitStruct::getStruct();
            $translationSplitStruct->id_segment = $this->id_segment;
            $translationSplitStruct->id_job = $this->id_job;
        }

        $Filter = Filter::getInstance( $this->featureSet );
        list( $this->segment, $translationSplitStruct->source_chunk_lengths ) = CatUtils::parseSegmentSplit( $this->segment, '', $Filter );

        /* Fill the statuses with DEFAULT DRAFT VALUES */
        $pieces                                       = ( count( $translationSplitStruct->source_chunk_lengths ) > 1 ? count( $translationSplitStruct->source_chunk_lengths ) - 1 : 1 );
        $translationSplitStruct->target_chunk_lengths = [
                'len'      => [ 0 ],
                'statuses' => array_fill( 0, $pieces, Constants_TranslationStatus::STATUS_DRAFT )
        ];

        $uow = new UnitOfWork($translationSplitStruct, $this->user);

        if ( $uow->commit() ) {
            //return success
            $this->result[ 'data' ] = 'OK';
        } else {
            Log::doJsonLog( "Failed while splitting/merging segment." );
            Log::doJsonLog( $translationSplitStruct );
        }
    }
}


