<?php


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

    public function __construct(array $middlewares = [], ?Response $DefaultResponse = NULL)
    {
        $this->middlewares = $middlewares;

        if (!$DefaultResponse) {
            $Body = new Stream();
            $Body->write('Content not found');
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

            //$response->header("Content-Type", "text/plain");
            //$response->end("Hello World\n");
            //temp code
            //$psr_response = new Response(StatusCode::HTTP_OK, ['Content-Type' => 'text/html'] );
            //$psr_response->getBody()->write('test response'.Response::EOL);
            //PsrToSwoole::ConvertResponse($psr_response, $swoole_response);

            //$r = new PsrToSwoole();
            //$r2 = new SwooleToPsr();
            //$psr_request = new Request();
            $PsrRequest = SwooleToPsr::ConvertRequest($SwooleRequest, new Request() );

            $FallbackHandler = new \Guzaba2\Http\RequestHandler($this->DefaultResponse);//this will produce 404
            $QueueRequestHandler = new QueueRequestHandler($FallbackHandler);//the default response prototype is a 404 message
            foreach ($this->middlewares as $Middleware) {
                $QueueRequestHandler->add_middleware($Middleware);
            }
            $PsrResponse = $QueueRequestHandler->handle($PsrRequest);
            PsrToSwoole::ConvertResponse($PsrResponse, $SwooleResponse);

            //debug
            print 'Request served with response: code: '.$PsrResponse->getStatusCode().' content length: '.$PsrResponse->getBody()->getSize().PHP_EOL;

            $Execution->destroy();
        } catch (\Throwable $exception) {
            Kernel::exception_handler($exception);
        }

    }

    public function __invoke(\Swoole\Http\Request $Request, \Swoole\Http\Response $Response) : void
    {
        $this->handle($Request, $Response);
    }

}