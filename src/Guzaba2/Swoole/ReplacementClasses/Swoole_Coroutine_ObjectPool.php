<?php
namespace Swoole\Coroutine;

abstract class ObjectPool
{
    protected static $context;
    protected $object_pool;
    protected $busy_pool;
    protected $type;
    public function __construct($type, $pool_size = 10, $concurrency = 10)
    {
    }

    public function get()
    {
    }

    public function free()
    {
    }

    // abstract public function create(){}
}
