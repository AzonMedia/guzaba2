<?php
declare(strict_types=1);

namespace Guzaba2\Authorization;

use Guzaba2\Base\Base;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FilteringMiddleware extends Base implements MiddlewareInterface
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
