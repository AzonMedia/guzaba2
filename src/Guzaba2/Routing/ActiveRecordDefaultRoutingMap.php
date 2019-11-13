<?php

namespace Guzaba2\Routing;

use Azonmedia\Routing\RoutingMapArray;
use Azonmedia\Utilities\ArrayUtil;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Mvc\ActiveRecordController;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Translator\Translator as t;

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

    private string $api_route_prefix = '';
    /**
     * ActiveRecordDefaultRoutingMap constructor.
     * Goes thorugh the provided namespace prefixes will be walked through and all models will have their routing extracted.
     * If a models has no routing information it will be skipped (not all models are expected to be managed individually though the API).
     * @uses \Guzaba2\Kernel\Kernel::get_loaded_classes()
     * @param array $ns_prefixes
     */
    public function __construct(array $ns_prefixes, string $api_route_prefix = '')
    {
        if (!$ns_prefixes) {
            throw new InvalidArgumentException(sprintf(t::_('No $ns_prefixes array provided to %s().'), __METHOD__ ));
        }
        $this->ns_prefixes = $ns_prefixes;
        $this->api_route_prefix = $api_route_prefix;

        $routing_map = [];
        $routing_meta_data = [];
        $active_record_classes = ActiveRecord::get_active_record_classes($this->ns_prefixes);
        foreach ($active_record_classes as $loaded_class) {
            $routing = $loaded_class::get_routes();
            //the models may not define route as not every controller is expected to be directly manageable through the API
//                    if ($routing === NULL) {
//                        throw new RunTimeException(sprintf(t::_('The model %s has no routing set.'), $loaded_class));
//                    }
            if ($routing) {
                if ($api_route_prefix) {
                    $routing = ArrayUtil::prefix_keys($routing, $this->api_route_prefix);
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