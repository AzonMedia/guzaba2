<?php
declare(strict_types=1);

namespace Guzaba2\Swoole;

use Guzaba2\Base\Base;
use Guzaba2\Http\Body\Stream;
use Guzaba2\Http\Response;
use Guzaba2\Http\StatusCode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class ApplicationMiddleware
 * This middleware filters any requests that look like requests to serve static files.
 * Swoole server is not used to serve static files like na ordinary Http server but only as an application server.
 * @package Guzaba2\Swoole
 */
class ApplicationMiddleware extends Base
    implements MiddlewareInterface
{

    public const FILTERED_EXTENSIONS = [
        'js',
        'css',
        'html',
        'png',
        'jpg',
        'gif',
        'svg',
        'xml',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function process(ServerRequestInterface $Request, RequestHandlerInterface $Handler) : ResponseInterface
    {

        if (strtolower($Request->getUri()->getPath() ) == strtolower('localhost')) {
            //just for test - it is not allowed to access the app from localhost - use IP
            $Body = new Stream();
            $Body->write('You are not allowed to access the server over "localhost". Please use the IP instead.');
            $ForbiddenResponse = new \Guzaba2\Http\Response(StatusCode::HTTP_FORBIDDEN, [], $Body);
            return $ForbiddenResponse;
        }

        return $Handler->handle($Request);
    }
}