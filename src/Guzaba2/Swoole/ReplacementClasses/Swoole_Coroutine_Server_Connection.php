<?php

declare(strict_types=1);

namespace Swoole\Coroutine\Server;

class Connection
{
    public $socket;
    public function __construct(\Swoole\Coroutine\Socket $conn)
    {
        $new_conn = $conn;
    }

    public function recv($timeout = 0)
    {
    }

    public function send($data)
    {
    }

    public function close()
    {
    }
}
