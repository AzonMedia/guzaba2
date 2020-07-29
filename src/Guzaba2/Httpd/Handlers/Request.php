<?php

declare(strict_types=1);

namespace Guzaba2\Httpd\Handlers;


use Azonmedia\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Http\Body\Stream;
use Guzaba2\Http\ContentType;
use Guzaba2\Http\Interfaces\ServerInterface;
use Guzaba2\Http\QueueRequestHandler;
use Guzaba2\Http\RequestHandler;
use Guzaba2\Http\Response;
use Guzaba2\Http\Server;
use Guzaba2\Http\StatusCode;
use Guzaba2\Translator\Translator as t;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\ResponseEmitter;

class Request extends Base
{


    protected ServerInterface $HttpServer;

    /**
     * @var MiddlewareInterface[]
     */
    protected iterable $middlewares = [];

    /**
     * @var ResponseInterface
     */
    protected ResponseInterface $DefaultResponse;

    protected ResponseInterface $ServerErrorResponse;

    /**
     * RequestHandler constructor.
     * @param Server $HttpServer
     * @param array $middlewares
     * @param Response|null $DefaultResponse
     * @param ResponseInterface|null $ServerErrorResponse
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function __construct(ServerInterface $HttpServer, iterable $middlewares, ?ResponseInterface $DefaultResponse = null, ?ResponseInterface $ServerErrorResponse = null)
    {


        $this->middlewares = $middlewares;

        if (!$DefaultResponse) {
            $message = t::_('Content not found or request not understood. The request contains a method and route that could not be found.');
            $Body = new Stream();
            $Body->write($message);
            $Body->rewind();
            $DefaultResponse = (new Response(StatusCode::HTTP_NOT_FOUND, [], $Body) )->withHeader('Content-Length', (string) strlen($message));
        }
        $this->DefaultResponse = $DefaultResponse;



        if (!$ServerErrorResponse) {
            $message = t::_('Internal server/application error occurred.');
            $Body = new Stream();
            $Body->write($message);
            $Body->rewind();
            $ServerErrorResponse = (new Response(StatusCode::HTTP_INTERNAL_SERVER_ERROR, [], $Body) )->withHeader('Content-Length', (string) strlen($message));
        }
        $this->ServerErrorResponse = $ServerErrorResponse;


    }

    //public function handle(ServerRequestInterface $Request, ResponseInterface $Response): void
    //public function handle(array $server_array, string $response)
    /**
     * As this method works with PHP native request there is no need of arguments and return
     * It uses PHP superglobals and prints directly to the output.
     */
    public function handle(): void
    {
        $globals = $_SERVER;
        //$SlimPsrRequest = ServerRequestFactory::createFromGlobals();
        //it doesnt matters if the Request is of different class - no need to create Guzaba\Http\Request
        $PsrRequest = ServerRequestFactory::createFromGlobals();
        //the only thing that needs to be fixed is the update the parsedBody if it is NOT POST & form-fata or url-encoded


        //$GuzabaPsrRequest =

        //TODO - this may be reworked to reroute to a new route (provided in the constructor) instead of providing the actual response in the constructor
        $DefaultResponse = $this->DefaultResponse;
        //TODO - fix the below
//        if ($PsrRequest->getContentType() === ContentType::TYPE_JSON) {
//            $DefaultResponse->getBody()->rewind();
//            $structure = ['message' => $DefaultResponse->getBody()->getContents()];
//            $json_string = json_encode($structure, JSON_UNESCAPED_SLASHES);
//            $StreamBody = new Stream(null, $json_string);
//            $DefaultResponse = $DefaultResponse->
//            withBody($StreamBody)->
//            withHeader('Content-Type', ContentType::TYPES_MAP[ContentType::TYPE_JSON]['mime'])->
//            withHeader('Content-Length', (string) strlen($json_string));
//        }

        $FallbackHandler = new RequestHandler($DefaultResponse);//this will produce 404
        $QueueRequestHandler = new QueueRequestHandler($FallbackHandler);//the default response prototype is a 404 message
        foreach ($this->middlewares as $Middleware) {
            $QueueRequestHandler->add_middleware($Middleware);
        }
        $PsrResponse = $QueueRequestHandler->handle($PsrRequest);
        $this->print_output($PsrResponse);

    }

    public function __invoke(): void
    {
        $this->handle();
    }

    protected function print_output(ResponseInterface $Response): void
    {
        $ResponseEmitter = new ResponseEmitter();
        $ResponseEmitter->emit($Response);

    }
}