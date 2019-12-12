<?php
declare(strict_types=1);

namespace Guzaba2\Mvc;

use Azonmedia\Reflection\ReflectionClass;
use Azonmedia\Utilities\ArrayUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Http\Body\Stream;
use Guzaba2\Http\Body\Structured;
use Guzaba2\Http\Body\Str;
use Guzaba2\Http\Response;
use Guzaba2\Http\StatusCode;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Mvc\Interfaces\ControllerInterface;
use Guzaba2\Mvc\Exceptions\InterruptControllerException;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\ActiveRecordDefaultController;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Translator\Translator as t;
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
class ActiveRecordController extends ActiveRecord //shouldnt be really instantiated
//it is possible to inherit ActiveRecord but this causes names collisions (and possible other issues with properties)
//abstract class Controller extends Base
implements ControllerInterface
{

    protected const CONFIG_DEFAULTS = [
        'main_table'            => 'controllers',
        'route'                 => '/controller',
        //'structure' => []//TODO add structure
        //'controllers_use_db'    => FALSE,
    ];

    protected const CONFIG_RUNTIME = [];

    use ResponseFactories;

    /**
     * @var RequestInterface
     */
    private ?RequestInterface $Request = NULL;

    /**
     * Controller constructor.
     * Allows for initialization with NULL in case it needs to be used as an ActiveRecord instance and not as a controller.
     * If $Request is provided it will be used as a Controller meaning no "read" permission will be checked at creation.
     * The ExecutorMiddleware will only check the permissions of the method being invoked.
     * @param RequestInterface $Request
     */
    //public function __construct(RequestInterface $Request)
    public function __construct(?RequestInterface $Request = NULL)
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

        if ($Request === NULL) { //it is accessed as ActiveRecord and then it needs to be instantiated
            parent::__construct( ['controller_class' => get_class($this)] );
        } else { //it remains as a new record meaning no "read" permission will be checked
            parent::__construct( 0 );
        }

    }

    /**
     * @return RequestInterface
     */
    public function get_request() : ?RequestInterface
    {
        return $this->Request;
    }

    /**
     * Throws RunTimeException if there are issues with the routes.
     * @throws RunTimeException
     */
    public static function validate_routes() : void
    {
        if (!array_key_exists('routes', static::CONFIG_RUNTIME)) {
            throw new RunTimeException(sprintf(t::_('The controller %s has no CONFIG_RUNTIME[\'routes\'] defined. Every controller must have this defined.'), get_called_class() ));
        }
        if (!count(static::CONFIG_RUNTIME['routes'])) {
            throw new RunTimeException(sprintf(t::_('The controller %s has no routes defined in CONFIG_RUNTIME[\'routes\']. There must be at least one route defined.'), get_called_class() ));
        }
        foreach (static::CONFIG_RUNTIME['routes'] as $route => $route_data) {
            if ($route[0] !== '/') {
                throw new RunTimeException(sprintf(t::_('The route "%s" of Controller %s seems wrong. All routes must begin with "/".'), $route, get_called_class()));
            }
        }
    }

    /**
     * May be overriden by a child class to provide routing set in an external source like database.
     * Or suppress certain routes based on permissions.
     * This will allow for the routes to be changed without code modification.
     * @return iterable|null
     */
    public static function get_routes() : ?iterable
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
            $classes = array_filter( $classes, fn(string $class) : bool => !in_array($class, [ActiveRecordController::class, Controller::class, ActiveRecordDefaultController::class, ControllerInterface::class]) );
            $controller_classes[$args_hash] = $classes;
        }
        return $controller_classes[$args_hash];
    }
}
