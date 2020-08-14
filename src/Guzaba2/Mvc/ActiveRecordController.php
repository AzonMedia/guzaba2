<?php

declare(strict_types=1);

namespace Guzaba2\Mvc;

use Azonmedia\Reflection\ReflectionClass;
use Azonmedia\Utilities\ArrayUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Azonmedia\Http\Body\Stream;
use Azonmedia\Http\Body\Structured;
use Azonmedia\Http\Body\Str;
use Guzaba2\Http\Response;
use Azonmedia\Http\StatusCode;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Mvc\Interfaces\ControllerInterface;
use Guzaba2\Mvc\Exceptions\InterruptControllerException;
use Guzaba2\Mvc\Traits\ControllerTrait;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\ActiveRecordDefaultController;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Swoole\IpcRequest;
use Guzaba2\Swoole\Server;
use Guzaba2\Translator\Translator as t;
use GuzabaPlatform\AppServer\Monitor\Controllers\Responder;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Guzaba2\Mvc\Traits\ResponseFactories;

/**
 * Class Controller
 * A base class representing a controller. All controllers should inherit this class.
 * TODO add execute event
 * @package Guzaba2\Mvc
 */
//abstract class Controller extends ActiveRecord
class ActiveRecordController extends ActiveRecord implements ControllerInterface //shouldnt be really instantiated //it is possible to inherit ActiveRecord but this causes names collisions (and possible other issues with properties) //abstract class Controller extends Base
{

