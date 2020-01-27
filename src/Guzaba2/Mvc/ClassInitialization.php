<?php
declare(strict_types=1);

namespace Guzaba2\Mvc;


use Guzaba2\Base\Base;
use Guzaba2\Kernel\Interfaces\ClassInitializationInterface;
use Guzaba2\Kernel\Kernel;

abstract class ClassInitialization extends Base implements ClassInitializationInterface
{
    public const INITIALIZATION_METHODS = [
        'initialize_controller_arguments',
    ];

    public static function run_all_initializations() : array
    {
        $ns_prefixes = array_keys(Kernel::get_registered_autoloader_paths());
        foreach (self::INITIALIZATION_METHODS as $method_name) {
            self::$method_name($ns_prefixes);
        }
        return self::INITIALIZATION_METHODS;
    }

    public static function initialize_controller_arguments(array $ns_prefixes) : void
    {
        ExecutorMiddleware::initialize_controller_arguments($ns_prefixes);
    }
}