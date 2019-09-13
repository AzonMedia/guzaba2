<?php
namespace Swoole\Coroutine;

class Channel
{
    public $capacity;
    public $errCode;
    public function __construct($size)
    {
        $new_size = $size;
    }

    public function push($data, $timeout)
    {
    }

    public function pop($timeout)
    {
    }

    public function isEmpty()
    {
    }

    public function isFull()
    {
    }

    public function close()
    {
    }

    public function stats()
    {
    }

    public function length()
    {
    }
}
