<?php
namespace Swoole\Server;

final class Task
{
    public $data;
    public $id = -1;
    public $worker_id = -1;
    public $flags;
    public function finish($data)
    {
    }
}
