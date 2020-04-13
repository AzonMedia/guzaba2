<?php
declare(strict_types=1);

namespace Guzaba2\Swoole;

use Guzaba2\Authorization\CurrentUser;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Http\Body\Structured;
use Guzaba2\Http\Method;
use Guzaba2\Http\Request;
use Guzaba2\Http\Uri;
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
            'Events',
        ]
    ];

    protected const CONFIG_RUNTIME = [];

    private bool $requires_response_flag = FALSE;

    /**
     * IpcRequest constructor.
     * @param int $method
     * @param string $route
     * @param array $args
     * @param string $user_uuid
     * @throws InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function __construct(int $method, string $route, iterable $args = [], string $user_uuid = '')
    {
        //parent::__construct();
        if (!isset(Method::METHODS_MAP[$method])) {
            $valid_method_constants = implode(',', array_map( fn(string $method) : string => 'HTTP_'.$method ,array_values(Method::METHODS_MAP)));
            throw new InvalidArgumentException(sprintf(t::_('Invalid method constant %1s is provided. The valid constants are %2s::[%3s]'), $method, Method::class,  $valid_method_constants));
        }
        $method = Method::METHODS_MAP[$method];

        if (!$user_uuid) {
            /** @var CurrentUser $CurrentUser */
            $CurrentUser = self::get_service('CurrentUser');
            $user_uuid = $CurrentUser->get()->get_uuid();//TODO pass this
        }

        $CurrentRequest = Coroutine::getRequest();
        if ($CurrentRequest) {
            $CurrentUri = $CurrentRequest->getUri();
            //even as the scheme is actually a request over IPC lets leave the original scheme as changing it may cause error in the controller execution
            $Uri = new Uri($CurrentUri->getScheme(), $CurrentUri->getHost(), $CurrentUri->getPort(), $route);
        } else {
            $Uri = new Uri('http', 'localhost', 80, $route);
        }
        //$headers = ['Accept' => ['application/json']];
        $headers = ['Accept' => ['application/php']];
        $cookies = [];
        $server_params = [];//these will be formed in the worker when received - it will create a new Request with new server_params

        $Body = new Structured($args);
        //$this->Request = new Request($method, $Uri, $headers, $cookies, $server_params, $Body);
        parent::__construct($method, $Uri, $headers, $cookies, $server_params, $Body);
    }

    public function get_request_id(): string
    {
        return $this->get_object_internal_id();
    }

    public function set_requires_response(bool $requires): void
    {
        $this->requires_response_flag = $requires;
    }

    public function requires_response(): bool
    {
        return $this->requires_response_flag;
    }
}