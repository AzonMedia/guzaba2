<?php
declare(strict_types=1);

namespace Guzaba2\Orm\Interfaces;

use Guzaba2\Authorization\Role;

interface ActiveRecordInterface
{

    public const PROPERTY_VALIDATION_SUPPORTED_RULES = [
        'required'      => 'bool',
        'max_length'    => 'int',
        'min_length'    => 'int',
    ];

    public const CRUD_HOOKS = [
        '_before_read', '_after_read',
        '_before_write', '_after_write',
        '_before_delete', '_after_delete',
    ];

    public const META_TABLE_COLUMNS = [
        'meta_object_uuid_binary',//TODO - mysql specific - remove
        'meta_object_uuid',
        'meta_class_name',
        'meta_object_id',
        'meta_object_create_microtime',
        'meta_object_last_update_microtime',
        'meta_object_create_transaction_id',
        'meta_object_last_update_transaction_id',
    ];

    public const AUTHZ_METHOD_PREFIX = 'authz_';

    public const INDEX_NEW = 0;
    
    public static function get_routes() : ?iterable ;

    public static function get_main_table() : string ;

    public static function get_temporal_class() : string ;

    public function is_new() : bool ;

    public function get_uuid() : string;

    public function get_id();

    public function get_primary_index() : array;

    //public function read() : void;

    public function write() : ActiveRecordInterface;

    public function delete() : void;


    public function check_permission(string $action) : void ;

    public function current_role_can(string $action) : bool ;

    public function role_can(Role $Role, string $action) : bool ;

}
