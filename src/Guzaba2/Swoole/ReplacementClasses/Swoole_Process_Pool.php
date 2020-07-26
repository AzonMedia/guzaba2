<?php

declare(strict_types=1);

namespace Swoole\Process;

class Pool
{
    public $master_pid = -1;
    public $workers;
    
    public function __construct($worker_num, $ipc_type, $msgqueue_key, $enable_coroutine)
    {
        $new_worker_num = $worker_num;
        $new_ipc_type = $ipc_type;
        $new_msgqueue_key = $msgqueue_key;
        $new_enable_coroutine = $enable_coroutine;
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
