<?php

namespace Guzaba2\Http;

use Guzaba2\Base\Base as Base;

/**
 * Class HttpServer
 * A generic HTTP Server implementation
 * @package Guzaba2\Http
 */
abstract class HttpServer extends Base
{

    protected $host;

    protected $port;

    protected $options = [];

    public function __construct(string $host, int $port, array $options = [])
    {
        $this->host = $host;
        $this->port = $port;
        $this->options = $options;
    }

    public abstract function start();

    public abstract function stop();

    public abstract function on(string $event_name, callable $callable);
}