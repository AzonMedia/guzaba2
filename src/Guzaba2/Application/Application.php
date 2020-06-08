<?php
declare(strict_types=1);

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
        'deployment'   => self::DEPLOYMENT['DEVELOPMENT'],
    ];

    protected const CONFIG_RUNTIME = [];

    public const DEPLOYMENT = [
        'PRODUCTION'    => 'production',
        'STAGING'       => 'staging',
        'DEVELOPMENT'   => 'development',
    ];
    //more can be added in the extending class

    /**
     * Application constructor.
     * Validates the deployment type set in the config
     */
    public function __construct()
    {
        if (!in_array(self::CONFIG_RUNTIME['deployment'], static::DEPLOYMENT)) {
            $allowed_str = implode(', ', array_map(fn($deployment) => '"'.$deployment['name'].'"',static::DEPLOYMENT));
            throw new RunTimeException(sprintf('The application deployment type is set to an invalid value of "%s". The valid types are %s.', static::CONFIG_RUNTIME['deployment'], $allowed_str));
        }
        parent::__construct();

    }

    public static function get_deployment() : string
    {
        return strtolower(self::CONFIG_RUNTIME['deployment']);
    }

    public static function is_production() : bool
    {
        return strtolower(self::CONFIG_RUNTIME['deployment']) === strtolower(self::DEPLOYMENT['PRODUCTION']);
    }

    public static function is_development() : bool
    {
        return strtolower(self::CONFIG_RUNTIME['deployment']) === strtolower(self::DEPLOYMENT['DEVELOPMENT']);
    }

    public static function is_staging() : bool
    {
        return strtolower(self::CONFIG_RUNTIME['deployment']) === strtolower(self::DEPLOYMENT['STAGING']);
    }
}
