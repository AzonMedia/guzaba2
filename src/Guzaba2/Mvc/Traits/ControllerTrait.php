<?php
declare(strict_types=1);

namespace Guzaba2\Mvc\Traits;


use Azonmedia\Reflection\ReflectionClass;
use Azonmedia\Reflection\ReflectionFunction;
use Azonmedia\Reflection\ReflectionMethod;
use Guzaba2\Authorization\Role;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Mvc\ActiveRecordController;
use Guzaba2\Mvc\ExecutorMiddleware;
use Psr\Http\Message\RequestInterface;
use Guzaba2\Translator\Translator as t;
use Psr\Http\Message\ResponseInterface;

trait ControllerTrait
{

    /**
     * @return RequestInterface
     */
    public function get_request() : ?RequestInterface
    {
        return $this->Request;
    }

    //========================= STATIC METHODS ============================

    /**
     * Returns an array of strings (action/method names)
     * @return array
     * @throws \ReflectionException
     */
    public static function get_actions() : array
    {
        $ret = [];
        $class = get_called_class();
        $RClass = new ReflectionClass($class);
        foreach ($RClass->getOwnMethods(\ReflectionMethod::IS_PUBLIC) as $RMethod) {
            if (!$RMethod->isStatic() && $RMethod->getName()[0] !== '_') {
                $ret[] = $RMethod->getName();
            }
        }
        return $ret;
    }

    /**
     * Returns the actions on this class that can be performed by $Role
     * @param Role $Role
     * @return array
     * @throws \ReflectionException
     */
    public static function get_actions_role_can_perform(Role $Role) : array
    {
        $ret = [];
        $class = get_called_class();
        $actions = self::get_actions();
        $AuthorizationProvider = self::get_service('AuthorizationProvider');
        foreach ($actions as $action) {
            if ($AuthorizationProvider->role_can_on_class($Role, $action, $class)) {
                $ret[] = $action;
            }
        }
        return $ret;
    }

    /**
     * Executes a $controller_callable and returns the Response.
     * @param callable $controller_callable
     * @param array $arguments
     * @return ResponseInterface
     */
    public static function execute_controller(callable $controller_callable, array $arguments) : ResponseInterface
    {
        if (is_array($controller_callable)) {
            $Response = (new ExecutorMiddleware())->execute_controller_method($controller_callable[0], $controller_callable[1], $arguments);
        } else {
            $Response = $controller_callable(...$arguments);
        }
        return $Response;
    }

    /**
     * Executes a $controller_callable that returns a Structured body Response.
     * Returns the structure (array) of the response.
     * @param callable $controller_callable
     * @param array $arguments
     * @return array
     */
    public static function execute_structured_controller(callable $controller_callable, array $arguments) : array
    {
        return self::execute_controller($controller_callable, $arguments)->getBody()->getStructure();
    }

    /**
     * Returns a string representation of a controller callable.
     * @param callable $controller_callable
     * @return string
     * @throws InvalidArgumentException
     * @throws \ReflectionException
     */
    public static function get_controller_callable_as_string(callable $controller_callable) : string
    {
        $controller_str = '';
        if (is_array($controller_callable) && count($controller_callable) === 2) {
            $controller_str = get_class($controller_callable[0]).'::'.$controller_callable[1].'('.(new ReflectionMethod(get_class($controller_callable[0]), $controller_callable[1]))->getParametersList().')';
        } elseif ($controller_callable instanceof \Closure) {
            $controller_str = 'function('.(new ReflectionFunction($controller_callable))->getParametersList().')';
        } elseif (method_exists($controller_callable,'__invoke')) {
            $controller_str = get_class($controller_callable).'::__invoke('.(new ReflectionMethod($controller_callable, '__invoke'))->getParametersList().')';
        } elseif (is_string($controller_callable)) {
            $controller_str = $controller_callable.'('.(new ReflectionFunction($controller_callable))->getParametersList().')';
        } else {
            throw new InvalidArgumentException(sprintf(t::_('An unsupported type %s or invalid value is provided.'), gettype($controller_callable) ));
        }
        return $controller_str;
    }
}