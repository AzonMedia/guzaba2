<?php
declare(strict_types=1);

namespace Guzaba2\Orm\Traits;

use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Orm\Exceptions\MultipleValidationFailedException;
use Guzaba2\Orm\Exceptions\ValidationFailedException;
use ReflectionException;

trait ActiveRecordValidation
{
    /**
     * Disables the validation (@see activerecordValidation::validate()) that is invoked on write().
     * This will also bypass the validation hooks like _before_validate.
     * By defaults this is enabled.
     */
    public function disable_validation() : void
    {
        $this->validation_is_disabled_flag = true;
    }

    /**
     * Enables the validation (activerecordValidation::validate()) that is invoked on write()
     * By defaults this is enabled.
     * @return void
     */
    public function enable_validation() : void
    {
        $this->validation_is_disabled_flag = false;
    }

    /**
     * Returns is the validation enabled for this instance.
     * @return bool
     */
    public function validation_is_disabled() : bool
    {
        return $this->validation_is_disabled_flag;
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     * @throws MultipleValidationFailedException
     * @throws ReflectionException
     */
    public function validate() : void
    {
        $properties = static::get_property_names();
        $validation_exceptions = [];
        foreach ($properties as $property) {
            //basic validation
            $validation_rules = self::get_validation_rules();
            foreach ($validation_rules as $property_name => $validation_rule) {
                if (!empty($validation_rule['required']) && !$this->{$property_name}) {
                    $validation_exceptions[] = new ValidationFailedException($this, $property_name, sprintf(t::_('The property %s on instance of class % must have value.'), $property_name, get_class($this) ));
                }
                if (!empty($validation_rule['min_length']) && strlen($this->{$property_name}) < $validation_rule['min_length'] ) { // TODO - use a wrapper and mb_string or use overloading of strign functions in php.ini
                    $validation_exceptions[] = new ValidationFailedException($this, $property_name, sprintf(t::_('The property %s on instance of class %s must be at least %s characters.'), $property_name, get_class($this), $validation_rule['min_length'] ));
                }
                if (!empty($validation_rule['max_length']) && strlen($this->{$property_name}) > $validation_rule['max_length'] ) { // TODO - use a wrapper and mb_string or use overloading of strign functions in php.ini
                    $validation_exceptions[] = new ValidationFailedException($this, $property_name, sprintf(t::_('The property %s on instance of class %s must be at maximum %s characters.'), $property_name, get_class($this), $validation_rule['max_length'] ));
                }

            }
            //method validation
            $method_name = '_validate_'.$property;
            $static_method_name = '_validate_static_'.$property;
            if (method_exists($this, $method_name)) {
                $ValidationException = $this->{$method_name}();
                if ($ValidationException) {
                    $validation_exceptions[] = $ValidationException;
                }
            } elseif (method_exists($this, $static_method_name)) {
                //$ValidationException = $this->{$static_method_name}($this->{$property});
                $ValidationException = static::{$static_method_name}($this->{$property});
                if ($ValidationException) {
                    $validation_exceptions[] = $ValidationException;
                }
            }
        }
        if ($validation_exceptions) {
            throw new MultipleValidationFailedException($validation_exceptions);
        }
    }

    //in future may allow static validation
//    public static function __callStatic($name, $arguments)
//    {
//
//    }
}
