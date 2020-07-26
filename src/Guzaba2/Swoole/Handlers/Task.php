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
use Guzaba2\Http\QueueRequestHandler;
use Guzaba2\Http\RequestHandler;
use Guzaba2\Http\Response;
use Guzaba2\Http\StatusCode;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Swoole\IpcRequest;
use Guzaba2\Swoole\IpcRequestWithResponse;
use Guzaba2\Swoole\IpcResponse;
use Guzaba2\Translator\Translator as t;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Swoole\Server;

class Task extends HandlerBase
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
     * @var int[]
     */
    private array $running_tasks;

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
    public function __construct(\Guzaba2\Swoole\Server $HttpServer, iterable $middlewares)
    {
        parent::__construct($HttpServer);

        $this->middlewares = $middlewares;
    }

    /**
     * It is part of Guzaba implementation that the $message is a IpcRequest
     * The IpcRequest is also a RequestInterface
     * @param Server $Server
     * @param int $task_id
     * @param int $src_worker_id The worker sending the request
     * @param IpcRequest $IpcRequest
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     */
    public function handle(Server $Server, int $task_id, int $src_worker_id, IpcRequest $IpcRequest): void
    {
        new Event($this, '_before_handle', func_get_args());


        //TODO implement this

        //havea global list with the currently running tasks
        $this->running_tasks[] = $task_id;
        //add an array holding requests for interrupting tasks
        defer(function () use ($task_id) {
            //remove the task from running_tasks
        });

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
    public function __invoke(Server $Server, int $task_id, int $src_worker_id, IpcRequest $IpcRequest): void
    {
        $this->handle($Server, $task_id, $src_worker_id, $IpcRequest);
    }
}
