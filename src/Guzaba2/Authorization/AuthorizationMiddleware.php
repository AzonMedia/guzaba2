<?php
declare(strict_types=1);

namespace Guzaba2\Authorization;

use Guzaba2\Base\Base;
use Guzaba2\Http\Body\Stream;
use Guzaba2\Http\Response;
use Guzaba2\Http\StatusCode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthorizationMiddleware extends Base implements MiddlewareInterface
{
    public function __construct()
    {
        parent::__construct();
    }

    public function process(ServerRequestInterface $Request, RequestHandlerInterface $Handler) : ResponseInterface
    {
        if (strtolower($Request->getUri()->getHost()) == strtolower('localhost')) {
            //just for test - it is not allowed to access the app from localhost - use IP
            $Body = new Stream();
            $Body->write('You are not allowed to access the server over "localhost". Please use the IP instead.');
            $ForbiddenResponse = new \Guzaba2\Http\Response(StatusCode::HTTP_FORBIDDEN, [], $Body);
            return $ForbiddenResponse;
        }

        return $Handler->handle($Request);
    }
}
