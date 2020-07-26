<?php

declare(strict_types=1);

namespace Swoole\Coroutine;

class Mysql
{
    public $serverInfo;
    public $sock = -1;
    public $connected;
    public $connect_errno;
    public $connect_error;
    public $affected_rows;
    public $insert_id;
    public $error;
    public $errno;
    public function __construct()
    {
    }

    public function __destruct()
    {
    }

    public function getDefer()
    {
    }

    public function setDefer($defer)
    {
    }

    public function connect(array $server_config)
    {
    }

    public function query($sql, $timeout)
    {
    }

    public function fetch()
    {
    }

    public function fetchAll()
    {
    }

    public function nextResult()
    {
    }

    public function prepare($query, $timeout)
    {
    }

    public function recv()
    {
    }

    public function begin($timeout)
    {
    }

    public function commit($timeout)
    {
    }

    public function rollback($timeout)
    {
    }

    public function close()
    {
    }
}
