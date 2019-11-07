<?php

namespace Features\Dqf\Factory;

use Matecat\Dqf\Repository\Persistence\InMemoryDqfUserRepository;
use Matecat\Dqf\Repository\Persistence\PDODqfUserRepository;
use Matecat\Dqf\Repository\Persistence\RedisDqfUserRepository;
use Predis\Connection\ConnectionException;

class UserRepositoryFactory implements FactoryInterface {

    /**
     * @return InMemoryDqfUserRepository|PDODqfUserRepository|RedisDqfUserRepository
     * @throws ConnectionException
     * @throws \ReflectionException
     */
    public static function create() {
        switch ( \INIT::$DQF_SESSION_PROVIDER_DRIVER ) {
            case "pdo":
                return new PDODqfUserRepository( \Database::obtain()->getConnection() );

            case "in_memory":
                return new InMemoryDqfUserRepository();

            case "redis":
            default:
                return new RedisDqfUserRepository( ( new \RedisHandler() )->getConnection() );
        }
    }
}
