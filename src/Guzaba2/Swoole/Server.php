<?php
declare(strict_types=1);

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

    protected const DEFAULT_CONFIG = [
        'swoole_host'   => '0.0.0.0',
        'swoole_port'   => 8081,
    ];

    protected const SWOOLE_HOST = '0.0.0.0';

    protected const SWOOLE_PORT = 8081;

    public function __construct(string $host = self::DEFAULT_CONFIG['swoole_host'], int $port = self::DEFAULT_CONFIG['swoole_port'], array $options = [])
    {
        if (!$host) {
            $host = self::DEFAULT_CONFIG['swoole_host'];
        }
        if (!$port) {
            $port = self::DEFAULT_CONFIG['swoole_port'];
        }

        parent::__construct($host, $port, $options);
        $this->swoole_http_server = new \Swoole\Http\Server($this->host, $this->port);
    }

    public function start() : void
    {
        printf('Starting Swoole HTTP server on %s:%s'.PHP_EOL,$this->host, $this->port);
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