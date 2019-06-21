<?php


namespace Guzaba2\Patterns;

use Guzaba2\Patterns\Interfaces\MultitonInterface;

abstract class MixedMultiton
{
    public static function &get_worker_instnace( /* mixed */ $index) : MultitonInterface
    {
        return WorkerMultiton::get_instance($index);
    }

    public static function &get_request_instance( /* mixed */ $index) : MultitonInterface
    {

    }

    public static function &get_coroutine_instance( /* mixed */ $index) : MultitonInterface
    {

    }

}