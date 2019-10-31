<?php

namespace Features\Dqf\CommandHandler;

use Features\Dqf\Command\CommandInterface;

interface CommandHandlerInterface  {

    /**
     * @param CommandInterface $command
     *
     * @return mixed
     */
    public function handle($command);
}