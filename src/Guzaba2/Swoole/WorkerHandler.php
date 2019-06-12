<?php


namespace Guzaba2\Swoole;

use Guzaba2\Base\Base;

class WorkerHandler extends Base
{

    /**
     * @var \Guzaba2\Http\Server
     */
    protected $HttpServer;

    public function __construct(\Guzaba2\Http\Server $HttpServer)
    {
        $this->HttpServer = $HttpServer;
    }

    public function handle(\Swoole\Http\Server $Server, int $worker_id) : void
    {
        $this->HttpServer->set_worker_id($worker_id);
    }

    public function __invoke(\Swoole\Http\Server $Server, int $worker_id) : void
    {
        $this->handle($Server, $worker_id);
    }
}