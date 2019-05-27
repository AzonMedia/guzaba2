<?php

namespace Guzaba2\Application;

use Guzaba2\Base\Base;

/**
 * Class Application
 * The children of this class are supposed to be instantiated before the Http Server is started.
 * @package Guzaba2\Application
 */
abstract class Application extends Base
{

    public const DEPLOYMENT_PRODUCTION = 'production';
    public const DEPLOYMENT_DEVELOPMENT = 'development';
    public const DEPLOYMENT_STAGING = 'staging';

    protected const CONFIG_DEFAULTS = [
        'deployment'   => self::DEPLOYMENT_DEVELOPMENT,
    ];

    protected static $CONFIG_RUNTIME = [];

    public function __construct()
    {
        parent::__construct();
    }

    public static function get_deployment() : string
    {
        return strtolower(self::$CONFIG_RUNTIME['deployment']);
    }

    public static function is_production() : bool
    {
        return strtolower(self::$CONFIG_RUNTIME['deployment']) === strtolower(self::DEPLOYMENT_PRODUCTION);
    }

    public static function is_development() : bool
    {
        return strtolower(self::$CONFIG_RUNTIME['deployment']) === strtolower(self::DEPLOYMENT_DEVELOPMENT);
    }

    public static function is_staging() : bool
    {
        return strtolower(self::$CONFIG_RUNTIME['deployment']) === strtolower(self::DEPLOYMENT_STAGING);
    }
}