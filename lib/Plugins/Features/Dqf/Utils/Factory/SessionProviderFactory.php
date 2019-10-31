<?php

namespace Features\Dqf\Utils\Factory;

use Matecat\Dqf\SessionProvider;
use Predis\Connection\ConnectionException;

class SessionProviderFactory implements FactoryInterface {
    /**
     * @return SessionProvider
     * @throws ConnectionException
     * @throws \ReflectionException
     */
    public static function create() {
        return new SessionProvider(
            ClientFactory::create(),
            UserRepositoryFactory::create()
        );
    }
}
