<?php


namespace Guzaba2\Orm;


use Azonmedia\Reflection\ReflectionMethod;
use Azonmedia\Utilities\ArrayUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\ClassValidationException;
use Guzaba2\Kernel\Interfaces\ClassDeclarationValidationInterface;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Interfaces\ValidationFailedExceptionInterface;
use Guzaba2\Translator\Translator as t;

/**
 * Class ClassDeclarationValidation
 * Contains methods used to validate all ActiveRecord classes
 * @package Guzaba2\Orm
 */
class ClassDeclarationValidation extends Base implements ClassDeclarationValidationInterface
{

    public const CRUD_HOOKS = [
        '_before_read', '_after_read',
        '_before_save', '_after_save',
        '_before_delete', '_after_delete',
    ];

    public static function run_all_validations() : void
    {
        self::validate_validation_hooks(array_keys(Kernel::get_registered_autoloader_paths()));
        self::validate_crud_hooks(array_keys(Kernel::get_registered_autoloader_paths()));
        self::validate_validation_rules(array_keys(Kernel::get_registered_autoloader_paths()));
    }

    /**
     * Foreach class gets the list of properties and then checks for validation hooks (like _validate_userid() )
     * @throws ClassValidationException
     * @param array $ns_prefixes
     */
    public static function validate_validation_hooks(array $ns_prefixes) : void
    {
        $active_record_classes = ActiveRecord::get_active_record_classes($ns_prefixes);
        foreach ($active_record_classes as $active_record_class) {
            $properties = $active_record_class::get_property_names();
            foreach ($properties as $property) {
                $method_name = '_validate_'.$property;
                if (method_exists($active_record_class, $method_name)) {
                    self::validate_property_validation_hook($active_record_class, $method_name);
                }
                $static_method_name = '_validate_static_'.$property;
                if (method_exists($active_record_class, $static_method_name)) {
                    self::validate_property_validation_hook($active_record_class, $static_method_name);
                }
            }
        }
    }

    /**
     * Validates a single validation hook method (like _validate_user_id() ).
     * It must be protected and must return nullable ValidationFailedExceptionInterface
     * @param string $class
     * @param string $method
     * @throws ClassValidationException
     * @throws \ReflectionException
     */
    public static function validate_property_validation_hook(string $class, string $method) : void
    {
        $RMethod = new ReflectionMethod($class, $method);
        if (!$RMethod->isProtected()) {
            throw new ClassValidationException(sprintf(t::_('The method %s::%s() must be protected.'), $class, $method ));
        }
        if ($RMethod->isStatic()) { // the _validate_static_ are static
            if ($RMethod->getNumberOfParameters() !== 1) {
                throw new ClassValidationException(sprintf(t::_('The static method %s::%s() must accept a single argument (the value that is being validated).'), $class, $method ));
            }
        } else {
            if ($RMethod->getNumberOfParameters()) {
                throw new ClassValidationException(sprintf(t::_('The method %s::%s() must not accept any arguments.'), $class, $method ));
            }
        }

        $RType = $RMethod->getReturnType();
        if (!$RType) {
            throw new ClassValidationException(sprintf(t::_('The method %s::%s() must have return type defined and it must be a nullable %s.'), $class, $method, ValidationFailedExceptionInterface::class ));
        }
        if ($RType->getName() !== ValidationFailedExceptionInterface::class) {
            throw new ClassValidationException(sprintf(t::_('The method %s::%s() must return a nullable %s.'), $class, $method, ValidationFailedExceptionInterface::class ));
        }
        if (!$RType->allowsNull()) {
            throw new ClassValidationException(sprintf(t::_('The method %s::%s() must allow for NULL to be returned.'), ValidationFailedExceptionInterface::class));
        }
    }

    /**
     * Validates the validation rules found in class_name::CONFIG_RUNTIME['validation']
     * @param array $ns_prefixes
     * @throws ClassValidationException
     */
    public static function validate_validation_rules(array $ns_prefixes) : void
    {
        $active_record_classes = ActiveRecord::get_active_record_classes($ns_prefixes);
        foreach ($active_record_classes as $active_record_class) {
            $validation_rules = $active_record_class::get_validation_rules();
            foreach ($validation_rules as $property_name => $property_validation_rules) {
                if (!$active_record_class::has_property($property_name)) {
                    throw new ClassValidationException(sprintf(t::_('The class %s has property name %s in the CONFIG_RUNTIME[\'validation\'] section that does not exist.'), $active_record_class, $property_name));
                }
                ArrayUtil::validate_array($property_validation_rules, ActiveRecordInterface::PROPERTY_VALIDATION_SUPPORTED_RULES, $errors);
                if ($errors) {
                    throw new ClassValidationException(sprintf(t::_('The class %s has invalid validation rules for property %s. The errors are: %s'), $active_record_class, $property_name, implode(' ', $errors) ));
                }
            }
        }
    }

    /**
     * Validates the CRUD hooks (_before_save(), _after_delete() etc).
     * These must be protected and return void
     */
    public static function validate_crud_hooks(array $ns_prefixes) : void
    {
        $active_record_classes = ActiveRecord::get_active_record_classes($ns_prefixes);
        foreach ($active_record_classes as $active_record_class) {
            foreach (self::CRUD_HOOKS as $method_name) {
                if (method_exists($active_record_class, $method_name)) {
                    self::validate_crud_hook($active_record_class, $method_name);
                }
            }
        }
    }

    public static function validate_crud_hook(string $class, string $method) : void
    {
        $RMethod = new ReflectionMethod($class, $method);
        if (!$RMethod->isProtected()) {
            throw new ClassValidationException(sprintf(t::_('The method %s::%s() must be protected.'), $class, $method ));
        }
        if ($RMethod->getNumberOfParameters()) {
            throw new ClassValidationException(sprintf(t::_('The method %s::%s() must not accept any arguments.'), $class, $method ));
        }
        if($RMethod->isStatic()) {
            throw new ClassValidationException(sprintf(t::_('The method %s::%s() must be dynamic.'), $class, $method ));
        }
        $RType = $RMethod->getReturnType();
        if (!$RType) {
            throw new ClassValidationException(sprintf(t::_('The method %s::%s() must have return type and it must be set to void.'), $class, $method ));
        }
        if ($RType->getName() !== 'void') {
            throw new ClassValidationException(sprintf(t::_('The method %s::%s() must have return type void.'), $class, $method ));
        }

    }

}