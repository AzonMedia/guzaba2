<?php

declare(strict_types=1);

namespace Guzaba2\Swoole\Handlers\Http;

use Azonmedia\Apm\Profiler;
use Azonmedia\Exceptions\InvalidArgumentException;
use Azonmedia\PsrToSwoole\PsrToSwoole;
use Guzaba2\Application\Application;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Event\Event;
use Azonmedia\Http\Body\Stream;
use Azonmedia\Http\Body\Structured;
use Azonmedia\Http\ContentType;
use Azonmedia\Http\Method;
use Guzaba2\Http\QueueRequestHandler;
use Guzaba2\Http\RequestHandler;
use Guzaba2\Http\Response;
use Azonmedia\Http\StatusCode;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Swoole\Server;
use Guzaba2\Swoole\SwooleToGuzaba;
use Guzaba2\Translator\Translator as t;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LogLevel;
use Throwable;

class Request extends HandlerBase
{
    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'Apm',
            'Server',
        ]
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * Array of MiddlewareInterface
     * @var array
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
    public function __construct(Server $HttpServer, iterable $middlewares, ?Response $DefaultResponse = null, ?ResponseInterface $ServerErrorResponse = null)
    {
        parent::__construct($HttpServer);

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

    /**
     *
     * @param \Swoole\Http\Request $SwooleRequest
     * @param \Swoole\Http\Response $SwooleResponse
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function handle(\Swoole\Http\Request $SwooleRequest, \Swoole\Http\Response $SwooleResponse): void
    {
        //swoole cant use set_exception_handler so everything gets wrapped in try/catch and a manual call to the exception handler

        $start_time = microtime(true);

        new Event($this, '_before_handle');
        $request_raw_content_length = 0;
        try {
            $PsrRequest = SwooleToGuzaba::convert_request_with_server_params($SwooleRequest, new \Guzaba2\Http\Request());
            $PsrRequest->setServer($this->HttpServer);
            $request_raw_content_length = $PsrRequest->getBody()->getSize();

            //\Guzaba2\Coroutine\Coroutine::init($this->HttpServer->get_worker_id());
            \Guzaba2\Coroutine\Coroutine::init($PsrRequest);
            $Server = self::get_service('Server');
            $Server->get_worker()->increment_served_requests();

            //TODO - this may be reworked to reroute to a new route (provided in the constructor) instead of providing the actual response in the constructor
            $DefaultResponse = $this->DefaultResponse;
            //if ($PsrRequest->getContentType() === ContentType::TYPE_JSON) {
            if (ContentType::get_content_type_from_message($PsrRequest) === ContentType::TYPE_JSON) {
                $DefaultResponse->getBody()->rewind();
                $structure = ['message' => $DefaultResponse->getBody()->getContents()];
                $json_string = json_encode($structure, JSON_UNESCAPED_SLASHES);
                $StreamBody = new Stream(null, $json_string);
                $DefaultResponse = $DefaultResponse->
                    withBody($StreamBody)->
                    withHeader('Content-Type', ContentType::TYPES_MAP[ContentType::TYPE_JSON]['mime'])->
                    withHeader('Content-Length', (string) strlen($json_string));
            }

            //$FallbackHandler = new \Guzaba2\Http\RequestHandler($this->DefaultResponse);//this will produce 404
            $FallbackHandler = new RequestHandler($DefaultResponse);//this will produce 404
            $QueueRequestHandler = new QueueRequestHandler($FallbackHandler);//the default response prototype is a 404 message
            foreach ($this->middlewares as $Middleware) {
                $QueueRequestHandler->add_middleware($Middleware);
            }
            $PsrResponse = $QueueRequestHandler->handle($PsrRequest);



            //very important to stay here!!!
            //Coroutine::awaitSubCoroutines();//await before the response is converted as the response uses end() which pushes the output
            //also if any of the subcoroutines has an uncaught exception this will catch all these and throw an exception so that the master coroutine is also terminated
            //the above is no longer needed as there is dedicated code for executing subcoroutines Coroutine::executeMulti()

            PsrToSwoole::ConvertResponse($PsrResponse, $SwooleResponse);

            //debug

            //$memory_usage = $Exception->get_memory_usage();



            //$end_time = microtime(TRUE);
            //print microtime(TRUE).' Request of '.$request_raw_content_length.' bytes served by worker '.$this->HttpServer->get_worker_id().' in '.($end_time - $start_time).' seconds with response: code: '.$PsrResponse->getStatusCode().' response content length: '.$PsrResponse->getBody()->getSize().PHP_EOL;
            //$message = 'Request of '.$request_raw_content_length.' bytes for path '.$PsrRequest->getUri()->getPath().' served by worker '.$this->HttpServer->get_worker_id().' in '.($end_time - $start_time).' seconds with response: code: '.$PsrResponse->getStatusCode().' response content length: '.$PsrResponse->getBody()->getSize().PHP_EOL;
            //Kernel::log($message, LogLevel::INFO);
            //Kernel::printk($message);
            //print 'Last coroutine id '.Coroutine::$last_coroutine_id.PHP_EOL;
        } catch (Throwable $Exception) {
            Kernel::exception_handler($Exception);//this just prints the exception, to terminate the execution (kill the worker) send third argument with exit_code

            //$DefaultResponseBody = new Stream();
            //$DefaultResponseBody->write('Internal server/application error occurred.');
            //$PsrResponse = new \Guzaba2\Http\Response(StatusCode::HTTP_INTERNAL_SERVER_ERROR, [], $DefaultResponseBody);
            $PsrResponse = $this->ServerErrorResponse;
            //if ($PsrRequest->getContentType() === ContentType::TYPE_JSON) {
            if (ContentType::get_content_type_from_message($PsrRequest) === ContentType::TYPE_JSON) {
                $PsrResponse->getBody()->rewind();
                $structure = ['message' => $PsrResponse->getBody()->getContents()];
                $json_string = json_encode($structure, JSON_UNESCAPED_SLASHES);
                $StreamBody = new Stream(null, $json_string);
                $PsrResponse = $PsrResponse->
                    withBody($StreamBody)->
                    withHeader('Content-Type', ContentType::TYPES_MAP[ContentType::TYPE_JSON]['mime'])->
                    withHeader('Content-Length', (string) strlen($json_string));
            }
            PsrToSwoole::ConvertResponse($PsrResponse, $SwooleResponse);
            $SwooleResponse->end();//this sends the response

            //$end_time = microtime(TRUE);

            //$message = 'Error occurred while handling request of '.$request_raw_content_length.' bytes for path '.$PsrRequest->getUri()->getPath().' served by worker '.$this->HttpServer->get_worker_id().' in '.($end_time - $start_time).' seconds with response: code: '.$PsrResponse->getStatusCode().' response content length: '.$PsrResponse->getBody()->getSize().PHP_EOL;
            //Kernel::log($message);
            //Kernel::printk($message);
            unset($Exception);//destroy it now  instead of waiting unti lthe end of the scope
        } finally {
            //\Guzaba2\Coroutine\Coroutine::end();//no need
            $end_time = microtime(true);
            //$message = 'Request of '.$request_raw_content_length.' bytes for path '.$PsrRequest->getUri()->getPath().' served by worker #'.$this->HttpServer->get_worker_id().' in '.($end_time - $start_time).' seconds with response: code: '.$PsrResponse->getStatusCode().' response content length: '.$PsrResponse->getBody()->getSize().PHP_EOL;
            //Kernel::printk($message);
            //when using log() the worker # is always printed

            new Event($this, '_after_handle');//lets have it before the very last message about the request.

            $time_str = '';
            if (Application::is_development()) {
                $served_in_time = $end_time - $start_time;

                if ($served_in_time > 1) {
                    $time_str = round($served_in_time, Kernel::MICROTIME_ROUNDING) . ' SECONDS';
                } elseif ($served_in_time > 0.001) {
                    $time_str = (round($served_in_time, Kernel::MICROTIME_ROUNDING) * 1_000) . ' MILLISECONDS';
                } else {
                    $time_str = (round($served_in_time, Kernel::MICROTIME_ROUNDING) * 1_000_000) . ' MICROSECONDS';
                }

                //if ($PsrRequest->getMethodConstant() === Method::HTTP_GET && $served_in_time > 0.005) {
                if (Method::get_method_constant($PsrRequest) === Method::HTTP_GET && $served_in_time > 0.005) {
                    $slow_message = __CLASS__ . ': ' . 'Slow response of ' . $time_str . ' to ' . $PsrRequest->getMethod() . ' request detected (more than 5 milliseconds). Dumping APM data:' . PHP_EOL;
                } elseif ($served_in_time > 0.050) {
                    $slow_message = __CLASS__ . ': ' . 'Slow response of ' . $time_str . ' to ' . $PsrRequest->getMethod() . ' request detected (more than 50 milliseconds). Dumping APM data:' . PHP_EOL;
                } else {
                    //excellent performance !!!
                }
                if (!empty($slow_message)) {
                    /** @var Profiler $Apm */
                    $Apm = self::get_service('Apm');
                    $slow_message .= (string) $Apm;
                    Kernel::log($slow_message, LogLevel::DEBUG);
                }
            }

            $message = '';
            if ($PsrResponse->getStatusCode() !== StatusCode::HTTP_OK) {
                //on failure print additional information if found
                //if ($PsrResponse->getContentType() === ContentType::TYPE_JSON) {
                if (ContentType::get_content_type_from_message($PsrResponse) === ContentType::TYPE_JSON) {
                    $PsrResponse->getBody()->rewind();
                    $contents = $PsrResponse->getBody()->getContents();
                    $PsrResponse->getBody()->rewind();
                    $message = json_decode($contents)->message ?? '';
                }
                if ($message) {
                    $message = ' (' . $message . ')';
                }
            }
            $request_str = '';
            if (Application::is_development()) {
                if ($PsrResponse->getStatusCode() === StatusCode::HTTP_BAD_REQUEST) {
                    //on bad requests dump the request
                    //if ($PsrRequest->getContentType() === ContentType::TYPE_JSON) {
                    if (ContentType::get_content_type_from_message($PsrRequest) === ContentType::TYPE_JSON) {
                        $PsrRequest->getBody()->rewind();
                        $contents = $PsrRequest->getBody()->getContents();
                        $PsrRequest->getBody()->rewind();
                        $request_str = $contents;
                    }
                }
                if ($request_str) {
                    $request_str = ' request: ' . $request_str;
                }
            }

            $log_message = __CLASS__ . ': ' . $PsrRequest->getMethod() . ':' . $PsrRequest->getUri()->getPath() . ' request of ' . $request_raw_content_length . ' bytes served in ' . $time_str . ' with response: code: ' . $PsrResponse->getStatusCode() . '' . $message . ' content length: ' . $PsrResponse->getBody()->getSize() . $request_str . PHP_EOL;
            Kernel::log($log_message, LogLevel::INFO);
        }
    }

    public function __invoke(\Swoole\Http\Request $Request, \Swoole\Http\Response $Response): void
    {
        $this->handle($Request, $Response);
    }
}
