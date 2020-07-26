<?php

declare(strict_types=1);

namespace Swoole\Coroutine;

class Socket
{
    public $fd = -1;
    public $errCode;
    public $errMsg;
    public function __construct($domain, $type, $protocol)
    {
        $new_domain = $domain;
        $new_type = $type;
        $new_protocol = $protocol;
    }

    public function bind($address, $port)
    {
    }

    public function listen($backlog)
    {
    }

    public function accept($timeout)
    {
    }

    public function connect($host, $port, $timeout)
    {
    }

    public function recv($length, $timeout)
    {
    }

    public function recvPacket($timeout)
    {
    }

    public function send($data, $timeout)
    {
    }

    public function sendFile($filename, $offset, $length)
    {
    }

    public function recvAll($length, $timeout)
    {
    }

    public function sendAll($data, $timeout)
    {
    }

    public function recvfrom(&$peername, $timeout)
    {
    }

    public function sendto($addr, $port, $data)
    {
    }

    public function getOption($level, $opt_name)
    {
    }

    public function setProtocol(array $settings)
    {
    }

    public function setOption($level, $opt_name, $opt_value)
    {
    }

    public function shutdown($how)
    {
    }

    public function cancel($event)
    {
    }

    public function close()
    {
    }

    public function getpeername()
    {
    }

    public function getsockname()
    {
    }
}
