<?php
declare(strict_types=1);

namespace Guzaba2\Swoole;

use Azonmedia\Glog\Application\MysqlConnection;
use Azonmedia\PsrToSwoole\PsrToSwoole;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Http\Body\Stream;
use Guzaba2\Http\QueueRequestHandler;
use Guzaba2\Http\Request;
use Guzaba2\Http\Response;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Http\StatusCode;
use Guzaba2\Execution\RequestExecution;
use Throwable;


class RequestHandler extends Base
{

//    protected const CONFIG_DEFAULTS = [
//        'services'      => [
//            'ConnectionFactory'
//        ]
//    ];
//
//    protected const CONFIG_RUNTIME = [];

    /**
     * Array of MiddlewareInterface
     * @var array
     */
    protected $middlewares = [];

    /**
     * @var Response
     */
    protected $DefaultResponse;

    /**
     * @var Server
     */
    protected $HttpServer;

    /**
     * RequestHandler constructor.
     * @param array $middlewares
     * @param Server $HttpServer
     * @param Response|null $DefaultResponse
     * @throws RunTimeException
     */
    public function __construct(array $middlewares = [], Server $HttpServer, ?Response $DefaultResponse = NULL)
    {
        parent::__construct();

        $this->middlewares = $middlewares;

        $this->HttpServer = $HttpServer;

        if (!$DefaultResponse) {
            $Body = new Stream();
            $Body->write('Content not found or request not understood (routing not configured).');
            $DefaultResponse = new Response(StatusCode::HTTP_NOT_FOUND, [], $Body);
        }
        $this->DefaultResponse = $DefaultResponse;

    }

