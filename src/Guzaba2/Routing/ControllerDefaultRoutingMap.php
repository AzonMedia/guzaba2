<?php
declare(strict_types=1);

namespace Guzaba2\Routing;

use Azonmedia\Routing\Interfaces\RoutingMapInterface;
use Azonmedia\Routing\RoutingMapArray;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Mvc\Controller;
use Guzaba2\Mvc\Interfaces\ControllerInterface;
use Guzaba2\Orm\ActiveRecordDefaultController;
use Guzaba2\Translator\Translator as t;

/**
 * Class ControllerDefaultRoutingMap
 * Walks through the provided paths and pulls the routing data from all controllers.
 * If a controller in the given path has no routing data it throws an exception.
 * @package Guzaba2\Mvc
 */
//NOT USED
class ControllerDefaultRoutingMap extends RoutingMapArray
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
    private array $processed_controllers = [];

    /**
     * ControllerDefaultRoutingMap constructor.
     * Goes thorugh the provided namespace prefixes will be walked through and all controllers will have their routing extracted.
     * If a controller has no routing information a RunTimeException will be thrown.
     * @uses \Guzaba2\Kernel\Kernel::get_loaded_classes()
     * @param array $ns_prefixes
     */
    public function __construct(array $ns_prefixes)
    {
        if (!$ns_prefixes) {
            throw new InvalidArgumentException(sprintf(t::_('No $ns_prefixes array provided to %s().'), __METHOD__ ));
        }

        $this->ns_prefixes = $ns_prefixes;
        $routing_map = [];

        $controller_classes = Controller::get_controller_classes($ns_prefixes);
        foreach ($controller_classes as $loaded_class) {
            $routing = $loaded_class::get_routes();
//            if ($routing === NULL) { //empty array is acceptable though - this may be intentional (for example to skip/disable the controller)
//                throw new RunTimeException(sprintf(t::_('The controller %s has no routing set. Please set the %s::ROUTES constant.'), $loaded_class, $loaded_class));
//            }
            $routing_map = array_merge($routing_map, $routing);
        }
        parent::__construct($routing_map);
    }

    public function get_processed_controllers() : array
    {
        return $this->processed_controllers;
    }

}