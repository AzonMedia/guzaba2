<?php


namespace Guzaba2\Http;


use Guzaba2\Base\Base;
use Guzaba2\Http\Body\Stream;
use http\Env\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestHandler extends Base
implements RequestHandlerInterface
{

    protected $Response;

    public function __construct(?ResponseInterface $ResponsePrototype = NULL)
    {

        if ($ResponsePrototype) {
            $this->Response = $ResponsePrototype;
        } else {
            $Body = new Stream();
            $Body->write('Content not found');
            $this->Response = (new \Guzaba2\Http\Response())->withStatus(StatusCode::HTTP_NOT_FOUND)->withBody( $Body );
        }

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