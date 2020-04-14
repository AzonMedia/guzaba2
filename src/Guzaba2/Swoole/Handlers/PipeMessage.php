<?php
declare(strict_types=1);


namespace Guzaba2\Swoole\Handlers;

use Azonmedia\Exceptions\InvalidArgumentException;
use Guzaba2\Authorization\CurrentUser;
use Guzaba2\Authorization\Exceptions\PermissionDeniedException;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Event\Event;
use Guzaba2\Http\Body\Stream;
use Guzaba2\Http\Body\Structured;
use Guzaba2\Http\QueueRequestHandler;
use Guzaba2\Http\RequestHandler;
use Guzaba2\Http\Response;
use Guzaba2\Http\StatusCode;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Swoole\Handlers\Http\Request;
use Guzaba2\Swoole\IpcRequest;
use Guzaba2\Swoole\IpcRequestWithResponse;
use Guzaba2\Swoole\IpcResponse;
use Guzaba2\Swoole\SwooleToGuzaba;
use Guzaba2\Translator\Translator as t;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Swoole\Server;

class PipeMessage extends HandlerBase
{

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'CurrentUser',
            'Events',
        ]
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * @var MiddlewareInterface[]
     */
    protected iterable $middlewares = [];

    /**
     * @var ResponseInterface
     */
    protected ResponseInterface $DefaultResponse;

    protected ResponseInterface $ServerErrorResponse;

    //TODO move the constructor in a trait or method - duplicate with http\request::__constrcut

    /**
     * RequestHandler constructor.
     * @param \Guzaba2\Swoole\Server $HttpServer
     * @param array $middlewares
     * @param Response|null $DefaultResponse
     * @param ResponseInterface|null $ServerErrorResponse
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function __construct(\Guzaba2\Swoole\Server $HttpServer, iterable $middlewares, ?Response $DefaultResponse = NULL, ?ResponseInterface $ServerErrorResponse = NULL)
    {
        parent::__construct($HttpServer);

        $this->middlewares = $middlewares;

        if (!$DefaultResponse) {
            $message = t::_('Content not found or request not understood (routing not configured).');
            $Body = new Stream();
            $Body->write($message);
            $DefaultResponse = (new Response(StatusCode::HTTP_NOT_FOUND, [], $Body) )->withHeader('Content-Length', (string) strlen($message));
        }
        $this->DefaultResponse = $DefaultResponse;

        if (!$ServerErrorResponse) {
            $message = t::_('Internal server/application error occurred.');
            $Body = new Stream();
            $Body->write($message);
            $ServerErrorResponse = (new Response(StatusCode::HTTP_INTERNAL_SERVER_ERROR, [], $Body) )->withHeader('Content-Length', (string) strlen($message));
        }
        $this->ServerErrorResponse = $ServerErrorResponse;
    }

    /**
     * It is part of Guzaba implementation that the $message is a IpcRequest
     * The IpcRequest is also a RequestInterface
     * @param Server $Server
     * @param int $src_worker_id The worker sending the request
     * @param IpcRequest $IpcRequest
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\LogicException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \Guzaba2\Kernel\Exceptions\ConfigurationException
     * @throws \ReflectionException
     */
    public function handle(Server $Server, int $src_worker_id, IpcRequest $IpcRequest): void
    {
        new Event($this, '_before_handle', func_get_args());

        if ($IpcRequest instanceof IpcRequestWithResponse) { //this is a response (pingback to a IpcRequest sent earlier)
            $this->HttpServer->set_ipc_request_response($IpcRequest->get_response(), $IpcRequest->get_response()->get_request_id(), $src_worker_id);
        } else { //this is a request from another worker

            $IpcRequest->setServer($this->HttpServer);
            $time = time();
            $server_params = [
                'ipc_request'           => TRUE,
                'src_worker_id'         => $src_worker_id,
                'request_uri'           => $IpcRequest->getUri()->getPath(),
                'path_info'             => $IpcRequest->getUri()->getPath(),
                'request_time'          => $time,
                'request_time_float'    => microtime(true),
                'server_protocol'       => 'HTTP/'.$IpcRequest->getProtocolVersion(),
                'server_port'           => $IpcRequest->getUri()->getPort(),
                'remote_port'           => $IpcRequest->getUri()->getPort(),//43826
                'remote_addr'           => '127.0.0.1',
                'master_time'           => $time,

            ];
            $IpcRequest = $IpcRequest->withServerParams($server_params);

            Coroutine::init($IpcRequest);

            /** @var CurrentUser $CurrentUser */
            $CurrentUser = self::get_service('CurrentUser');
            $user_uuid = $IpcRequest->get_user_uuid();
            $user_class = $CurrentUser->get_default_user_class();
            try {
                $User = new $user_class($user_uuid);
            } catch (RecordNotFoundException $Exception) {
                throw new LogicException(sprintf(t::_('There is no user corresponding to the provided $user_uuid %1s for the IPC request.'), $user_uuid));
            } catch (PermissionDeniedException $Exception) {
                throw new LogicException(sprintf(t::_('The user with UUID %1s can not be read. Please check the user permissions.'), $user_uuid));
            }
            $CurrentUser->set($User);

            $FallbackHandler = new RequestHandler($this->DefaultResponse);//this will produce 404
            $QueueRequestHandler = new QueueRequestHandler($FallbackHandler);//the default response prototype is a 404 message
            foreach ($this->middlewares as $Middleware) {
                $QueueRequestHandler->add_middleware($Middleware);
            }
            $Response = $QueueRequestHandler->handle($IpcRequest);

            if ($IpcRequest->requires_response()) {
                $IpcResponse = new IpcResponse($Response, $IpcRequest->get_request_id());
                $IpcRequestWithResponse = new IpcRequestWithResponse($IpcResponse);
                $Server->sendMessage($IpcRequestWithResponse, $src_worker_id);

            }

        }
        new Event($this, '_after_handle', func_get_args());
    }

    /**
     * @param Server $Server
     * @param int $src_worker_id
     * @param IpcRequest $IpcRequest
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws RunTimeException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \Guzaba2\Kernel\Exceptions\ConfigurationException
     * @throws \ReflectionException
     */
    public function __invoke(Server $Server, int $src_worker_id, IpcRequest $IpcRequest) : void
    {
        $this->handle($Server, $src_worker_id, $IpcRequest);
    }
}
