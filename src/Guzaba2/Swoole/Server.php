<?php

namespace Guzaba2\Swoole;

//use Guzaba2\Base\Base as Base;

/**
 * Class Server
 * Swoole implementation of HTTP server
 * @package Guzaba2\Swoole
 */
class Server extends \Guzaba2\Http\Server
{

    protected $swoole_http_server;

    public const SUPPPORTED_EVENTS = [];

    protected const SWOOLE_HOST = '0.0.0.0';

    protected const SWOOLE_PORT = 8081;

    public function __construct(string $host = self::SWOOLE_HOST, int $port = self::SWOOLE_PORT, array $options = [])
    {
        if (!$host) {
            $host = self::SWOOLE_HOST;
        }
        if (!$port) {
            $port = self::SWOOLE_PORT;
        }

        parent::__construct($host, $port, $options);
        $this->swoole_http_server = new \Swoole\Http\Server($this->host, $this->port);
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