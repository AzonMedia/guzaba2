<?php
declare(strict_types=1);

namespace Guzaba2\Mvc;

use Guzaba2\Base\Base;
use Guzaba2\Mvc\Interfaces\ControllerInterface;
use Guzaba2\Mvc\Traits\ResponseFactories;
use Psr\Http\Message\RequestInterface;

/**
 * Class Controller
 * @package Guzaba2\Mvc
 * A basic controller class
 */
abstract class Controller extends Base implements ControllerInterface
{
    use ResponseFactories;

    private RequestInterface $Request;

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
}