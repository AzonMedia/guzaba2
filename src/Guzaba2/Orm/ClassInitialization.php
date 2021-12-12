<?php

declare(strict_types=1);

namespace Guzaba2\Orm;

use Guzaba2\Base\Base;
use Guzaba2\Event\Interfaces\EventsInterface;
use Guzaba2\Kernel\Interfaces\ClassInitializationInterface;
use Guzaba2\Kernel\Kernel;

abstract class ClassInitialization extends Base implements ClassInitializationInterface
{
    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'Events',
        ],
    ];

    protected const CONFIG_RUNTIME = [];


    public const INITIALIZATION_METHODS = [
        'initialize_structure',
        'initialize_hooks',
        'initialize_memory',
        'register_active_record_temporal_hooks',
    ];

    public static function run_all_initializations(): array
    {
        $ns_prefixes = array_keys(Kernel::get_registered_autoloader_paths());
        foreach (self::INITIALIZATION_METHODS as $method_name) {
            self::$method_name($ns_prefixes);
        }
        return self::INITIALIZATION_METHODS;
    }

    public static function initialize_structure(array $ns_prefixes): void
    {
        $active_record_classes = ActiveRecord::get_active_record_classes($ns_prefixes);
        foreach ($active_record_classes as $active_record_class) {
            $active_record_class::initialize_structure();
        }
    }

    public static function initialize_hooks(array $ns_prefixes): void
    {
        $active_record_classes = ActiveRecord::get_active_record_classes($ns_prefixes);
        foreach ($active_record_classes as $active_record_class) {
            $active_record_class::initialize_hooks();
        }
    }

    /**
     * Prefetch data
     * Disabled currently
     * @param array $ns_prefixes
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     */
    public static function initialize_memory(array $ns_prefixes): void
    {
        $active_record_classes = ActiveRecord::get_active_record_classes($ns_prefixes);
        foreach ($active_record_classes as $active_record_class) {
            if ($active_record_class::is_loaded_in_memory()) {
                $active_record_class::initialize_in_memory();
            }
        }
    }

    public static function register_active_record_temporal_hooks(array $ns_prefixes): void
    {
        $active_record_history_classes = ActiveRecord::get_active_record_temporal_classes($ns_prefixes);
        /** @var EventsInterface $Events */
        $Events = self::get_service('Events');
        foreach ($active_record_history_classes as $active_record_history_class) {
            //get the parent class and add the hook on the parent class
            $active_record_class = get_parent_class($active_record_history_class);
            $Events->add_class_callback($active_record_class, '_after_write', [ActiveRecord::class, 'after_write_handler']);
            $Events->add_class_callback($active_record_class, '_after_delete', [ActiveRecord::class, 'after_delete_handler']);
        }
    }
}
