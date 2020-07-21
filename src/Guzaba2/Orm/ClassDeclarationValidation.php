<?php
declare(strict_types=1);

namespace Guzaba2\Orm;

use Azonmedia\Reflection\Reflection;
use Azonmedia\Reflection\ReflectionClass;
use Azonmedia\Reflection\ReflectionMethod;
use Azonmedia\Utilities\ArrayUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\ClassValidationException;
use Guzaba2\Kernel\Interfaces\ClassDeclarationValidationInterface;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Mvc\ActiveRecordController;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Interfaces\ValidationFailedExceptionInterface;
use Guzaba2\Translator\Translator as t;

/**
 * Class ClassDeclarationValidation
 * Contains methods used to validate all ActiveRecord classes
 * @package Guzaba2\Orm
 */
abstract class ClassDeclarationValidation extends Base implements ClassDeclarationValidationInterface
{

    public const VALIDATION_METHODS = [
        'validate_validation_hooks',
        'validate_crud_hooks',
        'validate_property_hooks',

        'validate_validation_rules',
        'validate_properties',
        'validate_structure_source',
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
     * Foreach class gets the list of properties and then checks for validation hooks (like _validate_userid() )
     * @throws ClassValidationException
     * @param array $ns_prefixes
     */
    public static function validate_validation_hooks(array $ns_prefixes) : void
    {
        $active_record_classes = ActiveRecord::get_active_record_classes($ns_prefixes);
        foreach ($active_record_classes as $active_record_class) {
            $property_hooks = $active_record_class::get_property_validation_hooks();
            foreach ($property_hooks as $property_hook) {
                self::validate_property_validation_hook($active_record_class, $property_hook);
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

        if ($RMethod->isStatic()) { // the _validate_static_ are static
            if (!$RMethod->isPublic()) {
                throw new ClassValidationException(sprintf(t::_('The method %s::%s() must be protected.'), $class, $method ));
            }

            if ($RMethod->getNumberOfParameters() !== 1) {
                throw new ClassValidationException(sprintf(t::_('The static method %s::%s() must accept a single argument (the value that is being validated).'), $class, $method ));
            }
            $expected_type = $class::get_property_type(str_replace('_validate_static_', '', $method));
            $RParam = $RMethod->getParameters()[0];
            $RType = $RParam->getType();
            if (!$RType) {
                throw new ClassValidationException(sprintf(t::_('The static method %s::%s() must accept an argument of type %s.'), $class_name, $method_name, $expected_type ) );
            }
            if ($RType->getName() !== $expected_type) {
                throw new ClassValidationException(sprintf(t::_('The static method %s::%s() must accept an argument of type %s. The current type is %s.'), $class_name, $method_name, $expected_type, $RType->getName() ) );
            }
        } else {
            if (!$RMethod->isProtected()) {
                throw new ClassValidationException(sprintf(t::_('The method %s::%s() must be protected.'), $class, $method ));
            }

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
     * Validates the CRUD hooks (_before_write(), _after_delete() etc).
     * These must be protected and return void
     */
    public static function validate_crud_hooks(array $ns_prefixes) : void
    {
        $active_record_classes = ActiveRecord::get_active_record_classes($ns_prefixes);
        foreach ($active_record_classes as $active_record_class) {
            $crud_hooks = $active_record_class::get_crud_hooks();
            foreach ($crud_hooks as $method_name) {
                self::validate_crud_hook($active_record_class, $method_name);
            }
        }
    }

    /**
     * @param string $class
     * @param string $method
     * @throws ClassValidationException
     * @throws \ReflectionException
     */
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

    /**
     * Validates the dynamic properties - the ActiveRecord classes are not allowed to define any properties be it static or dynamic.
     * @param array $ns_prefixes
     * @throws ClassValidationException
     * @throws \ReflectionException
     */
    public static function validate_properties(array $ns_prefixes) : void
    {
        $active_record_classes = ActiveRecord::get_active_record_classes($ns_prefixes);
        foreach ($active_record_classes as $active_record_class) {
            $RClass = new ReflectionClass($active_record_class);
            if (is_a($active_record_class, ActiveRecordController::class, TRUE)) {
                continue;//as the Controller is now also an ActiveRecord this check needs to be suppressed. These are expected to have properties.
            }
            //it is now allowed the ActiveRecord children to have dynamic properties
//            if (count($RClass->getOwnDynamicProperties())) {
//                throw new ClassValidationException(sprintf(t::_('The ActiveRecord class %s has defined properties. The ActiveRecord instances are not allowed to define any properties.'), $active_record_class));
//            }
            //but they still cant have static ones - using static properties in Swoole/coroutine context is a really bad idea
            //if static props are needed a separate class that does not extend ActiveRecord should be created
            if (count($RClass->getOwnStaticProperties())) {
                throw new ClassValidationException(sprintf(t::_('The ActiveRecord class %s has defined static properties. The ActiveRecord instances are not allowed to define any properties.'), $active_record_class));
            }
        }
    }

    /**
     * The ActiveRecord classes must have either CONFIG_RUNTIME['main_table'] defined or CONFIG_RUNTIME['structure'].
     * It is allowed these to come from a parent class.
     * @param array $ns_prefixes
     */
    public static function validate_structure_source(array $ns_prefixes) : void
    {
        $active_record_classes = ActiveRecord::get_active_record_classes($ns_prefixes);
        foreach ($active_record_classes as $active_record_class) {
            $RClass = new ReflectionClass($active_record_class);
            if (!$RClass->hasConstant('CONFIG_RUNTIME')) {
                throw new ClassValidationException(sprintf(t::_('The class %s does not have CONFIG_RUNTIME defined. ActiveRecord classes must have it defined and it must contain either "main_table" or a "structure" entry.'), $active_record_class));
            }
            if (!$active_record_class::has_main_table_defined() && !$active_record_class::has_structure_defined()) {
                throw new ClassValidationException(sprintf(t::_('The class %s does not define neither "main_table" nor "structure" in CONFIG_RUNTIME.'), $active_record_class));
            }
        }
    }

    /**
     * Validates the property hooks (_before_set(), _after_set(), _before_get(), _after_get() )
     * @param array $ns_prefixes
     * @throws ClassValidationException
     */
    public static function validate_property_hooks(array $ns_prefixes) : void
    {
        $active_record_classes = ActiveRecord::get_active_record_classes($ns_prefixes);
        foreach ($active_record_classes as $active_record_class) {
            $before_set_hooks = $active_record_class::get_before_set_property_hooks($active_record_class);
            foreach ($before_set_hooks as $before_set_hook) {
                self::validate_before_set_property_hook($active_record_class, $before_set_hook);
            }
            $after_set_hooks = $active_record_class::get_after_set_property_hooks($active_record_class);
            foreach ($after_set_hooks as $after_set_hook) {
                self::validate_after_set_property_hook($active_record_class, $after_set_hook);
            }
            $before_get_hooks = $active_record_class::get_before_get_property_hooks($active_record_class);
            foreach ($before_get_hooks as $before_get_hook) {
                self::validate_before_get_property_hook($active_record_class, $before_get_hook);
            }
            $after_get_hooks = $active_record_class::get_after_get_property_hooks($active_record_class);
            foreach ($after_get_hooks as $after_get_hook) {
                self::validate_after_get_property_hook($active_record_class, $after_get_hook);
            }
        }
    }

    /**
     * The _before_set_PROPERTY hook must be protected, dynamic, accept a single argument and have return type (the properties have known types and these can be used).
     * @param string $class_name
     * @param string $method_name
     */
    public static function validate_before_set_property_hook(string $class_name, string $method_name) : void
    {
        $RMethod = new ReflectionMethod($class_name, $method_name);
        if ($RMethod->isStatic()) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must be dynamic.'), $class_name, $method_name));
        }
        if (!$RMethod->isProtected()) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must be protected.'), $class_name, $method_name));
        }
        if ($RMethod->getNumberOfParameters() !== 1) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must accept a single argument.'), $class_name, $method_name));
        }

        $expected_type = $class_name::get_property_type(str_replace('_before_set_', '', $method_name));
        $RParam = $RMethod->getParameters()[0];
        $RType = $RParam->getType();
        if (!$RType) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must accept an argument of type %s.'), $class_name, $method_name, $expected_type ) );
        }
        if ($RType->getName() !== $expected_type) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must accept an argument of type %s. The current type is %s.'), $class_name, $method_name, $expected_type, $RType->getName() ) );
        }

        $RType = $RMethod->getReturnType();
        if (!$RType) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must have return type %s.'), $class_name, $method_name, $expected_type));
        }
        if ($RType->getName() !== $expected_type) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must have return type %s. The current type is %s.'), $class_name, $method_name, $expected_type, $RType->getName() ));
        }
    }

    /**
     * The _after_set_PROPERTY hook must be protected, dynamic, accept a single argument and return void
     * @param string $class_name
     * @param string $method_name
     */
    public static function validate_after_set_property_hook(string $class_name, string $method_name) : void
    {
        $RMethod = new ReflectionMethod($class_name, $method_name);
        if ($RMethod->isStatic()) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must be dynamic.'), $class_name, $method_name));
        }
        if (!$RMethod->isProtected()) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must be protected.'), $class_name, $method_name));
        }
        if ($RMethod->getNumberOfParameters() !== 1) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must accept a single argument.'), $class_name, $method_name));
        }

        $expected_type = $class_name::get_property_type(str_replace('_after_set_', '', $method_name));
        $RParam = $RMethod->getParameters()[0];
        $RType = $RParam->getType();
        if (!$RType) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must accept an argument of type %s.'), $class_name, $method_name, $expected_type ) );
        }
        if ($RType->getName() !== $expected_type) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must accept an argument of type %s. The current type is %s.'), $class_name, $method_name, $expected_type, $RType->getName() ) );
        }

        $RType = $RMethod->getReturnType();
        if (!$RType) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must have return type void.'), $class_name, $method_name));
        }
        if ($RType->getName() !== 'void') {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must have return type void. The current type is %s.'), $class_name, $method_name, $RType->getName() ));
        }
    }

    /**
     * The _before_get_PROPERTY hook must be protected, dynamic, not accept any arguments and return void
     * @param string $class_name
     * @param string $method_name
     */
    public static function validate_before_get_property_hook(string $class_name, string $method_name) : void
    {
        $RMethod = new ReflectionMethod($class_name, $method_name);
        if ($RMethod->isStatic()) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must be dynamic.'), $class_name, $method_name));
        }
        if (!$RMethod->isProtected()) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must be protected.'), $class_name, $method_name));
        }
        if ($RMethod->getNumberOfParameters() !== 0) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must not accept any arguments.'), $class_name, $method_name));
        }

        $RType = $RMethod->getReturnType();
        if (!$RType) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must have return type void.'), $class_name, $method_name));
        }
        if ($RType->getName() !== 'void') {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must have return type void. The current type is %s.'), $class_name, $method_name, $RType->getName() ));
        }
    }


    /**
     * The _after_set_PROPERTY hook must be protected, dynamic, accept a single argument and return void
     * @param string $class_name
     * @param string $method_name
     */
    public static function validate_after_get_property_hook(string $class_name, string $method_name) : void
    {
        $RMethod = new ReflectionMethod($class_name, $method_name);
        if ($RMethod->isStatic()) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must be dynamic.'), $class_name, $method_name));
        }
        if (!$RMethod->isProtected()) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must be protected.'), $class_name, $method_name));
        }
        if ($RMethod->getNumberOfParameters() !== 1) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must accept a single argument.'), $class_name, $method_name));
        }

        $expected_type = $class_name::get_property_type(str_replace('_after_get_', '', $method_name));
        $RParam = $RMethod->getParameters()[0];
        $RType = $RParam->getType();
        if (!$RType) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must accept an argument of type %s.'), $class_name, $method_name, $expected_type ) );
        }
        if ($RType->getName() !== $expected_type) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must accept an argument of type %s. The current type is %s.'), $class_name, $method_name, $expected_type, $RType->getName() ) );
        }

        $RType = $RMethod->getReturnType();
        if (!$RType) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must have return type %s.'), $class_name, $method_name, $expected_type));
        }
        if ($RType->getName() !== $expected_type) {
            throw new ClassValidationException(sprintf(t::_('The property hook %s::%s() must have return type %s. The current type is %s.'), $class_name, $method_name, $expected_type, $RType->getName() ));
        }
    }

}