<?php

namespace Guzaba2\Mvc;

use Guzaba2\Base\Base;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ExecutorMiddleware extends Base
implements MiddlewareInterface
{

    public function __construct()
    {
        parent::__construct();
    }

    public function process(ServerRequestInterface $Request, RequestHandlerInterface $Handler) : ResponseInterface
    {
        return $Handler->handle($Request);
    }
}