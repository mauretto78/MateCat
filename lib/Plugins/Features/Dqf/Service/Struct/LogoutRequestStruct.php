<?php

namespace Features\Dqf\Service\Struct;


class LogoutRequestStruct extends BaseRequestStruct implements ISessionBasedRequestStruct {

    public $apiKey;
    public $email;
    public $sessionId;

    public function getHeaders() {
        return $this->toArray(['apiKey', 'sessionId']);
    }

}