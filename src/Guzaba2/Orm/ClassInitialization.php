<?php
declare(strict_types=1);


namespace Guzaba2\Orm;


use Guzaba2\Base\Base;
use Guzaba2\Kernel\Interfaces\ClassInitializationInterface;
use Guzaba2\Kernel\Kernel;

abstract class ClassInitialization extends Base implements ClassInitializationInterface
{
    public const INITIALIZATION_METHODS = [
        'initialize_columns',
        'initialize_hooks',
        'initialize_memory',
    ];

    public static function run_all_initializations() : array
    {
        $ns_prefixes = array_keys(Kernel::get_registered_autoloader_paths());
        foreach (self::INITIALIZATION_METHODS as $method_name) {
            self::$method_name($ns_prefixes);
        }
        return self::INITIALIZATION_METHODS;
    }

    public static function initialize_columns(array $ns_prefixes) : void
    {
        $active_record_classes = ActiveRecord::get_active_record_classes($ns_prefixes);
        foreach ($active_record_classes as $active_record_class) {
            $active_record_class::initialize_columns();
        }
    }

    public static function initialize_hooks(array $ns_prefixes) : void
    {
        $active_record_classes = ActiveRecord::get_active_record_classes($ns_prefixes);
        foreach ($active_record_classes as $active_record_class) {
            $active_record_class::initialize_hooks();
        }
    }

    public static function initialize_memory(array $ns_prefixes) : void
    {
        $active_record_classes = ActiveRecord::get_active_record_classes($ns_prefixes);
        foreach ($active_record_classes as $active_record_class) {
            if ($active_record_class::is_loaded_in_memory()) {
                $active_record_class::initialize_in_memory();
            }
        }
    }
}