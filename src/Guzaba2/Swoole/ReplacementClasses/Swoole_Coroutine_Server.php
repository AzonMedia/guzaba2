<?php
namespace Swoole\Coroutine;

class Server
{
    public $host;
    public $port;
    public $type = 2;
    public $fd = -1;
    public $errCode;
    public $setting;
    protected $running;
    protected $fn;
    protected $socket;
    public function __construct(string $host, int $port = 0, bool $ssl = false, bool $reuse_port = false)
    {
    }

    public function set(array $setting) : void
    {
    }

    public function handle(callable $fn) : void
    {
    }

    public function shutdown() : bool
    {
    }

    public function start() : bool
    {
    }
}
