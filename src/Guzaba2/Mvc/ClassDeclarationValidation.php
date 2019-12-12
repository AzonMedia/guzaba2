<?php
declare(strict_types=1);

namespace Guzaba2\Mvc;


use Azonmedia\Reflection\ReflectionClass;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\ClassValidationException;
use Guzaba2\Kernel\Interfaces\ClassDeclarationValidationInterface;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Translator\Translator as t;

abstract class ClassDeclarationValidation extends Base implements ClassDeclarationValidationInterface
{
    public const VALIDATION_METHODS = [
        'validate_routes',
        'validate_activerecord_controller_actions',
    ];

    public const SUPPORTED_ARGUMENT_TYPES = [
        'int',
        'float',
        'string',
        'bool',
    ];

    public static function run_all_validations() : array
    {
        $ns_prefixes = array_keys(Kernel::get_registered_autoloader_paths());
        foreach (self::VALIDATION_METHODS as $method_name) {
            self::$method_name($ns_prefixes);
        }
        return self::VALIDATION_METHODS;
    }

    /**
     * Validates the routes
     * @param array $ns_prefixes
     */
    public static function validate_routes(array $ns_prefixes) : void
    {
        $active_record_classes = Controller::get_controller_classes($ns_prefixes);
        foreach ($active_record_classes as $loaded_class) {
            $routes = $loaded_class::get_routes();
            if ($routes === NULL) {
                throw new ClassValidationException(sprintf(t::_('The controller %s has no CONFIG_RUNTIME[\'routes\'] defined. Every controller must have this defined.'), $loaded_class ));
            }
            if (!count($routes)) {
                throw new ClassValidationException(sprintf(t::_('The controller %s has no routes defined in CONFIG_RUNTIME[\'routes\']. There must be at least one route defined.'), $loaded_class ));
            }
            foreach ($routes as $route => $route_data) {
                if ($route[0] !== '/') {
                    throw new ClassValidationException(sprintf(t::_('The route "%s" of Controller %s seems wrong. All routes must begin with "/".'), $route, $loaded_class ));
                }
            }
        }
    }

    /**
     * Validates the types of the parameters of the controller actions (the putli
     * @param array $ns_prefixes
     * @throws ClassValidationException
     * @throws \ReflectionException
     */
    public static function validate_activerecord_controller_actions(array $ns_prefixes) : void
    {
        $active_record_classes = Controller::get_controller_classes($ns_prefixes);
        foreach ($active_record_classes as $loaded_class) {
            foreach ((new ReflectionClass($loaded_class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $RMethod) {
                if ($RMethod->isConstructor()) {
                    continue;
                }
                if ($RMethod->getDeclaringClass() !== $loaded_class) {
                    continue;//do not validate parent methods
                }
                foreach ($RMethod->getParameters() as $RParameter) {
                    if (!($RType = $RParameter->getType()) ) {
                        throw new ClassValidationException(sprintf(t::_('The controller action %s::%s() has argument %s which is lacking type. All arguments to the controller actions must have their types set.'), $loaded_class, $RMethod->getName(), $RParameter->getName() ));
                    } elseif (!in_array($RType->getName(), self::SUPPORTED_ARGUMENT_TYPES)) {
                        throw new ClassValidationException(sprintf(t::_('The controller action %s::%s() has argument %s which is of unsupported type %s. The supported types are %s.'), $loaded_class, $RMethod->getName(), $RParameter->getName(), $RType->getName(), implode(', ',self::SUPPORTED_ARGUMENT_TYPES) ));
                    }
                }
            }
        }
    }
}