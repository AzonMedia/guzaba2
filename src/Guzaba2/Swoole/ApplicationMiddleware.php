<?php
declare(strict_types=1);

namespace Guzaba2\Swoole;

use Guzaba2\Base\Base;
use Guzaba2\Http\Body\Stream;
use Guzaba2\Http\Response;
use Guzaba2\Http\StatusCode;

use JBZoo\Utils\Str;
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

    /**
     * The file extensions that are to be filtered.
     * If the request ends in any of there it will be filtered
     */
    public const FILE_EXTENSIONS_TO_FILTER = [
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

    /**
     * {@inheritDoc}
     * Will return HTTP_BAD_REQUEST in case a static file is requested (@see self::FILE_EXTENSIONS_TO_FILTER)
     * @param ServerRequestInterface $Request
     * @param RequestHandlerInterface $Handler
     * @return ResponseInterface
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     */
    public function process(ServerRequestInterface $Request, RequestHandlerInterface $Handler) : ResponseInterface
    {

        $request_ok = TRUE;
        $path = $Request->getUri()->getPath();
        foreach (self::FILE_EXTENSIONS_TO_FILTER as $ext) {
            if (Str::isEnd($path, '.'.$ext)) {
                $request_ok = FALSE;
            }
        }

        if (!$request_ok) {
            //just for test - it is not allowed to access the app from localhost - use IP
            $Body = new Stream();
            $Body->write('It seems static content was requested through Swoole. Only application calls are to be servers through Swoole.');
            $BadRequestResponse = new \Guzaba2\Http\Response(StatusCode::HTTP_BAD_REQUEST, [], $Body);
            return $BadRequestResponse;
        }

        return $Handler->handle($Request);
    }
}