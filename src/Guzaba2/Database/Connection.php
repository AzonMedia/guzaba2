<?php

namespace Guzaba2\Database;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Resources\GenericResource;
use Guzaba2\Resources\Resource;
use Guzaba2\Translator\Translator as t;

abstract class Connection extends GenericResource implements ConnectionInterface
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
        $ConnectionFactory = static::get_service('ConnectionFactory');
        parent::__construct($ConnectionFactory);
    }

    public function __destruct()
    {
        //$this->close();//avoid this - the connections should be close()d immediately
        //or have a separate flag $is_connected_flag
    }

    public abstract function close() : void ;

    public static function get_tprefix() : string
    {
        return static::CONFIG_RUNTIME['tprefix'] ?? '';
    }

}
