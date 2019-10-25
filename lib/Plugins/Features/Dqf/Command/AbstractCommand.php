<?php

namespace Features\Dqf\Command;

use ReflectionClass;
use ReflectionProperty;

abstract class AbstractCommand {

    public function __construct( $array_params = [] ) {
        if ( $array_params != null ) {
            foreach ( $array_params as $property => $value ) {
                $this->$property = $value;
            }
        }
    }

    public function toArray( $mask = null ) {
        $attributes       = [];
        $reflectionClass  = new ReflectionClass( $this );
        $publicProperties = $reflectionClass->getProperties( ReflectionProperty::IS_PUBLIC );
        foreach ( $publicProperties as $property ) {
            if ( !empty( $mask ) ) {
                if ( !in_array( $property->getName(), $mask ) ) {
                    continue;
                }
            }
            $attributes[ $property->getName() ] = $property->getValue( $this );
        }

        return $attributes;
    }


}