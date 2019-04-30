<?php


namespace Guzaba2\Http;


use Guzaba2\Base\Base;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class QueueRequestHandler
 * @package Guzaba2\Http
 * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-15-request-handlers-meta.md
 */
class QueueRequestHandler extends Base
implements RequestHandlerInterface
{

    protected $default_handler;

    public function __construct(RequestHandlerInterface $default_handler)
    {
        $this->default_handler = $default_handler;
    }

    public function add_middleware(MiddlewareInterface $middleware) : self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * The registered middlewares are invoked recursively in the order they were added.
     * @param ServerRequestInterface $psr_request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $psr_request) : ResponseInterface
    {
        // Last middleware in the queue has called on the request handler.
        if (0 === count($this->middleware)) {
            return $this->fallbackHandler->handle($request);
        }

        $middleware = array_shift($this->middleware);
        return $middleware->process($request, $this);
    }
}