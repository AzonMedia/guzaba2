<?php

declare(strict_types=1);

namespace Guzaba2\Base\Traits;

use Azonmedia\Reflection\ReflectionClass;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Translator\Translator as t;

trait SupportsConfig
{
    //private static bool $is_configured_flag = FALSE;

    public static function has_runtime_configuration(): bool
    {
        $called_class = get_called_class();
        //return defined($called_class.'::CONFIG_RUNTIME');
        $RClass = new ReflectionClass($called_class);
        return $RClass->hasOwnConstant('CONFIG_RUNTIME');
    }

    /**
     * To be invoked only by the Kernel
     * @return array
     */
    public static function get_runtime_configuration(): array
    {
        //TODO add a check to allow to be called only by the Kernel
        //return static::CONFIG_RUNTIME;
        $called_class = get_called_class();
        $runtime_config = [];
        if (defined($called_class . '::CONFIG_RUNTIME')) {
            $runtime_config = $called_class::CONFIG_RUNTIME;
        }
        return $runtime_config;
    }

    /**
     *
     * @param string $key
     * @return mixed
     */
    public static function get_config_key(string $key) /* mixed */
    {
        return static::CONFIG_RUNTIME[$key];
    }

    public static function has_config_key(string $key): bool
    {
        return array_key_exists($key, static::CONFIG_RUNTIME);
    }
}
