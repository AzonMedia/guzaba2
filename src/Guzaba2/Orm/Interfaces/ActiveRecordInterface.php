<?php

namespace Guzaba2\Orm\Interfaces;

interface ActiveRecordInterface
{

    public const AUTHZ_METHOD_PREFIX = 'authz_';
    
    public static function get_routes() : ?iterable ;

    public function get_uuid() : string;

    public function get_id();

    public function get_primary_index() : array;

    //public function read() : void;

    public function save() : ActiveRecordInterface;

    public function delete() : void;
}
