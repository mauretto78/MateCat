<?php

class Dummy {
    private $id = 12;
    private $password = 'password';
}

class UtilsTest extends AbstractTest {

    public function testGetPropertiesFromAnObject() {

        $props = Utils::getPropertiesFromAnObject(new Dummy());

        $this->assertEquals($props['id'], 12);
        $this->assertEquals($props['password'], 'password');
    }
}