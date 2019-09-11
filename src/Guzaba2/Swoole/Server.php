<?php
declare(strict_types=1);

namespace Guzaba2\Swoole;

//use Guzaba2\Base\Base as Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Translator\Translator as t;

/**
 * Class Server
 * Swoole implementation of HTTP server
 * @package Guzaba2\Swoole
 */
class Server extends \Guzaba2\Http\Server
{

    /**
     * @see https://www.swoole.co.uk/docs/modules/swoole-server/configuration
     */
    protected const SUPPORTED_OPTIONS = [

    ];

    protected const SWOOLE_DEFAULTS = [
        'host'              => '0.0.0.0',
        'port'              => 8082,
        'dispatch_mode'     => SWOOLE_PROCESS,//SWOOLE_PROCESS or SWOOLE_BASE
    ];

    /**
     * @var \Swoole\Http\Server
     */
    protected $SwooleHttpServer;

    protected $host = self::SWOOLE_DEFAULTS['host'];

    protected $port = self::SWOOLE_DEFAULTS['port'];

    protected $dispatch_mode = self::SWOOLE_DEFAULTS['dispatch_mode'];

    protected $options = [];

    public $table;

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

//        foreach ($options as $option_name => $option_value) {
//            //if (isset(self::SWOOLE_DEFAULTS[$option_name])) {
//            //}
//            $this->SwooleHttpServer->set($options);
//        }
        $this->SwooleHttpServer->set($options);
    }

    public function get_host() : string
    {
        return $this->host;
    }

    public function get_port() : int
    {
        return $this->port;
    }

    public function start() : void
    {
        //before entering in coroutine mode it is a good idea to disable the blocking functions:
        //https://wiki.swoole.com/wiki/page/1006.html
        //\Swoole\Runtime::enableStrictMode();
        //Swoole\Runtime::enableStrictMode(): Swoole\Runtime::enableStrictMode is deprecated, it will be removed in v4.5.0    

        if (!empty($this->options['document_root']) && empty($this->options['enable_static_handler'])) {
            throw new RunTimeException(sprintf(t::_('The Swoole server has the "document_root" option set to "%s" but the "enable_static_handler" is not enabled. To serve static content the "enable_static_handler" setting needs to be enabled.')));
        }

        if (!empty($this->options['enable_static_handler']) && empty($this->options['document_root'])) {
            throw new RunTimeException(sprintf(t::_('The Swoole server has the "enable_static_handler" setting enabled but the "document_root" is not configured. To serve static content the "document_root" setting needs to be set.')));
        }
        //currently no validation or handling of static_handler_locations - instead of this the Azonmedia\Urlrewriting can be used

        Kernel::printk(sprintf(t::_('Starting Swoole HTTP server on %s:%s').PHP_EOL, $this->host, $this->port));
        if (!empty($this->options['document_root'])) {
            Kernel::printk(sprintf(t::_('Static serving is enabled and document_root is set to %s').PHP_EOL, $this->options['document_root']));
        }


        //Kernel::printk(sprintf('Starting Swoole HTTP server on %s:%s'.PHP_EOL, $this->host, $this->port));
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

//    /**
//     * Sets the worker ID for the server after the server is started.
//     * After the server is started and the workers forked each worker has its own instance of the Server object.
//     * Each server object will have its own worker_id set
//     * @param int $worker_id
//     */
//    public function set_worker_id(int $worker_id) : void
//    {
//        $this->worker_id = $worker_id;
//    }
//
//    /**
//     * @return int
//     */
//    public function get_worker_id() : int
//    {
//        return $this->worker_id;
//    }

    public function get_swoole_server() : \Swoole\Http\Server
    {
        return $this->SwooleHttpServer;
    }

    public function get_worker_id() : int
    {
        return $this->SwooleHttpServer->worker_id;
    }
}
