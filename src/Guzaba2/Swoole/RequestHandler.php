<?php
declare(strict_types=1);

namespace Guzaba2\Swoole;

use Azonmedia\PsrToSwoole\PsrToSwoole;
use Azonmedia\SwooleToPsr\SwooleToPsr;
use Guzaba2\Base\Base;
use Guzaba2\Http\Body\Stream;
use Guzaba2\Http\QueueRequestHandler;
use Guzaba2\Http\Request;
use Guzaba2\Http\Response;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Http\StatusCode;
use Guzaba2\Execution\Execution;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

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
     * @var \Guzaba2\Swoole\Server
     */
    protected $HttpServer;

    public function __construct(array $middlewares = [], \Guzaba2\Swoole\Server $HttpServer, ?Response $DefaultResponse = NULL)
    {
        $this->middlewares = $middlewares;

        $this->HttpServer = $HttpServer;

        if (!$DefaultResponse) {
            $Body = new Stream();
            $Body->write('Content not found or request not understood (routing not configured).');
            $DefaultResponse = new \Guzaba2\Http\Response(StatusCode::HTTP_NOT_FOUND, [], $Body);
        }
        $this->DefaultResponse = $DefaultResponse;
    }

    /**
     * Translates the \Swoole\Http\Request to \Guzaba2\Http\Request (PSR-7)
     * and \Guzaba2\Http\Response (PSR-7) to \Swoole\Http\Response
     * @param \Swoole\Http\Request $request
     * @param \Swoole\Http\Response $response
     */
    public function handle(\Swoole\Http\Request $SwooleRequest, \Swoole\Http\Response $SwooleResponse) : void
    {
        //swoole cant use set_exception_handler so everything gets wrapped in try/catch and a manual call to the exception handler
        try {

            $Execution =& Execution::get_instance();

            $PsrRequest = SwooleToPsr::ConvertRequest($SwooleRequest, new Request() );

            $FallbackHandler = new \Guzaba2\Http\RequestHandler($this->DefaultResponse);//this will produce 404
            $QueueRequestHandler = new QueueRequestHandler($FallbackHandler);//the default response prototype is a 404 message
            foreach ($this->middlewares as $Middleware) {
                $QueueRequestHandler->add_middleware($Middleware);
            }
            $PsrResponse = $QueueRequestHandler->handle($PsrRequest);
            PsrToSwoole::ConvertResponse($PsrResponse, $SwooleResponse);

            //debug
            $request_raw_content_length = $PsrRequest->getBody()->getSize();
            print 'Request of '.$request_raw_content_length.' bytes served with response: code: '.$PsrResponse->getStatusCode().' response content length: '.$PsrResponse->getBody()->getSize().PHP_EOL;

            $Execution->destroy();
        } catch (\Throwable $Exception) {
            Kernel::exception_handler($Exception);
        }

    }

    public function __invoke(\Swoole\Http\Request $Request, \Swoole\Http\Response $Response) : void
    {
        $this->handle($Request, $Response);
    }

}