<?php
declare(strict_types=1);

namespace Guzaba2\Http;

use Guzaba2\Base\Base;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class Middleware
 * NOT USED
 * @package Guzaba2\Http
 */
class RewritingMiddleware extends Base
implements MiddlewareInterface
{


    public function __construct()
    {
    }

    /**
     * Process an incoming server request.
     *
     * Processes an incoming server request in order to produce a response.
     * If unable to produce the response itself, it may delegate to the provided
     * request handler to do so.
     *
     *
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

    }
}