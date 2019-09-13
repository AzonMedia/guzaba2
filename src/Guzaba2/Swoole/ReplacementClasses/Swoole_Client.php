<?php
namespace Swoole;

class Client
{
    public $errCode;
    public $sock = -1;
    public $reuse;
    public $reuseCount;
    public $type;
    public $id;
    public $setting;
    private $onConnect;
    private $onError;
    private $onReceive;
    private $onClose;
    private $onBufferFull;
    private $onBufferEmpty;
    public const MSG_OOB = 1;
    public const MSG_PEEK = 2;
    public const MSG_DONTWAIT = 64;
    public const MSG_WAITALL = 256;
    public const SHUT_RDWR = 2;
    public const SHUT_RD = 0;
    public const SHUT_WR = 1;
    public function __construct($type, $async)
    {
    }

    public function __destruct()
    {
    }

    public function set(array $settings)
    {
    }

    public function connect($host, $port, $timeout, $sock_flag)
    {
    }

    public function recv($size, $flag)
    {
    }

    public function send($data, $flag)
    {
    }

    public function pipe($dst_socket)
    {
    }

    public function sendfile($filename, $offset, $length)
    {
    }

    public function sendto($ip, $port, $data)
    {
    }

    public function sleep()
    {
    }

    public function wakeup()
    {
    }

    public function pause()
    {
    }

    public function resume()
    {
    }

    public function shutdown($how)
    {
    }

    public function isConnected()
    {
    }

    public function getsockname()
    {
    }

    public function getpeername()
    {
    }

    public function close($force)
    {
    }

    public function on($event_name, callable $callback)
    {
    }
}
