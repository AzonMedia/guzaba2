<?php

declare(strict_types=1);

namespace Swoole\WebSocket;

class Frame
{
    public $fd;
    public $data;
    public $opcode = 1;
    public $finish = true;
    public function __toString()
    {
    }

    public static function pack($data, $opcode, $finish, $mask)
    {
    }

    public static function unpack($data)
    {
    }
}
