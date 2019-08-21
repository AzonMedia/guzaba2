<?php

namespace Guzaba2\Swoole\Handlers;

use Guzaba2\Base\Base;
use Guzaba2\Swoole\Handlers\Interfaces\HandlerInterface;

abstract class HandlerBase extends Base
implements HandlerInterface
{
    /**
     * A list of supported events
     */
    public const EVENTS = [
        'Start',
        'Shutdown',
        'WorkerStart',
        'WorkerStop',
        'WorkerExit',
        'Connect',
        'Receive',
        'Packet',
        'Close',
        'Task',
        'PipeMessage',
        'WorkerError',
        'ManagerStart',
        'ManagerStop',
    ];

    /**
     * @var \Guzaba2\Http\Server
     */
    protected $HttpServer;

    public function __construct(\Guzaba2\Http\Server $HttpServer)
    {
        parent::__construct();
        $this->HttpServer = $HttpServer;
    }
}
