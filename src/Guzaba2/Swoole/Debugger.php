<?php


namespace Guzaba2\Swoole;

use Guzaba2\Base\Base;

class Debugger extends Base
{
    protected const CONFIG_DEFAULTS = [
        'enabled'   => TRUE,
        'base_port' => 10000,//on this port the first worker will listen
    ];

    protected const CONFIG_RUNTIME = [];

    public function __construct()
    {
        parent::__construct();

        //ob_implicit_flush();
    }

    public static function is_enabled() : bool
    {
        return self::CONFIG_RUNTIME['enabled'];
    }

    public static function get_worker_port(int $worker_id) : int
    {
        return self::CONFIG_RUNTIME['base_port'] + $worker_id;
    }
}
