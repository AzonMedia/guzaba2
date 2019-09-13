<?php
namespace Swoole\Coroutine\Http2;

class Client
{
    public $errCode;
    public $errMsg;
    public $sock = -1;
    public $type;
    public $setting;
    public $connected;
    public $host;
    public $port;
    public $ssl;
    public function __construct($host, $port, $ssl)
    {
    }

    public function __destruct()
    {
    }

    public function set(array $settings)
    {
    }

    public function connect()
    {
    }

    public function stats($key)
    {
    }

    public function isStreamExist($stream_id)
    {
    }

    public function send($request)
    {
    }

    public function write($stream_id, $data, $end_stream)
    {
    }

    public function recv($timeout)
    {
    }

    public function goaway($error_code, $debug_data)
    {
    }

    public function ping()
    {
    }

    public function close()
    {
    }
}
