<?php

declare(strict_types=1);

namespace Guzaba2\Routing;

use Azonmedia\Routing\RoutingMapArray;
use Azonmedia\Utilities\ArrayUtil;
use Composer\Package\Package;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Azonmedia\Http\Method;
use Guzaba2\Kernel\Exceptions\ConfigurationException;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Mvc\ActiveRecordController;
use Guzaba2\Mvc\Interfaces\ControllerInterface;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Translator\Translator as t;

/**
 * Class ActiveRecordDefaultRoutingMap
 * @package Guzaba2\Routing
 * As the Controllers are also ActiveRecords this handles the controllers too (no need to use the ControllerDefaultRoutingMap)
 */
class ActiveRecordDefaultRoutingMap extends RoutingMapArray
{
    /**
     * Indexed array of namespace prefixes
     * @var array
     */
    private array $ns_prefixes = [];

    /**
     * Associative array of controller_path => controller_class
     * @var array
     */
    private array $processed_models = [];

    //private string $route_prefix = '';

    /**
     * ActiveRecordDefaultRoutingMap constructor.
     * Goes thorugh the provided namespace prefixes will be walked through and all models will have their routing extracted.
     * If a models has no routing information it will be skipped (not all models are expected to be managed individually though the API).
     * @param array $ns_prefixes
     * @param array $supported_languages
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     * @uses \Guzaba2\Kernel\Kernel::get_loaded_classes()
     */
    public function __construct(array $ns_prefixes, array $supported_languages = [] /* , string $route_prefix = '' */)
    {
        if (!$ns_prefixes) {
            throw new InvalidArgumentException(sprintf(t::_('No $ns_prefixes array provided to %1$s().'), __METHOD__));
        }
        $this->ns_prefixes = $ns_prefixes;
        //$this->route_prefix = $route_prefix;

        $routing_map = [];
        $routing_meta_data = [];
        $active_record_classes = ActiveRecord::get_active_record_classes($this->ns_prefixes);

        foreach ($active_record_classes as $loaded_class) {
            $routing = $loaded_class::get_routes();

            if ($routing) {
                //some validation
                foreach ($routing as $path => $route) {
                    foreach ($route as $method => $controller) {
                        if (is_array($controller)) {
                            if (!class_exists($controller[0])) {
                                throw new ConfigurationException(sprintf(t::_('The class %1$s contains an invalid controller for route %2$s:%3$s - the class %4$s does not exist.'), $loaded_class, Method::METHODS_MAP[$method], $path, $controller[0]));
                            }
                            if (!method_exists($controller[0], $controller[1])) {
                                throw new ConfigurationException(sprintf(t::_('The class %1$s contains an invalid controller for route %2$s:%3$s - the class %4$s does not have a method %5$s.'), $loaded_class, Method::METHODS_MAP[$method], $path, $controller[0], $controller[1]));
                            }
                        }
                    }
                }

//                //if ($api_route_prefix) {
//                if (is_a($loaded_class, ControllerInterface::class, TRUE)) {
//                    //skip
//                } else {
//                    $routing = ArrayUtil::prefix_keys($routing, $this->api_route_prefix);
//                    $routing_map = array_merge($routing_map, $routing);
//                    $routing_meta_data[current(array_keys($routing))] = ['orm_class' => $loaded_class];
//                }


//                if ($route_prefix) {
//                    $routing = ArrayUtil::prefix_keys($routing, $this->route_prefix);
//                }
                //even if a single language is provided still add additional path as this may be required for other purpose (future proofing)
                //no - lets not have the language routes if there is only a single language
                //if ($supported_languages) {
                if (count($supported_languages) > 1) {
                    foreach ($routing as $path => $value) {
                        $routing['/{language}' . $path] = $value;
                    }
                }

                //this will overwrite
                //$routing_map = array_merge($routing_map, $routing);
                //instead they need to be merged if different methods are used
                //if there are the same methods then an error is to be thrown
                foreach ($routing as $new_route => $new_methods) {
                    //check for overwriting
                    foreach($routing_map as $route => $methods) {
                        if ($new_route === $route) {
                            foreach ($new_methods as $new_method => $new_controller) {
                                foreach ($methods as $method => $controller) {
                                    if ($matching_methods = $new_method & $method) {
                                        //there are matching routes & methods

                                        //match is OK if the controllers are the same
                                        //this is the usual case in inheritance
                                        if ($new_controller === $controller) {
                                            continue;
                                        }

                                        //the controllers are different and appropraite error needs to be thrown
                                        foreach (Method::METHODS_MAP as $method_const => $method_name) {
                                            if ($method_const & $matching_methods) {
                                                //use the meta data to get wchich class defined that route
                                                $definer_class = $routing_meta_data[$route][$method];
                                                $message = sprintf(
                                                    t::_('The class %1$s has a route %2$s containing method %3$s that is already defined in the routing map by class %4$s.'),
                                                    $loaded_class,
                                                    $new_route,
                                                    $method_name,
                                                    $definer_class
                                                    );
                                                throw new ConfigurationException($message);
                                            }
                                        }
                                        throw new LogicException(sprintf(t::_('A matching route was found but no matching method.')));
                                    }
                                }
                            }
                        }
                    }
                    //merge
                    foreach ($new_methods as $new_method => $new_controller) {
                        $routing_map[$new_route][$new_method] = $new_controller;
                    }
                }

//                //print_r($routing);
//                if (is_a($loaded_class, ActiveRecordInterface::class, true)) {
//                    $route = array_keys($routing)[0];
//
//                    $routing_meta_data[$route] = ['orm_class' => $loaded_class];
//                    if ($route[-1] === '/') {
//                        //add the same route without trailing /
//                        $route_wo_trailing_slash = substr($route, 0, strlen($route) - 1);
//                        $routing_meta_data[ $route_wo_trailing_slash ] = ['orm_class' => $loaded_class];//with trailing slash it supported too
//                    } else {
//                        //add the same route with trailing /
//                        $routing_meta_data[ $route . '/' ] = ['orm_class' => $loaded_class];//with trailing slash it supported too
//                    }
//                }

                //update the meta data
                foreach ($routing as $new_route => $new_methods) {
                    foreach ($new_methods as $new_method => $new_controller) {
                        $routing_meta_data[$new_route][$new_method] = $loaded_class;
                    }
                }

//                $route = current(array_keys($routing));
//
//                $routing_meta_data[$route] = ['orm_class' => $loaded_class];
//                if ($route[-1] === '/') {
//                    //add the same route without trailing /
//                    $route_wo_trailing_slash = substr($route, 0, strlen($route) - 1);
//                    $routing_meta_data[ $route_wo_trailing_slash ] = ['orm_class' => $loaded_class];//with trailing slash it supported too
//                } else {
//                    //add the same route with trailing /
//                    $routing_meta_data[ $route . '/' ] = ['orm_class' => $loaded_class];//with trailing slash it supported too
//                }
            } // end if $routing
        } // end foreach

        parent::__construct($routing_map, $routing_meta_data);
    }

    public function get_processed_models(): array
    {
        return $this->processed_models;
    }
}