    protected const CONFIG_DEFAULTS = [
        'main_table'            => 'controllers',
        'route'                 => '/controller',
        //'structure' => []//TODO add structure
        //'controllers_use_db'    => FALSE,
        'services'              => [
            'Server',
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    use ResponseFactories;
    use ControllerTrait;

    /**
     * @var RequestInterface
     */
    // private ?RequestInterface $Request = NULL;
    private $Request = null;

    private ?ResponseInterface $Response = null;

    /**
     * Controller constructor.
     * Allows for initialization with NULL in case it needs to be used as an ActiveRecord instance and not as a controller.
     * If $Request is provided it will be used as a Controller meaning no "read" permission will be checked at creation.
     * The ExecutorMiddleware will only check the permissions of the method being invoked.
     * @param RequestInterface $Request
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\LogicException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \Guzaba2\Kernel\Exceptions\ConfigurationException
     * @throws \ReflectionException
     */
    //public function __construct(RequestInterface $Request)
    public function __construct($Request = null)
    {
        $this->Request = $Request;
//        if ($Request === NULL) { //it is accessed as ActiveRecord and then it needs to be instantiated
//            if (!empty(self::CONFIG_RUNTIME['controllers_use_db'])) {
//                parent::__construct( ['controller_class' => get_class($this)] );
//            } else {
//                parent::__construct( 0 );
//            }
//        } else { //it remains as a new record meaning no "read" permission will be checked
//            parent::__construct( 0 );
//        }

        if ($Request === null) { //it is accessed as ActiveRecord and then it needs to be instantiated
            //to be used when the controllers will have records in the DB and will be managed as ActiveRecords
            parent::__construct(['controller_class' => get_class($this)]);
        } else { //it remains as a new record meaning no "read" permission will be checked - only the permission associated with the requested action (method of the class) will be checked
            parent::__construct(0);
        }
    }

    public function __toString(): string
    {
        // TODO: Implement __toString() method.
        return get_class($this);
    }

//    /**
//     * To be used when an event needs to preset the response.
//     * @param ResponseInterface $Response
//     */
//    public function set_response(ResponseInterface $Response) : void
//    {
//        $this->Response = $Response;
//    }
//
//    /**
//     * Returns the response as it may be
//     * @return ResponseInterface|null
//     */
//    public function get_response() : ?ResponseInterface
//    {
//        return $this->Response;
//    }

    /**
     * May be overriden by a child class to provide routing set in an external source like database.
     * Or suppress certain routes based on permissions.
     * This will allow for the routes to be changed without code modification.
     * @return iterable|null
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public static function get_routes(): ?iterable
    {
        //return static::CONFIG_RUNTIME['routes'];
        if (array_key_exists('routes', static::CONFIG_RUNTIME)) {
            $ret = static::CONFIG_RUNTIME['routes'];
        } else {
            $ret = parent::get_routes();
        }
        return $ret;
    }

    /**
     * Executes a $method on this class
     * Returns the response.
     * @param string $method
     * @param array $arguments
     * @return ResponseInterface
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function execute_action(string $method, array $arguments = []): ResponseInterface
    {
        return (new ExecutorMiddleware())->execute_controller_method($this, $method, $arguments);
    }

    /**
     * Executes a $method on this controller that returns a structured response.
     * Returns the structure (array) of the response.
     * @param string $method
     * @param array $arguments
     * @return array
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function execute_structured_action(string $method, array $arguments = [], ?int &$status_code = null): array
    {
        //return $this->execute_action($method, $arguments)->getBody()->getStructure();
        $Response = $this->execute_action($method, $arguments);
        $status_code = $Response->getStatusCode();
        return $Response->getBody()->getStructure();
    }

    /**
     * Executes the provided $action on an ActiveRecordController class that returns Structured body Response.
     * Returns the structure (array) of the response.
     * @param string $controller_class
     * @param string $method
     * @param array $arguments
     * @return array
     * @throws InvalidArgumentException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function execute_controller_action_structured(string $controller_class, string $method, array $arguments = [], ?int &$status_code = null): array
    {
        //return $this->execute_controller_action($controller_class, $method, $arguments)->getBody()->getStructure();
        $Response = $this->execute_controller_action($controller_class, $method, $arguments);
        $status_code = $Response->getStatusCode();
        return $Response->getBody()->getStructure();
    }

    /**
     * Executes the provided $action on an ActiveRecordController class.
     * Returns the response from the action.
     * @param string $controller_class
     * @param string $method
     * @param array $arguments
     * @return ResponseInterface
     * @throws InvalidArgumentException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function execute_controller_action(string $controller_class, string $method, array $arguments = []): ResponseInterface
    {
        if (!class_exists($controller_class)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided class %1$s does not exist.'), $controller_class));
        }
        if (!is_a($controller_class, ActiveRecordController::class, true)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided class %1$s is not a %2$s.'), ActiveRecordController::class));
        }
        if (!method_exists($controller_class, $method)) {
            throw new InvalidArgumentException(sprintf(t::_('The controller class %1$s does not have a method $2s.'), $controller_class, $method));
        }
        $controller_callable = [ new $controller_class($this->get_request()), $method ];
        return self::execute_controller($controller_callable, $arguments);
    }

    /**
     * Executes a request on the provided $worker_ids list.
     * The current one is included it executes the request directly , not over IPC as sending an IpcRequest to yourself is not supported.
     * @param int $method
     * @param string $route
     * @param string $controller_class
     * @param string $action
     * @param array $arguments
     * @return array
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\LogicException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \Guzaba2\Kernel\Exceptions\ConfigurationException
     * @throws \ReflectionException
     */
    public function execute_broadcast_request(int $method, string $route, string $controller_class, string $action, array $arguments = []): array
    {
        $ret = [];

        /** @var Server $Server */
        $Server = self::get_service('Server');
        $worker_ids = range(0, $Server->get_total_workers() - 1);//the workers IDs start from 0

        return $this->execute_multicast_request($worker_ids, $method, $route, $controller_class, $action, $arguments = []);
    }

    /**
     * Executes a request on all workers including the current one.
     * The current one executes the request directly , not over IPC as sending an IpcRequest to yourself is not supported.
     * @param array $worker_ids
     * @param int $method
     * @param string $route
     * @param string $controller_class
     * @param string $action
     * @param array $arguments
     * @return array
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\LogicException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \Guzaba2\Kernel\Exceptions\ConfigurationException
     * @throws \ReflectionException
     */
    public function execute_multicast_request(array $worker_ids, int $method, string $route, string $controller_class, string $action, array $arguments = []): array
    {
        if (!count($worker_ids)) {
            throw new InvalidArgumentException(sprintf(t::_('No worker IDs provided.')));
        }

        $ret = [];

        /** @var Server $Server */
        $Server = self::get_service('Server');

        $IpcRequest = new IpcRequest($method, $route, $arguments);
        $ipc_responses = $Server->send_broadcast_ipc_request($IpcRequest);

        foreach ($ipc_responses as $IpcResponse) {
            if ($IpcResponse) {
                $from_worker_id = $IpcResponse->get_source_worker_id();
                $ret[$from_worker_id]['worker_id'] = $from_worker_id;
                $ret[$from_worker_id]['code'] = $IpcResponse->getStatusCode();
                if ($IpcResponse->getStatusCode() === StatusCode::HTTP_OK) {
                    $ret[$from_worker_id]['data'] = $IpcResponse->getBody()->getStructure();
                } else {
                    $ret[$from_worker_id]['data'] = sprintf(t::_('Worker ID %1$s returned HTTP status code %2$s.'), $from_worker_id, $IpcResponse->getStatusCode());
                }
            }
        }

        $this_worker_id = $Server->get_worker_id();
        //we need to add the current worker data
        $data = $this->execute_controller_action_structured($controller_class, $action, $arguments, $status_code);
        $ret[$this_worker_id]['worker_id'] = $this_worker_id;
        $ret[$this_worker_id]['code'] = $status_code;
        $ret[$this_worker_id]['data'] = $data;


        //add entries for the workers from which we didnt receive data
        foreach ($worker_ids as $worker_id) {
            if (!isset($ret[$worker_id])) {
                $ret[$worker_id]['worker_id'] = $worker_id;
                $ret[$worker_id]['code'] = StatusCode::HTTP_CONNECTION_CLOSED_WITHOUT_RESPONSE;//a custom code to denote that no response was received from the worker
                $ret[$worker_id]['data'] = sprintf(t::_('No response received from worker %1$s.'), $worker_id);
            }
        }

        ksort($ret);
        //if the worker ID in future changes from 0-X to something else then it may be better to preserve the presentation in JSON as array to send back just the values;
        $ret = array_values($ret);

        return $ret;
    }
}
