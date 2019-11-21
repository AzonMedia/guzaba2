<?php

namespace Guzaba2\Orm\Interfaces;

interface ActiveRecordInterface
{

    public const PROPERTY_VALIDATION_SUPPORTED_RULES = [
        'required'      => 'bool',
        'max_length'    => 'int',
        'min_length'    => 'int',
    ];

    public const CRUD_HOOKS = [
        '_before_read', '_after_read',
        '_before_save', '_after_save',
        '_before_delete', '_after_delete',
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
