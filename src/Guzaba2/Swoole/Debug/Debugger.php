<?php


namespace Guzaba2\Swoole\Debug;

use Guzaba2\Base\Base;

/**
 * Class Debugger
 * Uses Swoole\Coroutine\Server which is available after Swoole version 4.4
 * @package Guzaba2\Swoole
 */
class Debugger extends Base
{
    protected const CONFIG_DEFAULTS = [
        'enabled'   => TRUE,
        'base_port' => 10000,//on this port the first worker will listen
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * @var \Guzaba2\Http\Server
     */
    protected $HttpServer;

    /**
     * @var \Swoole\Coroutine\Server
     */
    protected $DebugServer;

    /**
     * @var \Azonmedia\Debug\Interfaces\DebuggerInterface
     */
    protected $Debugger;

    /**
     * @var int
     */
    protected $worker_id;

    public function __construct(\Guzaba2\Http\Server $HttpServer, int $worker_id, \Azonmedia\Debug\Interfaces\DebuggerInterface $Debugger)
    //public function __construct(?\Guzaba2\Http\Server $HttpServer, int $worker_id, \Azonmedia\Debug\Interfaces\DebuggerInterface $Debugger)
    {
        parent::__construct();

        if (!self::is_enabled()) {
            return;
        }

        $this->HttpServer = $HttpServer;
        $this->worker_id = $worker_id;
        $this->Debugger = $Debugger;

        //ob_implicit_flush();
        $this->DebugServer = new \Swoole\Coroutine\Server($this->HttpServer->get_host(), self::get_worker_port($worker_id), FALSE);
//        $server->handle(function (Swoole\Coroutine\Server\Connection $conn) use ($server) {
//            while(true) {
//                $data = $conn->recv();
//                $json = json_decode($data, true);
//                Assert::eq(is_array($json), $json['data'], 'hello');
//                $conn->send("world\n");
//            }
//        });
        //$this->DebugServer->handle([$this,'connection_handler']);//Triggers Uncaught TypeError: Argument 1 passed to Swoole\Coroutine\Server::handle() must be callable, array given
        $Function = function (\Swoole\Coroutine\Server\Connection $Connection) : void {
            while (true) {
                $command = $Connection->recv();
                $response = $this->Debugger->handle($command);
                if ($response === NULL) {
                    $response = sprintf(t::_('Unknown command provided.'));
                }
                //$json = json_decode($data, true);
                //Assert::eq(is_array($json), $json['data'], 'hello');
                $response .= PHP_EOL;
                $conn->send($response);
            }
        };
        $this->DebugServer->handle($Function);
        $this->DebugServer->start();
    }

    protected function connection_handler(\Swoole\Coroutine\Server\Connection $Connection) : void
    {
        while (true) {
            $command = $Connection->recv();
            $response = $this->Debugger->handle($command);
            if ($response === NULL) {
                $response = sprintf(t::_('Unknown command provided.'));
            }
            //$json = json_decode($data, true);
            //Assert::eq(is_array($json), $json['data'], 'hello');
            $response .= PHP_EOL;
            $conn->send($response);
        }
    }

    public static function is_enabled() : bool
    {
        return self::CONFIG_RUNTIME['enabled'];
    }

    public static function get_worker_port(int $worker_id) : int
    {
        return self::CONFIG_RUNTIME['base_port'] + $worker_id;
    }
}
