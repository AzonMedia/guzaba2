<?php

declare(strict_types=1);

namespace Swoole;

class Atomic
{
    public function __construct($value)
    {
        $new_value = $value;
    }

    public function add($add_value)
    {
    }

    public function sub($sub_value)
    {
    }

    public function get()
    {
    }

    public function set($value)
    {
    }

    public function wait($timeout)
    {
    }

    public function wakeup($count)
    {
    }

    public function cmpset($cmp_value, $new_value)
    {
    }
}
