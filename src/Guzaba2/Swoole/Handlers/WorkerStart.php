<?php

namespace Guzaba2\Swoole\Handlers;

use Guzaba2\Swoole\Debug\Debugger;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Database\ConnectionMonitor;

/**
 * Class WorkerStart
 * Executed at worker start.
 * It starts the Debugger (if this is enabled)
 * @package Guzaba2\Swoole\Handlers
 */

class WorkerStart extends HandlerBase
{

    /**
     * @var
     */
    protected $SwooleDebugger;

    public function handle(\Swoole\Http\Server $Server, int $worker_id) : void
    {
        //$this->HttpServer->set_worker_id($worker_id);
        
        if (Debugger::is_enabled()) {
            $DebuggerBackend = new \Guzaba2\Swoole\Debug\Backends\Basic();
            $Debugger = new \Azonmedia\Debug\Debugger($DebuggerBackend);

            $this->SwooleDebugger = new \Guzaba2\Swoole\Debug\Debugger($this->HttpServer, $worker_id, $Debugger);
            //after the server is started print here will not print anything - it seems the output is redirected
        }
        
        Kernel::$Watchdog->checkin($Server, $worker_id);
        Kernel::$Watchdog->check($worker_id);

        \co::create(function() {
            $ConnectionMonitor = new ConnectionMonitor();
            $ConnectionMonitor->monitor();
        });
    }

    public function __invoke(\Swoole\Http\Server $Server, int $worker_id) : void
    {
        $this->handle($Server, $worker_id);
    }
}
