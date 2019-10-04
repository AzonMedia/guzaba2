<?php

namespace Guzaba2\Database;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Resources\Resource;
use Guzaba2\Translator\Translator as t;

abstract class Connection extends Resource implements ConnectionInterface
{
    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'ConnectionFactory'
        ]
    ];

    protected const CONFIG_RUNTIME = [];

//    protected $is_created_from_factory_flag = FALSE;

    public function __construct()
    {
        $ConnectionFactory = self::ConnectionFactory();
        parent::__construct($ConnectionFactory);
    }

    public static function get_tprefix() : string
    {
        return static::CONFIG_RUNTIME['tprefix'] ?? '';
    }

    public static function get_database() : string
    {
        return static::CONFIG_RUNTIME['database'];
    }
}
