<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 08/03/2017
 * Time: 12:20
 */

namespace Features\Dqf\Service\Struct;


class LogoutRequestStruct extends BaseRequestStruct implements ISessionBasedRequestStruct {

    public $apiKey ;
    public $email ;
    public $sessionId ;

    public function getHeaders() {
        return $this->toArray(['apiKey']);
    }

}