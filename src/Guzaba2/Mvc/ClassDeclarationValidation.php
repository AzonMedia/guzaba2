<?php
declare(strict_types=1);

namespace Guzaba2\Mvc;


use Guzaba2\Base\Base;
use Guzaba2\Kernel\Interfaces\ClassDeclarationValidationInterface;
use Guzaba2\Kernel\Kernel;

abstract class ClassDeclarationValidation extends Base implements ClassDeclarationValidationInterface
{
    public const VALIDATION_METHODS = [
        'validate_routes',
    ];

    public static function run_all_validations() : array
    {
        $ns_prefixes = array_keys(Kernel::get_registered_autoloader_paths());
        foreach (self::VALIDATION_METHODS as $method_name) {
            self::$method_name($ns_prefixes);
        }
        return self::VALIDATION_METHODS;
    }

    public static function validate_routes(array $ns_prefixes) : void
    {
        $active_record_classes = ActiveRecordController::get_controller_classes($ns_prefixes);
        foreach ($active_record_classes as $loaded_class) {
            $routes = $loaded_class::validate_routes();//this will trigger the checks
        }
    }
}