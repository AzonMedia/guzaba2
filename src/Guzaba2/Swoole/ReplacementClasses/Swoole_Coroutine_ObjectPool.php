<?php
declare(strict_types=1);
namespace Swoole\Coroutine;

abstract class ObjectPool
{
    protected static $context;
    protected $object_pool;
    protected $busy_pool;
    protected $type;
    public function __construct($type, $pool_size = 10, $concurrency = 10)
    {
        $new_type = $type;
        $new_pool_size = $pool_size;
        $new_concurrency = $concurrency;
    }

    public function get()
    {
    }

    public function free()
    {
    }

    // abstract public function create(){}
}
