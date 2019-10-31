<?php

namespace Features\Dqf\Utils\Factory;

use Matecat\Dqf\Utils\DataEncryptor;

class DataEncryptorFactory implements FactoryInterface {

    /**
     * @return DataEncryptor
     */
    public static function create() {
        return new DataEncryptor( \INIT::$DQF_ENCRYPTION_KEY, \INIT::$DQF_ENCRYPTION_IV );
    }
}