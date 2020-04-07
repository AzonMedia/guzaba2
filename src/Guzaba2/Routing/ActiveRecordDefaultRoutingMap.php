<?php
declare(strict_types=1);

namespace Guzaba2\Routing;

use Azonmedia\Routing\RoutingMapArray;
use Azonmedia\Utilities\ArrayUtil;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
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
     * @throws InvalidArgumentException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @uses \Guzaba2\Kernel\Kernel::get_loaded_classes()
     */
    public function __construct(array $ns_prefixes, array $supported_languages = [] /* , string $route_prefix = '' */ )
    {
        if (!$ns_prefixes) {
            throw new InvalidArgumentException(sprintf(t::_('No $ns_prefixes array provided to %s().'), __METHOD__ ));
        }
        $this->ns_prefixes = $ns_prefixes;
        //$this->route_prefix = $route_prefix;

        $routing_map = [];
        $routing_meta_data = [];
        $active_record_classes = ActiveRecord::get_active_record_classes($this->ns_prefixes);

        foreach ($active_record_classes as $loaded_class) {

            $routing = $loaded_class::get_routes();


            if ($routing) {

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

                if ($supported_languages) {
                    //the basic route without language prefix will be always added and will point to the default target language
                    //here additional routes for each of the supported languages is added
                    //no need to generate individual URL paths... instead use a {language} var in the path
//                    foreach ($supported_languages as $supported_language) {
//                        foreach ($routing as $path => $value) {
//                            $routing['/'.$supported_language.$path] = $value;
//                        }
//                    }
                    //even if a single language is provided still add additional path as this may be required for other purpose (future proofing)
                    foreach ($routing as $path => $value) {
                        $routing['/{language}'.$path] = $value;
                    }
                }

                $routing_map = array_merge($routing_map, $routing);
                $routing_meta_data[current(array_keys($routing))] = ['orm_class' => $loaded_class];

            }


        }

        parent::__construct($routing_map, $routing_meta_data);
    }

    public function get_processed_models() : array
    {
        return $this->processed_models;
    }
}