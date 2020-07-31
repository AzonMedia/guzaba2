<?php

declare(strict_types=1);

namespace Guzaba2\Http;

use Azonmedia\Apm\Profiler;
use Guzaba2\Base\Base;
use Guzaba2\Event\Event;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class QueueRequestHandler
 * @package Guzaba2\Http
 * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-15-request-handlers-meta.md
 */
class QueueRequestHandler extends Base implements RequestHandlerInterface
{

    protected RequestHandlerInterface $DefaultRequestHandler;

    protected array $middleware_arr = [];

    public function __construct(RequestHandlerInterface $DefaultRequestHandler)
    {
        $this->DefaultRequestHandler = $DefaultRequestHandler;
    }

    /**
     * Supports method chaining.
     * @param MiddlewareInterface $Middleware
     * @return $this
     */
    public function add_middleware(MiddlewareInterface $Middleware): self
    {
        $this->middleware_arr[] = $Middleware;
        return $this;
    }

    /**
     * The registered middlewares are invoked recursively in the order they were added.
     * @param ServerRequestInterface $psr_request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $Request): ResponseInterface
    {
        // Last middleware in the queue has called on the request handler.
        if (0 === count($this->middleware_arr)) {
            return $this->DefaultRequestHandler->handle($Request);
        }

        $Middleware = array_shift($this->middleware_arr);
        new Event($this, '_before_middleware_process', [$Middleware] );
        $Response = $Middleware->process($Request, $this);
        new Event($this, '_after_middleware_process', [$Middleware] );
        return $Response;
    }
}
