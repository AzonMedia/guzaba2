<?php
declare(strict_types=1);

namespace Guzaba2\Mvc;

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
class RoutingMiddleware extends Base
implements MiddlewareInterface
{

    /**
     * @var \Guzaba2\Http\Server
     */
    protected $HttpServer;
    
    public function __construct(Server $HttpServer, )
    {
        parent::__construct();

        $this->HttpServer = $HttpServer;
    }

    public function process(ServerRequestInterface $Request, RequestHandlerInterface $Handler) : ResponseInterface
    {
        //basic logic
        if ($Request->getUri()->getPath() == '/') {
            //server the home page
        }
        return $Handler->handle($Request);

    }
}