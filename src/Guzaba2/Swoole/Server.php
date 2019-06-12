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

    protected $SwooleHttpServer;

    public const SUPPPORTED_EVENTS = [];

    /**
     * @see https://www.swoole.co.uk/docs/modules/swoole-server/configuration
     */
    protected const SUPPORTED_OPTIONS = [

    ];

    protected const SWOOLE_DEFAULTS = [
        'host'              => '0.0.0.0',
        'port'              => 8081,
        'dispatch_mode'     => 2,
    ];

    protected $host = self::SWOOLE_DEFAULTS['host'];

    protected $port = self::SWOOLE_DEFAULTS['port'];

    protected $dispatch_mode = self::SWOOLE_DEFAULTS['dispatch_mode'];

    protected $options = [];

    /**
     * @var int
     */
    protected $worker_id;


    public function __construct(string $host = self::SWOOLE_DEFAULTS['host'], int $port = self::SWOOLE_DEFAULTS['port'], array $options = [])
    {
        $this->host = $host;
        $this->port = $port;
        $this->dispatch_mode = $options['dispatch_mode'] ?? self::SWOOLE_DEFAULTS['dispatch_mode'];
        $this->options = $options;


        parent::__construct($this->host, $this->port, $this->options);//TODO - sock type needed?


        $this->SwooleHttpServer = new \Swoole\Http\Server($this->host, $this->port, $this->dispatch_mode);

        foreach ($options as $option_name => $option_value) {
            //if (isset(self::SWOOLE_DEFAULTS[$option_name])) {
            //}
            $this->SwooleHttpServer->set($options);
        }
    }

    public function start() : void
    {
        printf('Starting Swoole HTTP server on %s:%s'.PHP_EOL,$this->host, $this->port);
        $this->SwooleHttpServer->start();
    }

    public function stop() : bool
    {
        return $this->SwooleHttpServer->stop();
    }

    public function on(string $event_name, callable $callable) : void
    {
        $this->SwooleHttpServer->on($event_name, $callable);
    }

    public function __call(string $method, array $args) /* mixed */
    {
        return call_user_func_array([$this->SwooleHttpServer, $method], $args);
    }

    /**
     * Sets the worker ID for the server after the server is started.
     * After the server is started and the workers forked each worker has its own instance of the Server object.
     * Each server object will have its own worker_id set
     * @param int $worker_id
     */
    public function set_worker_id(int $worker_id) : void
    {
        $this->worker_id = $worker_id;
    }

    public function get_worker_id() : int
    {
        return $this->worker_id;
    }


}