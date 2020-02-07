<?php
declare(strict_types=1);

namespace Guzaba2\Routing;

use Guzaba2\Http\Method;

/**
 * Class RoutingMapArray
 * @package Guzaba2\Routing
 * Provides shorthand methods for defining routes like:
 * @example $RoutingMap->delete('/some/path', function(){});
 */
class RoutingMapArray extends \Azonmedia\Routing\RoutingMapArray
{
    public function get(string $route, callable $controller) : void
    {
        $this->add_route($string, Method::HTTP_GET, $controller);
    }

    public function post(string $route, callable $controller) : void
    {
        $this->add_route($string, Method::HTTP_POST, $controller);
    }

    public function put(string $route, callable $controller) : void
    {
        $this->add_route($string, Method::HTTP_PUT, $controller);
    }

    public function patch(string $route, callable $controller) : void
    {
        $this->add_route($string, Method::HTTP_PATCH, $controller);
    }

    public function delete(string $route, callable $controller) : void
    {
        $this->add_route($string, Method::HTTP_DELETE, $controller);
    }

    public function head(string $route, callable $controller) : void
    {
        $this->add_route($string, Method::HTTP_HEAD, $controller);
    }

    public function options(string $route, callable $controller) : void
    {
        $this->add_route($string, Method::HTTP_OPTIONS, $controller);
    }
}