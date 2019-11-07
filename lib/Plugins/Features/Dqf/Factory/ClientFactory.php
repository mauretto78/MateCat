<?php

namespace Features\Dqf\Factory;

use Matecat\Dqf\Client;

class ClientFactory implements FactoryInterface
{
    /**
     * @return Client
     */
    public static function create() {

        return new Client([
            'apiKey'         => \INIT::$DQF_API_KEY,
            'idPrefix'       => \INIT::$DQF_ID_PREFIX,
            'encryptionKey'  => \INIT::$DQF_ENCRYPTION_KEY,
            'encryptionIV'   => \INIT::$DQF_ENCRYPTION_IV,
            'debug'          => \INIT::$DQF_DEBUG == true,
            'logStoragePath' => \INIT::$LOG_REPOSITORY . '/dqf_api_calls.log'
        ]);
    }
}


