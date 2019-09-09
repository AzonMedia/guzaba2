<?php

namespace Guzaba2\Swoole\Handlers\Http;

use Azonmedia\PsrToSwoole\PsrToSwoole;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Http\Body\Stream;
use Guzaba2\Http\QueueRequestHandler;
use Guzaba2\Http\Response;
use Guzaba2\Http\StatusCode;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Swoole\Server;
use Guzaba2\Swoole\SwooleToGuzaba;
use Psr\Log\LogLevel;
use Throwable;

class Request extends HandlerBase
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
     * RequestHandler constructor.
     * @param array $middlewares
     * @param Server $HttpServer
     * @param Response|null $DefaultResponse
     * @throws RunTimeException
     */
    public function __construct(Server $HttpServer, array $middlewares = [], ?Response $DefaultResponse = NULL)
    {
        parent::__construct($HttpServer);

        $this->middlewares = $middlewares;

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

        //swoole cant use set_exception_handler so everything gets wrapped in try/catch and a manual call to the exception handler
        try {
            $start_time = microtime(TRUE);

            \Guzaba2\Coroutine\Coroutine::init($this->HttpServer->get_worker_id());


            $PsrRequest = SwooleToGuzaba::convert_request_with_server_params($SwooleRequest, new \Guzaba2\Http\Request());
            $PsrRequest->setServer($this->HttpServer);


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
            //print microtime(TRUE).' Request of '.$request_raw_content_length.' bytes served by worker '.$this->HttpServer->get_worker_id().' in '.($end_time - $start_time).' seconds with response: code: '.$PsrResponse->getStatusCode().' response content length: '.$PsrResponse->getBody()->getSize().PHP_EOL;
            $message = 'Request of '.$request_raw_content_length.' bytes served by worker '.$this->HttpServer->get_worker_id().' in '.($end_time - $start_time).' seconds with response: code: '.$PsrResponse->getStatusCode().' response content length: '.$PsrResponse->getBody()->getSize().PHP_EOL;
            Kernel::log($message, LogLevel::INFO);
            //print 'Last coroutine id '.Coroutine::$last_coroutine_id.PHP_EOL;
        } catch (Throwable $Exception) {
            Kernel::exception_handler($Exception, NULL);//sending NULL as exit code means DO NOT EXIT (no point to kill the whole worker - let only this request fail)



            $DefaultResponseBody = new Stream();
            $DefaultResponseBody->write('Internal server/application error occurred.');
            $PsrResponse = new \Guzaba2\Http\Response(StatusCode::HTTP_INTERNAL_SERVER_ERROR, [], $DefaultResponseBody);
            PsrToSwoole::ConvertResponse($PsrResponse, $SwooleResponse);
        } finally {
            //\Guzaba2\Coroutine\Coroutine::end();//no need
        }
    }

    public function __invoke(\Swoole\Http\Request $Request, \Swoole\Http\Response $Response) : void
    {
        $this->handle($Request, $Response);
    }
}
