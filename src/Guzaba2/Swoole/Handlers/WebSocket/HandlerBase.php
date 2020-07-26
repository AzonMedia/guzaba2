<?php

declare(strict_types=1);

namespace Guzaba2\Swoole\Handlers\WebSocket;

abstract class HandlerBase extends \Guzaba2\Swoole\Handlers\Http\HandlerBase
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

        //WebSocket Specific
        'Open',
        'Close',
        'Message',
    ];
}
