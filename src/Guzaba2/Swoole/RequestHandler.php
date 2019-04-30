<?php


namespace Guzaba2\Swoole;

use Azonmedia\PsrToSwoole\PsrToSwoole;
use Azonmedia\SwooleToPsr\SwooleToPsr;
use Guzaba2\Base\Base;
use Guzaba2\Http\Request;
use Guzaba2\Http\Response;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Http\StatusCode;
use Guzaba2\Execution\Execution;

class RequestHandler extends Base
{


    protected $response;

    public function __construct()
    {

    }

    /**
     * Translates the \Swoole\Http\Request to \Guzaba2\Http\Request (PSR-7)
     * and \Guzaba2\Http\Response (PSR-7) to \Swoole\Http\Response
     * @param \Swoole\Http\Request $request
     * @param \Swoole\Http\Response $response
     */
    public function handle(\Swoole\Http\Request $swoole_request, \Swoole\Http\Response $swoole_response) : void
    {
        //swoole cant use set_exception_handler so everything gets wrapped in try/catch and a manual call to the exception handler
        try {

            $execution =& Execution::get_instance();

            //$response->header("Content-Type", "text/plain");
            //$response->end("Hello World\n");
            //temp code
            $psr_response = new Response(StatusCode::HTTP_OK, ['Content-Type' => 'text/html'] );
            $psr_response->getBody()->write('test response'.Response::EOL);
            PsrToSwoole::ConvertResponse($psr_response, $swoole_response);


            $execution->destroy();
        } catch (\Throwable $exception) {
            Kernel::exception_handler($exception);
        }

    }


    public function __invoke(\Swoole\Http\Request $request, \Swoole\Http\Response $response) : void
    {
        $this->handle($request, $response);
    }

//    public static function SwooleToPsrRequest(\Swoole\Http\Request $request) : Request
//    {
//
//    }
//
//    //public static function PsrToSwooleResponse(Response $psr_response) : \Swoole\Http\Response
//    public static function PsrToSwooleResponse(Response $psr_response, \Swoole\Http\Response $swoole_response) : void
//    {
//
//        $headers = $psr_response->getHeaders();
//        foreach ($headers as $header_name => $header_value) {
//            $swoole_response->header($header_name, $header_value);
//        }
//
//        $body = $psr_response->getBody();
//        $output = (string) $body;
//        $swoole_response->write($output);
//    }
}