<?php


namespace Guzaba2\Swoole\Handlers\Http;


abstract class HandlerBase extends \Guzaba2\Swoole\Handlers\HandlerBase
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

        //HTTP specific
        'Request',
    ];
}