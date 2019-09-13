<?php
namespace Swoole\Process;

class Pool
{
    public $master_pid = -1;
    public $workers;
    public function __construct($worker_num, $ipc_type, $msgqueue_key, $enable_coroutine)
    {
    }

    public function __destruct()
    {
    }

    public function set(array $settings)
    {
    }

    public function on($event_name, callable $callback)
    {
    }

    public function getProcess($worker_id)
    {
    }

    public function listen($host, $port, $backlog)
    {
    }

    public function write($data)
    {
    }

    public function start()
    {
    }

    public function shutdown()
    {
    }
}