    /**
     *
     * @param \Swoole\Http\Request $SwooleRequest
     * @param \Swoole\Http\Response $SwooleResponse
     */
    public function handle(\Swoole\Http\Request $SwooleRequest, \Swoole\Http\Response $SwooleResponse) : void
    {




//        \Guzaba2\Coroutine\Coroutine::create(function(){
//            print_r(\Guzaba2\Coroutine\Coroutine::getParentCoroutines());
//        });

//        \Guzaba2\Coroutine\Coroutine::create(function(){
//            //\Co::sleep(2);
//            //print 'AFTER SLEEP'.PHP_EOL;
//            print '========='.PHP_EOL;
//            print_r(\Guzaba2\Coroutine\Coroutine::getParentCoroutines());
//            print '========='.PHP_EOL;
//        });
//        \Co::sleep(2);

        //print_r(\Guzaba2\Coroutine\Coroutine::getParentCoroutines());

        //print_r(\Guzaba2\Coroutine\Coroutine::getParentCoroutines());
        //print_r(\co::getBacktrace(0, DEBUG_BACKTRACE_IGNORE_ARGS));

        //swoole cant use set_exception_handler so everything gets wrapped in try/catch and a manual call to the exception handler
        try {

            $start_time = microtime(TRUE);

            \Guzaba2\Coroutine\Coroutine::init($this->HttpServer->get_worker_id());

            $Execution =& RequestExecution::get_instance();
            //print $Execution->get_object_internal_id().' '.spl_object_hash($Execution).PHP_EOL;


//            //$c1 = new c1();
//            \Guzaba2\Coroutine\Coroutine::create(function() use (&$c1) {
//                $c1 = new c1();
//                \co::sleep(1);
//                $c1->v1 = 55;
//            });
//            \Guzaba2\Coroutine\Coroutine::create(function() use (&$c1) {
//                print $c1->v1.PHP_EOL;
//                \co::sleep(2);
//                print $c1->v1.PHP_EOL;
//            });


//            \Guzaba2\Coroutine\Coroutine::create(function() {
//                \co::sleep(1);
//                c1::$ss = 55;
//            });
//            \Guzaba2\Coroutine\Coroutine::create(function() {
//                print c1::$ss.PHP_EOL;
//                \co::sleep(2);
//                print c1::$ss.PHP_EOL;
//            });

//            \Guzaba2\Coroutine\Coroutine::create(function(){
//
//                $F = function () {
//                    \Guzaba2\Coroutine\Coroutine::create(function () {
//                        //print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
//                        //print_r(Coroutine::getFullBacktrace(DEBUG_BACKTRACE_IGNORE_ARGS));
//                        //print_r(\co::getBacktrace(\co::getcid(), DEBUG_BACKTRACE_IGNORE_ARGS));
//                        //print_r(Coroutine::getParentCoroutines());
//                        //\co::sleep(rand(0,2));
//                        //print_r(Coroutine::$coroutines_ids);
//                        //print_r(Coroutine::getParentCoroutines());
//                        print Coroutine::getTotalSubCoroutinesCount(Coroutine::getRootCoroutine()).PHP_EOL;
//                    });
//                };+
//
//                $F();
//            });

//            Coroutine::create(function(){
//                //print Coroutine::getTotalSubCoroutinesCount(Coroutine::getRootCoroutine()).'*'.PHP_EOL;
//                Coroutine::create(function(){
//                    //print Coroutine::getTotalSubCoroutinesCount(Coroutine::getRootCoroutine()).'*'.PHP_EOL;
//                    \co::sleep(4);
//                    print 'ggg';
//                });
//            });

//            Coroutine::create(function(){
//                \co::sleep(3);
//                print 'ggg';
//            });

//            Coroutine::create(function($a1) {
//                print $a1;
//            }, 555);

//            static $debug_is_set = FALSE;
//            if (!$debug_is_set) {
//                print 'gggggg';
//
//                $worker_id = $this->HttpServer->get_worker_id();
//                $function = function () use ($worker_id) {
//
//                    print 'start debug'.PHP_EOL;
//
////                    $socket = new \Co\Socket(AF_INET, SOCK_STREAM, 0);//SOL_TCP
////                    $socket->bind('127.0.0.1', 1000 + (int) $worker_id);
////                    $socket->listen(128);
////
////                    $client = $socket->accept();
//
//                    while(true) {
////                        //echo "Client Recv: \n";
////                        $data = $client->recv();
////                        //if (empty($data)) {
////                        //    $client->close();
////                        //    break;
////                        //}
////                        //var_dump($client->getsockname());
////                        //var_dump($client->getpeername());
////                        //echo "Client Send: \n";
////                        $data = print_r(\Guzaba2\Coroutine\Coroutine::$coroutines_ids, TRUE);
////                        $client->send($data);
//                        \Co::sleep(2);
//                        //print print_r(\Guzaba2\Coroutine\Coroutine::$last_coroutine_id.PHP_EOL, TRUE);
//                        print 'AAAA'.Coroutine::$last_coroutine_id.PHP_EOL;
//                        //print_r(Coroutine::$coroutines_ids);
//                    }
//                };
//                \Co::create($function);
//
//
//                $debug_is_set = TRUE;
//            }

//            static $flag = FALSE;
//            if (!$flag) {
//                print 'DEBUG'.PHP_EOL;
//                $function = function() {
//                    while(true) {
//                        //print_r(Coroutine::$coroutines_ids);
//                        //print Coroutine::$last_coroutine_id.PHP_EOL;
//                        //print 'count '.count(Coroutine::$co_id).PHP_EOL;
//                        print 'parallel coroutines '.count(Coroutine::$coroutines_ids).PHP_EOL;
//                        \Co::sleep(1);
//                    }
//
//                };
//                \Co::create($function);
//                $flag = TRUE;
//            }
//
            //\Co::sleep(1);
            //for ($aa = 0; $aa < rand(100000, 1000000000); $aa++) {
//            $function = function () {
//                for ($aa = 0; $aa < 1000000000; $aa++) {
//                    if (! ($aa % 100000000) ) {
//                        print 'worker id '.$this->HttpServer->get_worker_id().' coid '.\Co::getcid().PHP_EOL;
//                        //\Co::sleep(1);
//                    }
//                }
//            };
//            \Co::create($function);
//            \Co::create($function);

            //print_r(Coroutine::$coroutines_ids);



            //print 'aaaaaaaaa';
            //print print_r(\Guzaba2\Coroutine\Coroutine::$coroutines_ids, TRUE);

            $PsrRequest = SwooleToGuzaba::convert_request_with_server_params($SwooleRequest, new Request());
            $PsrRequest->set_server($this->HttpServer);


//            $o = new class () {
//                public function __sleep() {
//                    print 'SLEEP';
//                }
//            };

//            if ($PsrRequest['action'] == 'set') {
//                $this->HttpServer->table->set('0', ['id' => $this->HttpServer->get_worker_id(), 'data'=> 'asd'] );
//            } elseif ($PsrRequest['action'] == 'get') {
                //print_r($this->HttpServer->table->get('0'));
//                $s = microtime(true);
//                $Connection1 = self::ConnectionFactory()->get_connection(MysqlConnection::class);
//                for ($aa=0; $aa<10000; $aa++) {
//                    //$this->HttpServer->table->get('0');
//
//                    //print_r(Coroutine::getContext()->getConnections());
//
//                    $query = "SELECT * FROM some_table";
//                    //\Co::sleep(3);
//                    //$query = "SELECT SLEEP(1)";
//                    $Statement = $Connection1->prepare($query);
//                    $Statement->execute();
//                    $data = $Statement->fetchAll();
//                }
//                $e = microtime(true);
//                print 'total: '.($e-$s).PHP_EOL;
//            }


            $FallbackHandler = new \Guzaba2\Http\RequestHandler($this->DefaultResponse);//this will produce 404
            $QueueRequestHandler = new QueueRequestHandler($FallbackHandler);//the default response prototype is a 404 message
            foreach ($this->middlewares as $Middleware) {
                $QueueRequestHandler->add_middleware($Middleware);
            }
            $PsrResponse = $QueueRequestHandler->handle($PsrRequest);


            //very important to stay here!!!
            Coroutine::awaitSubCoroutines();//await before the response is converted as the response uses end() which pushes the output
            //also if any of the subcoroutines has an uncaught exception this will catch all these and throw an exception so that the master coroutine is also terminated

            PsrToSwoole::ConvertResponse($PsrResponse, $SwooleResponse);

            //debug
            $request_raw_content_length = $PsrRequest->getBody()->getSize();
            //$memory_usage = $Exception->get_memory_usage();



            $end_time = microtime(TRUE);
            print microtime(TRUE).' Request of '.$request_raw_content_length.' bytes served by worker '.$this->HttpServer->get_worker_id().' in '.($end_time - $start_time).' seconds with response: code: '.$PsrResponse->getStatusCode().' response content length: '.$PsrResponse->getBody()->getSize().PHP_EOL;
            //print 'Last coroutine id '.Coroutine::$last_coroutine_id.PHP_EOL;


        } catch (Throwable $Exception) {

            Kernel::exception_handler($Exception, NULL);//sending NULL as exit code means DO NOT EXIT (no point to kill the whole worker - let only this request fail)



            $DefaultResponseBody = new Stream();
            $DefaultResponseBody->write('Internal server/application error occurred.');
            $PsrResponse = new \Guzaba2\Http\Response(StatusCode::HTTP_INTERNAL_SERVER_ERROR, [], $DefaultResponseBody);
            PsrToSwoole::ConvertResponse($PsrResponse, $SwooleResponse);
        } finally {
            //\Guzaba2\Coroutine\Coroutine::end();//no need
            $Execution->destroy();
        }
        //print 'MASTER END'.PHP_EOL;

    }

    public function __invoke(\Swoole\Http\Request $Request, \Swoole\Http\Response $Response) : void
    {
        $this->handle($Request, $Response);
    }

}