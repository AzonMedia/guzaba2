<?php


namespace Guzaba2\Kernel;

// TODO check does the CONFIG_RUNTIME exist in classes that have CONFIG_DEFAULTS
use Azonmedia\Reflection\ReflectionClass;
use Guzaba2\Base\Exceptions\ClassValidationException;
use Guzaba2\Kernel\Interfaces\ClassDeclarationValidationInterface;

/**
 * Class ClassDeclarationValidation
 * @package Guzaba2\Kernel
 */
class ClassDeclarationValidation implements ClassDeclarationValidationInterface
{
    public static function run_all_validations(): void
    {
        self::validate_config_constants();
    }

    public static function validate_config_constants() : void
    {
        $loaded_classes = Kernel::get_loaded_classes();
        foreach ($loaded_classes as $loaded_class) {
            $RClass = new ReflectionClass($loaded_class);
            //if (defined($loaded_class.'::CONFIG_DEFAULTS') xor !defined($loaded_class.'::CONFIG_RUNTIME') ) {
            if ( $RClass->hasOwnConstant('CONFIG_DEFAULTS') && ! $RClass->hasOwnConstant('CONFIG_RUNTIME') ) {
                throw new ClassValidationException(sprintf('The class %s defines CONFIG_DEFAULTS but does not define CONFIG_RUNTIME.', $loaded_class));
            }
            if ( ! $RClass->hasOwnConstant('CONFIG_DEFAULTS') && $RClass->hasOwnConstant('CONFIG_RUNTIME') ) {
                throw new ClassValidationException(sprintf('The class %s defines CONFIG_RUNTIME but does not define CONFIG_DEFAULTS.', $loaded_class));
            }
            if ( $RClass->hasOwnConstant('CONFIG_DEFAULTS') && $RClass->hasOwnConstant('CONFIG_RUNTIME') ) {
                $RConstant = $RClass->getReflectionConstant('CONFIG_DEFAULTS');
                if (!$RConstant->isProtected()) {
                    throw new ClassValidationException(sprintf('The class constant %s::CONFIG_DEFAULTS must be protected.', $loaded_class,));
                }
                $RConstant = $RClass->getReflectionConstant('CONFIG_RUNTIME');
                if (!$RConstant->isProtected()) {
                    throw new ClassValidationException(sprintf('The class constant %s::CONFIG_RUNTIME must be protected.', $loaded_class,));
                }
            }

        }
    }
}