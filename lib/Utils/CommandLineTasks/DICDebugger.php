<?php

namespace CommandLineTasks;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DICDebugger extends Command {

    protected function configure() {
        $this
                ->setName( 'dic:debug' )
                ->setDescription( 'Show the content of DIC.' )
                ->setHelp( "Show the content of DIC" );
    }

    protected function execute( InputInterface $input, OutputInterface $output ) {
        echo 'ciao';
    }
}