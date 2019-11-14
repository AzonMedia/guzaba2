<?php

namespace Guzaba2\Orm\Interfaces;

interface ActiveRecordInterface
{

    public const PROPERTY_VALIDATION_SUPPORTED_RULES = [
        'required'      => 'bool',
        'max_length'    => 'int',
        'min_length'    => 'int',
    ];

    public const AUTHZ_METHOD_PREFIX = 'authz_';
    
    public static function get_routes() : ?iterable ;

    public function get_uuid() : string;

    public function get_id();

    public function get_primary_index() : array;

    //public function read() : void;

    public function save() : ActiveRecordInterface;

    public function delete() : void;


}
