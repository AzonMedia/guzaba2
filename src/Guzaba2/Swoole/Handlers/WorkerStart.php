<?php


namespace Guzaba2\Swoole\Handlers;

use Guzaba2\Swoole\Debug\Debugger;

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
        $this->HttpServer->set_worker_id($worker_id);

        if (Debugger::is_enabled()) {
            $DebuggerBackend = new \Guzaba2\Swoole\Debug\Backends\Basic();
            $Debugger = new \Azonmedia\Debug\Debugger($DebuggerBackend);
            $this->SwooleDebugger = new \Guzaba2\Swoole\Debug\Debugger($this->HttpServer, $worker_id, $Debugger);
        }


        //this is the proper place to initialize the Di/Container
        //the master thread also works becase when a worker is forked it copies the whole mmeory of the master process

        /*
        //$worker_id = $this->HttpServer->get_worker_id();
        $function = function () use ($worker_id) {

            print 'start debug'.PHP_EOL;

            $socket = new \Co\Socket(AF_INET, SOCK_STREAM, 0);//SOL_TCP
            $socket->bind('127.0.0.1', 1000 + (int) $worker_id);
            $socket->listen(128);
//
            $client = $socket->accept();

            while(true) {
//                        //echo "Client Recv: \n";
                $data = $client->recv();
//                        //if (empty($data)) {
//                        //    $client->close();
//                        //    break;
//                        //}
//                        //var_dump($client->getsockname());
//                        //var_dump($client->getpeername());
//                        //echo "Client Send: \n";
                $data = 'parallel co '.count(\Guzaba2\Coroutine\Coroutine::$coroutines_ids);
                $client->send($data);
//                        \Co::sleep(2);
                //print print_r(\Guzaba2\Coroutine\Coroutine::$last_coroutine_id.PHP_EOL, TRUE);
                print 'AAAA'.Coroutine::$last_coroutine_id.PHP_EOL;
                //print_r(Coroutine::$coroutines_ids);
            }
        };
        \Co::create($function);
        */

//        \Swoole\Coroutine::create(function() use ($worker_id) {
//            for ($aa = 0 ; $aa< 100; $aa++) {
//                \Swoole\Coroutine::sleep(1);
//                print $worker_id.' '.$aa.PHP_EOL;
//            }
//
//        });
    }

    public function __invoke(\Swoole\Http\Server $Server, int $worker_id) : void
    {
        $this->handle($Server, $worker_id);
    }
}