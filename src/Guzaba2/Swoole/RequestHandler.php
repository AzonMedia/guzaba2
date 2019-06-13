<?php
declare(strict_types=1);

namespace Guzaba2\Swoole;

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
//            \Guzaba2\Coroutine\Coroutine::create(function(){
//                //print_r(\Guzaba2\Coroutine\Coroutine::getParentCoroutines());
//                print Coroutine::getRootCoroutine();
//            });
//        });

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

            \Guzaba2\Coroutine\Coroutine::init();
            $Execution =& RequestExecution::get_instance();
            //print $Execution->get_object_internal_id().' '.spl_object_hash($Execution).PHP_EOL;


            $PsrRequest = SwooleToGuzaba::convert_request_with_server_params($SwooleRequest, new Request());
            $PsrRequest->set_server($this->HttpServer);


            $FallbackHandler = new \Guzaba2\Http\RequestHandler($this->DefaultResponse);//this will produce 404
            $QueueRequestHandler = new QueueRequestHandler($FallbackHandler);//the default response prototype is a 404 message
            foreach ($this->middlewares as $Middleware) {
                $QueueRequestHandler->add_middleware($Middleware);
            }
            $PsrResponse = $QueueRequestHandler->handle($PsrRequest);
            PsrToSwoole::ConvertResponse($PsrResponse, $SwooleResponse);

            //debug
            $request_raw_content_length = $PsrRequest->getBody()->getSize();
            //$memory_usage = $Exception->get_memory_usage();
            print microtime(TRUE).' Request of '.$request_raw_content_length.' bytes served by worker '.$this->HttpServer->get_worker_id().' with response: code: '.$PsrResponse->getStatusCode().' response content length: '.$PsrResponse->getBody()->getSize().PHP_EOL;


        } catch (Throwable $Exception) {
            Kernel::exception_handler($Exception);
        } finally {
            \Guzaba2\Coroutine\Coroutine::end();
            $Execution->destroy();
        }
        //print 'MASTER END'.PHP_EOL;

    }

    public function __invoke(\Swoole\Http\Request $Request, \Swoole\Http\Response $Response) : void
    {
        $this->handle($Request, $Response);
    }

}