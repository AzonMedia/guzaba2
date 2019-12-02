<?php

namespace Guzaba2\Application;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;

/**
 * Class Application
 * The children of this class are supposed to be instantiated before the Http Server is started.
 * This is static class
 * @package Guzaba2\Application
 */
abstract class Application extends Base
{

    protected const CONFIG_DEFAULTS = [
        'deployment'   => self::DEPLOYMENT_DEVELOPMENT,
    ];

    protected const CONFIG_RUNTIME = [];

    public const DEPLOYMENT_PRODUCTION = 'production';
    public const DEPLOYMENT_STAGING = 'staging';
    public const DEPLOYMENT_DEVELOPMENT = 'development';
    //more can be added in the extending class

    public const DEPLOYMENT_MAP = [
        self::DEPLOYMENT_PRODUCTION     => ['name' => 'production'],
        self::DEPLOYMENT_STAGING        => ['name' => 'staging'],
        self::DEPLOYMENT_DEVELOPMENT    => ['name' => 'development'],
    ];


    /**
     * Application constructor.
     * Validates the deployment type set in the config
     */
    public function __construct()
    {
        if (!isset(static::DEPLOYMENT_MAP[self::CONFIG_RUNTIME['deployment']])) {
            $allowed_str = implode(', ', array_map(fn($deployment) => '"'.$deployment['name'].'"',self::DEPLOYMENT_MAP));
            throw new RunTimeException(sprintf('The application deployment type is set to an invalid value of "%s". The valid types are %s.', self::CONFIG_RUNTIME['deployment'], $allowed_str));
        }
        parent::__construct();

    }

    public static function get_deployment() : string
    {
        return strtolower(self::CONFIG_RUNTIME['deployment']);
    }

    public static function is_production() : bool
    {
        return strtolower(self::CONFIG_RUNTIME['deployment']) === strtolower(self::DEPLOYMENT_PRODUCTION);
    }

    public static function is_development() : bool
    {
        return strtolower(self::CONFIG_RUNTIME['deployment']) === strtolower(self::DEPLOYMENT_DEVELOPMENT);
    }

    public static function is_staging() : bool
    {
        return strtolower(self::CONFIG_RUNTIME['deployment']) === strtolower(self::DEPLOYMENT_STAGING);
    }
}
