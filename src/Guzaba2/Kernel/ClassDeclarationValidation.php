<?php

declare(strict_types=1);

namespace Guzaba2\Kernel;

use Azonmedia\Reflection\ReflectionClass;
use Guzaba2\Base\Exceptions\ClassValidationException;
use Guzaba2\Kernel\Interfaces\ClassDeclarationValidationInterface;

/**
 * Class ClassDeclarationValidation
 * @package Guzaba2\Kernel
 */
class ClassDeclarationValidation implements ClassDeclarationValidationInterface
{

    public const VALIDATION_METHODS = [
        'validate_config_constants',
        //'check_source',
    ];

    public static function run_all_validations(): array
    {
        foreach (self::VALIDATION_METHODS as $method) {
            self::$method();
        }
        return self::VALIDATION_METHODS;
    }

    /**
     * Validates CONFIG_DEFAULTS and CONFIG_RUNTIME. If a class has one of these has to have the other too.
     * @throws ClassValidationException
     * @throws \ReflectionException
     */
    public static function validate_config_constants(): void
    {
        $loaded_classes = Kernel::get_loaded_classes();

        foreach ($loaded_classes as $loaded_class) {
            $RClass = new ReflectionClass($loaded_class);

            //if (defined($loaded_class.'::CONFIG_DEFAULTS') xor !defined($loaded_class.'::CONFIG_RUNTIME') ) {
            if ($RClass->hasOwnConstant('CONFIG_DEFAULTS') && !$RClass->hasOwnConstant('CONFIG_RUNTIME')) {
                throw new ClassValidationException(sprintf('The class %s defines CONFIG_DEFAULTS but does not define CONFIG_RUNTIME.', $loaded_class));
            }
            if (!$RClass->hasOwnConstant('CONFIG_DEFAULTS') && $RClass->hasOwnConstant('CONFIG_RUNTIME')) {
                throw new ClassValidationException(sprintf('The class %s defines CONFIG_RUNTIME but does not define CONFIG_DEFAULTS.', $loaded_class));
            }
            if ($RClass->hasOwnConstant('CONFIG_DEFAULTS') && $RClass->hasOwnConstant('CONFIG_RUNTIME')) {
                $RConstant = $RClass->getReflectionConstant('CONFIG_DEFAULTS');
                if (!$RConstant->isProtected()) {
                    throw new ClassValidationException(sprintf('The class constant %s::CONFIG_DEFAULTS must be protected.', $loaded_class));
                }
                $RConstant = $RClass->getReflectionConstant('CONFIG_RUNTIME');
                if (!$RConstant->isProtected()) {
                    throw new ClassValidationException(sprintf('The class constant %s::CONFIG_RUNTIME must be protected.', $loaded_class));
                }
            }
        }
    }

    /**
     * Checks the source code for non strict comparisons and missing strict_types declaration
     * @throws ClassValidationException
     */
    public static function check_source(): void
    {
        $loaded_paths = Kernel::get_loaded_paths();
        // Check for == and != operator
        foreach ($loaded_paths as $file_path) {
            $fp = fopen($file_path, "r");
            $line_number = 1;
            while ($line = fgets($fp)) {
                // TODO figure out what to do with multirow comments
                //TODO - skip == in strings too - tokenizer is needed
                if (strpos(trim($line), '//') !== 0 && strpos($line, '==') !== false) {
                    if (preg_match('/[^=!]==[^=]/', $line)) {
                        throw new ClassValidationException(sprintf('Not strict equal comparison operator (==) found in %s on line %d.', $file_path, $line_number));
                    }
                }

                if (strpos(trim($line), '//') !== 0 && strpos($line, '!=') !== false) {
                    if (preg_match('/!=[^=]/', $line)) {
                        throw new ClassValidationException(sprintf('Not strict not equal comparison operator (!=) found in %s on line %d', $file_path, $line_number));
                    }
                }
                $line_number++;
            }
            fclose($fp);
        }

        // Check for strict types
        foreach ($loaded_paths as $file_path) {
            $fp = fopen($file_path, "r");
            while ($line = fgets($fp)) {
                $line_wo_spaces = str_replace(' ', '', $line);
                if (strpos($line_wo_spaces, 'declare(strict_types=1);') !== false) {
                    break;
                }

                if (strpos($line_wo_spaces, 'namespace') === 0) {
                    throw new ClassValidationException(sprintf('Missing strict types declaration in %s', $file_path));
                }
            }
            fclose($fp);
        }
    }
}
