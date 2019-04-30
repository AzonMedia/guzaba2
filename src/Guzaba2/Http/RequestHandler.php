<?php


namespace Guzaba2\Http;


use Guzaba2\Base\Base;
use http\Env\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestHandler extends Base
implements RequestHandlerInterface
{

    protected $response;

    public function __construct(?ResponseInterface $psr_response_prototype = NULL)
    {

        if ($psr_response_prototype) {
            $this->response = $psr_response_prototype;
        } else {
            $this->response = (new \Guzaba2\Http\Response())->withStatus(StatusCode::HTTP_NOT_FOUND);
        }

    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        return $this->psr_response_prototype;
    }


    public function __invoke(ServerRequestInterface $request) : ResponseInterface
    {
        return $this->handle($request);
    }
}