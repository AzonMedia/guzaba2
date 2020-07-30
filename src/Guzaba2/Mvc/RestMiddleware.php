<?php

declare(strict_types=1);

namespace Guzaba2\Mvc;

use Azonmedia\Routing\Interfaces\RouterInterface;
use Azonmedia\Http\Body\Stream;
use Azonmedia\Http\Method;
use Azonmedia\Http\StatusCode;
use Guzaba2\Base\Base;
use Guzaba2\Http\Server;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class RestMiddleware
 * Filters the allowed methods to the ones supported by REST
 * @package Guzaba2\Mvc
 */
class RestMiddleware extends Base implements MiddlewareInterface
{

    /**
     * These are the supported HTTP methods as per the REST
     */
    public const SUPPORTED_HTTP_METHODS = [
        //Method::HTTP_CONNECT,
        Method::HTTP_DELETE,
        Method::HTTP_GET,
        Method::HTTP_HEAD,//similar to GET but returns headers only (AKA is the resource available)
        Method::HTTP_OPTIONS,
        Method::HTTP_PATCH,//same like PUT
        Method::HTTP_POST,
        Method::HTTP_PUT,
        //Method::HTTP_TRACE,
    ];

    /**
     * RestMiddleware constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     * @param ServerRequestInterface $Request
     * @param RequestHandlerInterface $Handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $Request, RequestHandlerInterface $Handler): ResponseInterface
    {
        $method_const = $Request->getMethodConstant();
        if (!in_array($method_const, self::SUPPORTED_HTTP_METHODS)) {
            $Body = new Stream();
            $Body->write(sprintf('The request is with method %s which is not a REST method.', $Request->getMethod()));
            $BadRequestResponse = new \Guzaba2\Http\Response(StatusCode::HTTP_BAD_REQUEST, [], $Body);
            return $BadRequestResponse;
        }

        $Response = $Handler->handle($Request);

        return $Response;
    }
}
