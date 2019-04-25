<?php


namespace Guzaba2\Http;


use Guzaba2\Base\Base;
use http\Env\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestHandler extends Base
implements RequestHandlerInterface
{


    //@see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-15-request-handlers-meta.md
//    protected $middleware;
//
//    protected $handler;
//
//    public function __construct(MiddlewareInterface $middleware, RequestHandlerInterface $handler)
//    {
//        $this->middleware = $middleware;
//        $this->handler = $handler;
//    }
//
//    public function handle(ServerRequestInterface $request) : ResponseInterface
//    {
//        return $this->middleware->process($request, $this->handler);
//    }

    protected $response;

    public function __construct(?ResponseInterface $response_prototype = NULL)
    {
        if ($response_prototype) {
            $this->response = $response_prototype;
        } else {
            $this->response = new Response();
        }

    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        //no separate routing and authorization layers for now...
        $response = new Response(

        );
        return $response;
    }


    public function __invoke(ServerRequestInterface $request) : ResponseInterface
    {
        return $this->handle($request);
    }
}