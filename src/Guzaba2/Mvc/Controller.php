<?php


namespace Guzaba2\Mvc;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Http\Body\Stream;
use Guzaba2\Http\Body\Structured;
use Guzaba2\Http\Body\Str;
use Guzaba2\Http\Response;
use Guzaba2\Http\StatusCode;
use Guzaba2\Mvc\Interfaces\ControllerInterface;
use Guzaba2\Mvc\Exceptions\InterruptControllerException;
use Guzaba2\Orm\ActiveRecord;
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
//it is possible to inherit ActiveRecord but this causes names collisions (and possible other issues with properties)
abstract class Controller extends Base
implements ControllerInterface
{

    use ResponseFactories;

    /**
     * @var RequestInterface
     */
    private $Request;

    /**
     * Controller constructor.
     * @param RequestInterface $Request
     */
    public function __construct(RequestInterface $Request)
    {
        parent::__construct();
        $this->Request = $Request;
    }

    /**
     * @return RequestInterface
     */
    public function get_request() : RequestInterface
    {
        return $this->Request;
    }

    /**
     * May be overriden by a child class to provide routing set in an external source like database.
     * Or suppress certain routes based on permissions.
     * This will allow for the routes to be changed without code modification.
     * @return iterable|null
     */
    public static function get_routes() : ?iterable
    {
        $ret = NULL;

        if (defined('static::ROUTES')) {
            $ret = static::ROUTES;
        }

        if ($ret) {
            //validate the routes
            foreach ($ret as $route => $route_data) {
                if ($route[0] !== '/') {
                    throw new RunTimeException(sprintf(t::_('The route "%s" of Controller class %s seems wrong. All routes must begin with "/".'), $route, get_called_class()));
                }
            }
        }
        return $ret;
    }

}
