<?php

namespace Guzaba2\Swoole;

//use Guzaba2\Base\Base as Base;

/**
 * Class HttpServer
 * Swoole implementation of HTTP server
 * @package Guzaba2\Swoole
 */
class HttpServer extends \Guzaba2\Http\HttpServer
{

    protected $swoole_http_server;

    public const SUPPPORTED_EVENTS = [];

    public function __construct(string $host, int $port, array $options = [])
    {
        parent::__construct($host, $port, $options);
        $this->swoole_http_server = new Swoole\Http\Server($this->host, $this->port);
    }

    public function start() : void
    {
        $this->swoole_http_server->start();
    }

    public function stop() : bool
    {
        return $this->swoole_http_server->stop();
    }

    public function on(string $event_name, callable $callable) : void
    {
        $this->swoole_http_server->on($event_name, $callable);
    }

    public function __call(string $method, array $args) /* mixed */
    {
        return call_user_func_array([$this->swoole_http_server, $method], $args);
    }


}