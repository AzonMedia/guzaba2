<?php


namespace Guzaba2\Base\Traits;


use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Translator\Translator as t;

trait SupportsConfig
{

    /**
     * Runtime configuration for the class.
     * Needs to be protected as if the child has has no configuration at all it can still access its parent config.
     * If a class needs to have its own config it needs to declare $CONFIG_RUNTIME AND CONFIG_DEFAULTS
     * @var array
     */
    protected static $CONFIG_RUNTIME = [];

    private static $is_configured_flag = FALSE;

    /**
     * To be invoked by the kernel on class initialization
     * Can be invoked only once
     * @param array $config_runtime
     */
    public static function initialize_runtime_configuration(array $config_runtime) : void
    {
        if (static::$is_configured_flag) {
            $called_class = get_called_class();
            throw new RuntimeException(sprintf(t::_('The class %s is already configured. Can not invoke twice the %s::%s method.'), $called_class, $called_class, __FUNCTION__ ));
        }
        static::$CONFIG_RUNTIME = $runtime_config;
        static::$is_configured_flag = TRUE;
    }

    /**
     * To be invoked only by the Kernel
     * @return array
     */
    public static function get_runtime_configuration() : array
    {
        //TODO add a check to allow to be called only by the Kernel
        return static::$CONFIG_RUNTIME;
    }

    /**
     *
     * @param string $key
     * @return mixed
     */
    public static function get_config_key(string $key) /* mixed */
    {
        return static::$CONFIG_RUNTIME[$key];
    }

    public static function has_config_key(string $key) : bool
    {
        return array_key_exists($key, static::$CONFIG_RUNTIME);
    }


    public static function update_runtime_configuration(array $options) : void
    {
        foreach ($options as $option_name=>$option_value) {
            if (array_key_exists($option_name, static::$CONFIG_RUNTIME)) {
                static::$CONFIG_RUNTIME[$option_name] = $option_value;
            }
        }
    }
}