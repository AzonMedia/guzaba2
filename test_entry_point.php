<?php

namespace Some\App;

use \Guzaba2\Kernel\Kernel;
use Guzaba2\Patterns\ExecutionSingleton;

//require_once('./src/Guzaba2/Kernel/Kernel.php');
require_once('./vendor/autoload.php');

/*
$bootstrap = function() : int
{

    //$http = new swoole_http_server("127.0.0.1", 9501);
    $http = new Swoole\Http\Server("127.0.0.1", 9501);

    $http->on("start", function (Swoole\Http\Server $server) {
        print get_class($server);
        echo "Swoole http server is started at http://127.0.0.1:9501\n";
    });

    $http->on("request", function (Swoole\Http\Request $request, Swoole\Http\Response $response) : void
    {
        $response->header("Content-Type", "text/plain");
        $response->end("Hello World\n");
    });

    $http->start();

    return Kernel::EXIT_SUCCESS;
};
*/




$bootstrap = function() : int
{
    class test extends ExecutionSingleton
    {

    }

    //$o = new test;
    $o = test::get_instance();

    return Kernel::EXIT_SUCCESS;
};

//require_once(self::$guzaba2_root_dir . DIRECTORY_SEPARATOR . '../vendor/autoload.php');
//Kernel::run($bootstrap);
Kernel::run_swoole();