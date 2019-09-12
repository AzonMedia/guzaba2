<?php
declare(strict_types=1);

namespace Guzaba2\Http;

use Guzaba2\Base\Base as Base;

/**
 * Class Server
 * A generic HTTP Server implementation
 * @package Guzaba2\Http
 */
abstract class Server extends Base
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

    abstract public function start();

    abstract public function stop();

    abstract public function on(string $event_name, callable $callable);

    abstract public function get_worker_id() : int ;

    abstract public function get_worker_pid() : int ;

    //abstract public function get_master_pid() : int ;

    //abstract public function get_ports() : array ;

    //abstract public function get_connections() : array ;
}
