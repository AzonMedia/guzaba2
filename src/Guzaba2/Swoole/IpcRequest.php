<?php
declare(strict_types=1);

namespace Guzaba2\Swoole;

use Azonmedia\Routing\Interfaces\RouterInterface;
use Guzaba2\Authorization\CurrentUser;
use Guzaba2\Authorization\Exceptions\PermissionDeniedException;
use Guzaba2\Authorization\User;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Http\Body\Structured;
use Guzaba2\Http\Method;
use Guzaba2\Http\Request;
use Guzaba2\Http\Uri;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Swoole\Interfaces\IpcRequestInterface;
use Guzaba2\Translator\Translator as t;
use Psr\Http\Message\RequestInterface;

/**
 * Class IpcRequest
 * @package Guzaba2\Swoole
 * Represents an Inter Process Communication (IPC) message sent between workers.
 * In Guzaba2 these messages represent requests to execute a controller.
 * When the controller is executed the target worker sends an IpcResponse to the worker which sent the IpcRequest
 *
 * The IPC communication is using sendMessage() instead of task() as the tasks can be sent only to Task Workers while the communication must be possible between all workers.
 */
class IpcRequest extends Request implements IpcRequestInterface
{

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'CurrentUser',
            'Router',//used to validate the route
            'Server',
        ]
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * Should a response be returned back to the calling worker.
     * @var bool
     */
    private bool $requires_response_flag = FALSE;

    /**
     * The request will be executed as this user.
     * @var string
     */
    private string $user_uuid;

    /**
     * The ID of the worker sending the request.
     * @var int
     */
    private int $source_worker_id;

    /**
     * IpcRequest constructor.
     * @param int $method
     * @param string $route
     * @param array $args
     * @param string $user_uuid If provided the request will be done on behalf of the provided user_uuid. Use with extreme care!
     * @throws InvalidArgumentException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\LogicException
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \Guzaba2\Kernel\Exceptions\ConfigurationException
     * @throws \ReflectionException
     */
    public function __construct( /* int|string */ $method, string $route, iterable $args = [], string $user_uuid = '')
    {
        if (!$method) {
            throw new InvalidArgumentException(sprintf(t::_('No $method is provided.')));
        }
        if (is_int($method)) {
            if (!isset(Method::METHODS_MAP[$method])) {
                $valid_method_constants = implode(',', array_map(fn (string $method): string => 'HTTP_' . $method, array_values(Method::METHODS_MAP)));
                throw new InvalidArgumentException(sprintf(t::_('Invalid method constant %1s is provided. The valid constants are %2s::[%3s]'), $method, Method::class, $valid_method_constants));
            }
            $method = Method::METHODS_MAP[$method];
        } elseif (is_string($method)) {
            if (!Method::is_valid_method($method)) {
                throw new InvalidArgumentException(sprintf(t::_('Invalid method string %1s is provided. The valid method names are %2s.'), implode(',', array_values(Method::METHODS_MAP)) ));
            }
        } else {
            throw new InvalidArgumentException(sprintf(t::_('Wrong type %1s provided for $method. Only int and string are supported.'), gettype($method) ));
        }

        if (!$route) {
            throw new InvalidArgumentException(sprintf(t::_('No $route provided.')));
        } else {
            //check is this a valid route...
            //this will be done further down after the parent constructor is invoked by using $this
        }

        if (!$user_uuid) {
            /** @var CurrentUser $CurrentUser */
            $CurrentUser = self::get_service('CurrentUser');
            $user_uuid = $CurrentUser->get()->get_uuid();
        } else {
            //verify the user exists
            try {
                $User = new User($user_uuid);
                if ($User->user_is_disabled) {
                    throw new InvalidArgumentException(sprintf(t::_('The provided $user_uuid %1s corresponds to user %1s which is disabled. IPC requests can not be sent on behalf of disabled users.'), $user_uuid, $User->user_name));
                }
            } catch (RecordNotFoundException $Exception) {
                throw new InvalidArgumentException(sprintf(t::_('There is no user corresponding to the provided $user_uuid %1s.'), $user_uuid));
            } catch (PermissionDeniedException $Exception) {
                throw new LogicException(sprintf(t::_('The user with UUID %1s can not be read. Please check the permissions.'), $user_uuid));
            }

        }
        $this->user_uuid = $user_uuid;

        /** @var Server $Server */
        $Server = self::get_service('Server');
        $this->source_worker_id = $Server->get_worker_id();

        $CurrentRequest = Coroutine::getRequest();
        if ($CurrentRequest) {
            $CurrentUri = $CurrentRequest->getUri();
            //even as the scheme is actually a request over IPC lets leave the original scheme as changing it may cause error in the controller execution
            $Uri = new Uri($CurrentUri->getScheme(), $CurrentUri->getHost(), $CurrentUri->getPort(), $route);
        } else {
            $Uri = new Uri('http', 'localhost', 80, $route);
        }
        //$headers = ['Accept' => ['application/json']];
        $headers = ['Accept' => ['application/php']];//this avoids the ExecutorMiddlware::json_handler() and uses the native_handler() which leaves it untouched
        $cookies = [];
        $server_params = [];//these will be formed in the worker when received - it will create a new Request with new server_params

        $Body = new Structured($args);
        //$this->Request = new Request($method, $Uri, $headers, $cookies, $server_params, $Body);
        parent::__construct($method, $Uri, $headers, $cookies, $server_params, $Body);

        //if the service Router is defined validate the route
        if (self::has_service('Router')) {
            /** @var RouterInterface $Router */
            $Router = self::get_service('Router');
            if ($Router->match_request($this)->getAttribute('controller_callable') === NULL) {
                throw new InvalidArgumentException(sprintf(t::_('The provided route %1s seems invalid (can not be routed).'), $route));
            }
        }

    }

    public function get_source_worker_id(): int
    {
        return $this->source_worker_id;
    }

    /**
     * Returns the UUID of the user which should be used to execute the call.
     * @return string
     */
    public function get_user_uuid(): string
    {
        return $this->user_uuid;
    }

    /**
     * @return string
     */
    public function get_request_id(): string
    {
        return $this->get_object_internal_id();
    }

    /**
     * @param bool $requires
     */
    public function set_requires_response(bool $requires): void
    {
        $this->requires_response_flag = $requires;
    }

    /**
     * @return bool
     */
    public function requires_response(): bool
    {
        return $this->requires_response_flag;
    }
}