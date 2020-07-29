<?php

declare(strict_types=1);

namespace Guzaba2\Routing;

use Azonmedia\Routing\Interfaces\RouterInterface;
use Azonmedia\Routing\Router;
use Guzaba2\Base\Base;
use Guzaba2\Http\Server;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class RoutingMiddleware
 * @link https://restfulapi.net/resource-naming/
 *
 * / Points to a specially defined controller
 * /home - points to APP/home/controllers/main
 * /articles - APP/articles/controllers/main
 * /article/ID - articles/controller/
 *
 * @package Guzaba2\Mvc
 */
class RoutingMiddleware extends Base implements MiddlewareInterface
{

    /**
     * @var \Guzaba2\Http\Server
     */
    //protected Server $HttpServer;

    /**
     * @var RouterInterface
     */
    protected RouterInterface $Router;

    /**
     * RoutingMiddleware constructor.
     * @param Server $HttpServer
     * @param RouterInterface $Router
     */
    //public function __construct(Server $HttpServer, RouterInterface $Router)
    public function __construct(RouterInterface $Router)
    {
        parent::__construct();

        //$this->HttpServer = $HttpServer;

        $this->Router = $Router;
    }

    /**
     * Returns the router
     * @return RouterInterface
     */
    public function get_router(): RouterInterface
    {
        return $this->Router;
    }

    /**
     * {@inheritDoc}
     * @param ServerRequestInterface $Request
     * @param RequestHandlerInterface $Handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $Request, RequestHandlerInterface $Handler): ResponseInterface
    {
        $Request = $this->Router->match_request($Request);
        $Response = $Handler->handle($Request);

        return $Response;
        /*$controller_callable = $this->Router->match_request($Request);
        if ($controller_callable) {
            $Request = $Request->withAttribute('controller_callable', $controller_callable);
        }

        $Response = $Handler->handle($Request);

        return $Response;*/
    }
}
