<?php
namespace Swoole\Coroutine;

class WaitGroup
{
    protected $chan;
    protected $count;
    protected $waiting;
    public function __construct()
    {
    }

    public function add(int $delta = 1) : void
    {
    }

    public function done() : void
    {
    }

    public function wait(float $timeout = -1) : bool
    {
    }
}
