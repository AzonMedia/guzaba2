<?php

namespace Guzaba2\Orm\Interfaces;

interface ActiveRecordInterface
{
    public static function get_routes() : ?iterable ;

    public function get_uuid() : string;

    public function get_id();

    public function get_primary_index() : array;
}
