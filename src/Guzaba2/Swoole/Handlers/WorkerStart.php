<?php
declare(strict_types=1);

namespace Guzaba2\Swoole\Handlers;

use Guzaba2\Kernel\Kernel;
use Guzaba2\Swoole\Debug\Debugger;
use Guzaba2\Database\ConnectionMonitor;
use Monolog\Handler\StreamHandler;

/**
 * Class WorkerStart
 * Executed at worker start.
 * It starts the Debugger (if this is enabled)
 * @package Guzaba2\Swoole\Handlers
 */

class WorkerStart extends HandlerBase
{

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'Events',
        ]
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * Will be NULL if $enable_debug_ports = FALSE
     * @var
     */
    private ?Debugger $SwooleDebugger = NULL;

    private bool $enable_debug_ports = FALSE;

    private int $base_debug_port = Debugger::DEFAULT_BASE_DEBUG_PORT;

    public function __construct(\Guzaba2\Http\Server $HttpServer, bool $enable_debug_ports = FALSE, int $base_debug_port = Debugger::DEFAULT_BASE_DEBUG_PORT)
    {
        parent::__construct($HttpServer);
        $this->enable_debug_ports = $enable_debug_ports;
        $this->base_debug_port = $base_debug_port;
    }

    public function debug_ports_enabled() : bool
    {
        return $this->enable_debug_ports;
    }

    public function get_base_debug_port() : int
    {
        return $this->base_debug_port;
    }

    /**
     * @param \Swoole\Http\Server $Server
     * @param int $worker_id
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     * @throws \Exception
     */
    public function handle(\Swoole\Http\Server $Server, int $worker_id) : void
    {
        //$this->HttpServer->set_worker_id($worker_id);
        self::get_service('Events')->create_event($this, '_after_start');

        self::register_log_handler($worker_id);

        //add a ping to the connections to make sure they are not idle
        \Swoole\Coroutine::create(function () {
            $ConnectionMonitor = new ConnectionMonitor();
            $ConnectionMonitor->monitor();
        });

        //\Swoole\Timer::tick($this->HttpServer->get_ipc_responses_cleanup_time() * 1000, [$this->HttpServer, 'ipc_responses_cleanup']);


        //if (Debugger::is_enabled()) {
        if ($this->enable_debug_ports) {
            //pass all paths there classes implementing CommandInterface exist
            $DebuggerBackend = new \Guzaba2\Swoole\Debug\Backends\Basic( Debugger::get_debug_command_classes() );
            $Debugger = new \Azonmedia\Debug\Debugger($DebuggerBackend);

            $this->SwooleDebugger = new \Guzaba2\Swoole\Debug\Debugger($this->HttpServer, $worker_id, $Debugger, $this->base_debug_port);
            //after the server is started print here will not print anything - it seems the output is redirected
        }
        
        Kernel::$Watchdog->checkin($Server, $worker_id);
        Kernel::$Watchdog->check($worker_id);
    }

    /**
     * Register a new log handler for this worker.
     * This will work only if there is main file logger.
     * The worker log is put in the same directory as the main log with name worker_XX.txt
     * @param int $worker_id
     * @throws \Exception
     */
    private static function register_log_handler(int $worker_id) : void
    {
        $Logger = Kernel::get_logger();
        $MainLogFileHandler = Kernel::get_main_log_file_handler();
        if ($MainLogFileHandler !== NULL) {
            $log_level = $Logger::getLevelName($MainLogFileHandler->getLevel());
            $worker_log_file = dirname($MainLogFileHandler->getUrl()).'/worker_'.$worker_id.'.txt';
            $Formatter = $MainLogFileHandler->getFormatter();
            $StdoutHandler = new StreamHandler($worker_log_file, $log_level);
            $StdoutHandler->setFormatter($Formatter);
            $Logger->pushHandler($StdoutHandler);
        }
    }

    public function __invoke(\Swoole\Http\Server $Server, int $worker_id) : void
    {
        $this->handle($Server, $worker_id);
    }
}
