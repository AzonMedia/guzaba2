<?php
declare(strict_types=1);

namespace Guzaba2\Http;

use Guzaba2\Base\Base as Base;
use Guzaba2\Http\Interfaces\ServerInterface;

/**
 * Class Server
 * A generic HTTP Server implementation
 * @package Guzaba2\Http
 */
abstract class Server extends Base
implements ServerInterface
{
    protected string $host = '';

    protected int $port = 0;

    protected array $options = [];

    public function __construct(string $host, int $port, array $options = [])
    {
        parent::__construct();
        $this->host = $host;
        $this->port = $port;
        $this->options = $options;
    }

    //abstract public function get_master_pid() : int ;

    //abstract public function get_ports() : array ;

    //abstract public function get_connections() : array ;
}
