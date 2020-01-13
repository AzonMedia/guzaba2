<?php
declare(strict_types=1);

namespace Guzaba2\Mvc;

use Azonmedia\Utilities\ArrayUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Event\Event;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Mvc\Interfaces\AfterControllerMethodHookInterface;
use Guzaba2\Mvc\Interfaces\ControllerInterface;
use Guzaba2\Mvc\Traits\ResponseFactories;
use Guzaba2\Orm\ActiveRecordDefaultController;
use Guzaba2\Translator\Translator as t;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Controller
 * @package Guzaba2\Mvc
 * A basic controller class
 */
abstract class Controller extends Base implements ControllerInterface
{

    protected const CONFIG_DEFAULTS = [
        'services' => [
            'Events',
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    use ResponseFactories;

    private RequestInterface $Request;

    //private ResponseInterface $Response;

    public function __construct(RequestInterface $Request)
    {
        $this->Request = $Request;
    }

    /**
     * @return RequestInterface
     */
    public function get_request() : ?RequestInterface
    {
        return $this->Request;
    }

    /**
     * To be used when an event needs to preset the response.
     * @param ResponseInterface $Response
     */
    public function set_response(ResponseInterface $Response) : void
    {
        $this->Response = $Response;
    }

    /**
     * Returns the response as it may be
     * @return ResponseInterface|null
     */
    public function get_response() : ?ResponseInterface
    {
        return $this->Response;
    }

    /**
     * May be overriden by a child class to provide routing set in an external source like database.
     * Or suppress certain routes based on permissions.
     * This will allow for the routes to be changed without code modification.
     * @return iterable|null
     */
    public static function get_routes() : ?iterable
    {
        return static::CONFIG_RUNTIME['routes'] ?? NULL;
    }

    /**
     * Returns all Controller classes that are loaded by the Kernel in the provided namespace prefixes.
     * Usually the array from Kernel::get_registered_autoloader_paths() is provided to $ns_prefixes
     * @param array $ns_prefixes
     * @return array
     */
    public static function get_controller_classes(array $ns_prefixes) : array
    {
        /*
        $loaded_classes = Kernel::get_loaded_classes();
        $ret = [];
        foreach ($ns_prefixes as $ns_prefix) {
            foreach ($loaded_classes as $loaded_class) {
                $RClass = new ReflectionClass($loaded_class);
                if (
                    strpos($loaded_class, $ns_prefix) === 0
                    && is_a($loaded_class, ControllerInterface::class, TRUE)
                    //&& !in_array($loaded_class, [Controller::class, ActiveRecordDefaultController::class, ControllerInterface::class, ControllerWithAuthorization::class] )
                    && !in_array($loaded_class, [ActiveRecordController::class, Controller::class, ActiveRecordDefaultController::class, ControllerInterface::class] )
                    //&& !in_array($loaded_class, [ActiveRecordDefaultController::class, ControllerInterface::class] )
                    && $RClass->isInstantiable()
                ) {
                    $ret[] = $loaded_class;
                }
            }
        }
        */
        static $controller_classes = [];
        $args_hash = md5(ArrayUtil::array_as_string($ns_prefixes));
        if (!array_key_exists( $args_hash, $controller_classes ) ) {
            $classes = Kernel::get_classes($ns_prefixes, ControllerInterface::class);
            //$classes = array_filter( $classes, fn(string $class) : bool => !in_array($class, [ActiveRecordController::class, Controller::class, ActiveRecordDefaultController::class, ControllerInterface::class]) );
            $classes = array_filter( $classes, fn(string $class) : bool => !in_array($class, [ActiveRecordController::class, Controller::class, ControllerInterface::class]) );
            $controller_classes[$args_hash] = $classes;
        }
        return $controller_classes[$args_hash];
    }


    public static function register_after_hook(string $controller_class_name, string $event_name, callable $hook_callable) : void
    {

        //TODO add a check on the callable signature - must accept a single ResponseInterface argument and return ResponseInterface

        $Events = self::get_service('Events');
        $Callback = static function(Event $Event) use ($Events, $hook_callable) : ResponseInterface
        {

            $arguments = [ $Event->get_return_value() ];

            if (is_array($hook_callable)) {
                $BeforeEvent = $Events::create_event($hook_callable[0], '_before_'.$hook_callable[1], [$Event->get_return_value()], NULL );
                $arguments = $BeforeEvent->get_event_return();
            }

            $HookResponse = $hook_callable(...$arguments);

            if (is_array($hook_callable)) {
                $AfterEvent = $Events::create_event($hook_callable[0], '_after_'.$hook_callable[1], [], $HookResponse);
                $HookResponse = $AfterEvent->get_event_return() ?? $HookResponse;
            }

            return $HookResponse;
        };

        $Events->add_class_callback($controller_class_name, $event_name, $Callback);
    }
}