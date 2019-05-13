<?php
declare(strict_types=1);

namespace Guzaba2\Http;


use Guzaba2\Base\Base;
use Guzaba2\Http\Body\Stream;
use http\Env\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class RequestHandler
 * This is just a basic request handler that will serve the provided $ResponsePrototype
 * @package Guzaba2\Http
 */
class RequestHandler extends Base
implements RequestHandlerInterface
{

    protected $Response;

    public function __construct(?ResponseInterface $ResponsePrototype = NULL)
    {
        $this->Response = $ResponsePrototype;
    }

    public function handle(ServerRequestInterface $Request) : ResponseInterface
    {
        return $this->Response;
    }


    public function __invoke(ServerRequestInterface $Request) : ResponseInterface
    {
        return $this->handle($Request);
    }
}