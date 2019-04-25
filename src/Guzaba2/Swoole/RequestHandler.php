<?php


namespace Guzaba2\Swoole;

use Guzaba2\Base\Base;
use Guzaba2\Http\Response;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Http\StatusCode;

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
    public function handle(\Swoole\Http\Request $request, \Swoole\Http\Response $response) : void
    {
        //swoole cant use set_exception_handler so everything gets wrapped in try/catch and a manual call to the exception handler
        try {
            //$response->header("Content-Type", "text/plain");
            //$response->end("Hello World\n");

            $psr_response = new Response(StatusCode::HTTP_OK, ['Content-Type', 'text/html'] );
            $psr_response->getBody->write('test response'.Response::EOL);

            $headers = $psr_response->getHeaders();
            $body = $psr_response->getBody();
            foreach ($headers as $header_name => $header_value) {
                $response->header($header_name, $header_value);
            }

        } catch (\Throwable $exception) {
            Kernel::exception_handler($exception);
        }

    }


    public function __invoke(\Swoole\Http\Request $request, \Swoole\Http\Response $response) : void
    {
        $this->handle($request, $response);
    }
}