<?php

namespace Guzaba2\Mvc;

use Guzaba2\Base\Base;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;


class RoutingMiddleware extends Base
implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        //basic logic
        if ($request->getUri()->getPath() == '/') {
            //server the home page
        }


    }
}