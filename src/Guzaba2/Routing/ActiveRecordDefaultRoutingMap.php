<?php

declare(strict_types=1);

namespace Guzaba2\Routing;

use Azonmedia\Routing\RoutingMapArray;
use Azonmedia\Utilities\ArrayUtil;
use Azonmedia\Http\Method;
use Composer\Package\Package;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Base\Interfaces\ConfigInterface;
use Guzaba2\Base\Traits\SupportsConfig;
use Guzaba2\Kernel\Exceptions\ConfigurationException;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Mvc\ActiveRecordController;
use Guzaba2\Mvc\Interfaces\ControllerInterface;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Translator\Translator as t;
use Psr\Log\LogLevel;

/**
 * Class ActiveRecordDefaultRoutingMap
 * @package Guzaba2\Routing
 * As the Controllers are also ActiveRecords this handles the controllers too (no need to use the ControllerDefaultRoutingMap)
 */
class ActiveRecordDefaultRoutingMap extends RoutingMapArray implements ConfigInterface
{

    use SupportsConfig;

    protected const CONFIG_DEFAULTS = [
        //allows a controller route to overwrite a route (part of set of rules) defined by an ActiveRecord
        'allow_controller_overwriting_activerecord_route'  => true,//if AR and a controller define the same route, the controller takes precedence and no error is thrown
        //the reasoning is that it may be needed for a controller to overwrite a single route out of the defined routes by a AR
        //no two activerecords can have the same route (if they have different controllers) and neither can two controllers
    ];

    protected const CONFIG_RUNTIME = [];

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
    public function __construct(array $ns_prefixes, array $supported_languages = [])
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

                    foreach ($new_methods as $new_method => $new_controller) {
                        foreach($routing_map as $route => $methods) {
                            //check for overwriting
                            if ($new_route === $route) {
                                foreach ($methods as $method => $controller) {
                                    if ($matching_methods = $new_method & $method) {
                                        //there are matching routes & methods

                                        //match is OK if the controllers are the same
                                        //this is the usual case in inheritance
                                        if ($new_controller === $controller) {
                                            continue;
                                        }

                                        $definer_class = $routing_meta_data[$route][$method]['class'];

                                        if (self::CONFIG_RUNTIME['allow_controller_overwriting_activerecord_route']) {
                                            if (is_a($definer_class, ActiveRecordInterface::class, true) && is_a($loaded_class, ControllerInterface::class, true)) {
                                                //there is a match but it is allowed a controller to overwrite a route defined in ActiveRecord
                                                //and the newly found route is coming from a controller
                                                //so allow to proceed (with a notice) and the route will be overwritten
                                                $raw_message = <<<'RAW'
                                                %1$s: Controller %2$s overwrites route %3$s for method %4$s defined by ActiveRecord %5$s.
                                                (%6$s::CONFIG_DEFAULTS[allow_controller_overwriting_activerecord_route] = true).
                                                RAW;
                                                $message = sprintf(
                                                    t::_($raw_message),
                                                    __CLASS__,
                                                    $loaded_class,
                                                    $route,
                                                    $method,
                                                    $definer_class,
                                                    __CLASS__
                                                );
                                                //Kernel::log($message, LogLevel::NOTICE);
                                                Kernel::printk($message.PHP_EOL);
                                            }
                                            if (is_a($definer_class, ControllerInterface::class, true) && is_a($loaded_class, ActiveRecordInterface::class, true)) {
                                                //there is a match but it is allowed a controller to overwrite a route defined in ActiveRecord
                                                //this means that overwriting must be avoided and a new route discarded
                                                //issue a notice and proceed with the next method
                                                $raw_message = <<<'RAW'
                                                %1$s: ActiveRecord %2$s route %3$s for method %4$s is discarded as it is already defined by Controller %5$s.
                                                (%6$s::CONFIG_DEFAULTS[allow_controller_overwriting_activerecord_route] = true).
                                                RAW;
                                                $message = sprintf(
                                                    t::_($raw_message),
                                                    __CLASS__,
                                                    $loaded_class,
                                                    $route,
                                                    $method,
                                                    $definer_class,
                                                    __CLASS__
                                                );
                                                //Kernel::log($message, LogLevel::NOTICE);
                                                Kernel::printk($message.PHP_EOL);
                                                continue 3;
                                            }
                                        }

                                        //the controllers are different and appropraite error needs to be thrown
                                        foreach (Method::METHODS_MAP as $method_const => $method_name) {
                                            if ($method_const & $matching_methods) {
                                                //use the meta data to get wchich class defined that route
                                                RAW;
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
                        //merge
                        $routing_map[$new_route][$new_method] = $new_controller;
                        //update the meta
                        $routing_meta_data[$new_route][$new_method]['class'] = $loaded_class;
                    }
                } // end foreach ($routing as $new_route => $new_methods)
            } // end if ($routing)
        } // end foreach ($active_record_classes as $loaded_class)

        parent::__construct($routing_map, $routing_meta_data);
    }

    public function get_processed_models(): array
    {
        return $this->processed_models;
    }
}
