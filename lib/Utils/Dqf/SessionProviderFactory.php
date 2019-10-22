<?php

namespace Dqf;

use Matecat\Dqf\Model\Repository\DqfUserRepositoryInterface;
use Matecat\Dqf\Repository\Persistence\InMemoryDqfUserRepository;
use Matecat\Dqf\Repository\Persistence\PDODqfUserRepository;
use Matecat\Dqf\Repository\Persistence\RedisDqfUserRepository;
use Matecat\Dqf\SessionProvider;
use Predis\Connection\ConnectionException;

class SessionProviderFactory {
    /**
     * @return SessionProvider
     * @throws ConnectionException
     * @throws \ReflectionException
     */
    public static function create() {
        return new SessionProvider( ClientFactory::create(), self::getDqfUserRepository() );
    }

    /**
     * @return DqfUserRepositoryInterface
     * @throws ConnectionException
     * @throws \ReflectionException
     */
    private static function getDqfUserRepository() {
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