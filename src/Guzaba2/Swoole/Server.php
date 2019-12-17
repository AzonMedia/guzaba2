<?php
declare(strict_types=1);

namespace Guzaba2\Swoole;

//use Guzaba2\Base\Base as Base;
use Azonmedia\Utilities\SysUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Swoole\Debug\Debugger;
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
     * @see https://wiki.swoole.com/wiki/page/274.html
     */
    public const SUPPORTED_OPTIONS = [
        //Server options
        //https://wiki.swoole.com/wiki/page/274.html
        'reactor_num',
        'worker_num',
        'max_request',
        'max_conn',
        'task_worker_num',
        'task_ipc_mode',
        'task_max_request',
        'task_tmpdir',
        'task_enable_coroutine',
        'task_use_object',
        'dispatch_mode',
        'dispatch_func',
        'message_queue_key',
        'daemonize',
        'backlog',
        'log_file',
        'log_level',
        'heartbeat_check_interval',
        'heartbeat_idle_time',
        'open_eof_check',
        'open_eof_split',
        'package_eof',
        'open_length_check',
        'package_length_type',
        'package_length_func',
        'package_max_length',
        'open_cpu_affinity',
        'cpu_affinity_ignore',
        'open_tcp_nodelay',
        'tcp_defer_accept',
        'ssl_cert_file',
        'ssl_method',
        'ssl_ciphers',
        'user',
        'group',
        'chroot',
        'pid_file',
        'pipe_buffer_size',
        'buffer_output_size',
        'socket_buffer_size',
        'enable_unsafe_event',
        'discard_timeout_request',
        'enable_reuse_port',
        'enable_delay_receive',
        'open_http_protocol',
        'open_http2_protocol',
        'open_websocket_protocol',
        'open_mqtt_protocol',
        'open_websocket_close_frame',
        'reload_async',
        'tcp_fastopen',
        'request_slowlog_file',
        'enable_coroutine',
        'max_coroutine',
        'ssl_verify_peer',
        'max_wait_time',

        //Http Server options
        //https://wiki.swoole.com/wiki/page/620.html
        'upload_tmp_dir',
        'http_parse_post',
        'http_parse_cookie',
        'http_compression',
        'document_root',
        'enable_static_handler',
        'static_handler_locations',
        

        'ssl_key_file',//this is mandatory if ssl_cert_file is used

    ];

    public const SWOOLE_DEFAULTS = [
        'host'              => '0.0.0.0',
        'port'              => 8081,
        'dispatch_mode'     => SWOOLE_PROCESS,//SWOOLE_PROCESS or SWOOLE_BASE
    ];

    /**
     * @var \Swoole\Http\Server
     */
    private \Swoole\Http\Server $SwooleHttpServer;

    protected string $host = self::SWOOLE_DEFAULTS['host'];

    protected int $port = self::SWOOLE_DEFAULTS['port'];

    private int $dispatch_mode = self::SWOOLE_DEFAULTS['dispatch_mode'];

    protected array $options = [];

    /**
     * Associative array of event=>callable for the various handlers
     * @var array
     */
    private array $handlers = [];

    /**
     * @var int
     */
    private int $worker_id = -1;//0 is a valid worker id


    public function __construct(string $host = self::SWOOLE_DEFAULTS['host'], int $port = self::SWOOLE_DEFAULTS['port'], array $options = [])
    {
        $this->host = $host;
        $this->port = $port;
        $this->dispatch_mode = $options['dispatch_mode'] ?? self::SWOOLE_DEFAULTS['dispatch_mode'];

        if ($options['worker_num'] === NULL) {
            $options['worker_num'] = swoole_cpu_num() * 2;
        }

        $this->options = $options;


        parent::__construct($this->host, $this->port, $this->options);//TODO - sock type needed?

        $sock_type = SWOOLE_SOCK_TCP;
        if (!empty($this->options['ssl_cert_file'])) {
            $sock_type |= SWOOLE_SSL;
        }
        $this->SwooleHttpServer = new \Swoole\Http\Server($this->host, $this->port, $this->dispatch_mode, $sock_type);
        
        self::validate_server_configuration_options($options);

        $this->SwooleHttpServer->set($options);

        Kernel::set_http_server($this);
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



        //currently no validation or handling of static_handler_locations - instead of this the Azonmedia\Urlrewriting can be used


        $this->print_server_start_messages();

        //just before the server is started enable the coroutine hooks (not earlier as these will be in place but we will not be in coroutine cotext yet and this will trigger an error - for example when exec() is used)
        \Swoole\Runtime::enableCoroutine(TRUE);//we will be running everything in coroutine context and makes sense to enable all hooks
        $this->SwooleHttpServer->start();
    }

    private function print_server_start_messages() : void
    {
        //Kernel::printk(Kernel::FRAMEWORK_BANNER);
        Kernel::printk(PHP_EOL);

        //TODO - add option for setting the timezone of the application, and time format
        Kernel::printk(sprintf(t::_('Starting Swoole HTTP server on %s:%s at %s %s').PHP_EOL, $this->host, $this->port, date('Y-m-d H:i:s'), date_default_timezone_get() ));

        if (!empty($this->options['document_root'])) {
            Kernel::printk(sprintf(t::_('Static serving is enabled and document_root is set to %s').PHP_EOL, $this->options['document_root']));
        }

        //$debugger_ports = Debugger::is_enabled() ? Debugger::get_base_port().' - '.(Debugger::get_base_port() + $this->options['worker_num']) : t::_('Debugger Disabled');
        //Kernel::printk(sprintf(t::_('Workers: %s, Task Workers: %s, Workers Debug Ports: %s'), $this->options['worker_num'], $this->options['task_worker_num'], $debugger_ports ).PHP_EOL );
        Kernel::printk(sprintf(t::_('Workers: %s, Task Workers: %s'), $this->options['worker_num'], $this->options['task_worker_num'] ).PHP_EOL );
        $WorkerStartHandler = $this->get_handler('WorkerStart');
        if ($WorkerStartHandler->debug_ports_enabled()) {
            $base_port = $WorkerStartHandler->get_base_debug_port();
            Kernel::printk(sprintf(t::_('Worker debug ports enabled: %s - %s'), $base_port, $base_port + $this->options['worker_num']).PHP_EOL);
        }
        if (!empty($this->options['open_http2_protocol'])) {
            Kernel::printk(sprintf(t::_('HTTP2 enabled')).PHP_EOL);
        }
        if (!empty($this->options['ssl_cert_file'])) {
            Kernel::printk(sprintf(t::_('HTTPS enabled')).PHP_EOL);
        }
        if (!empty($this->options['daemonize'])) {
            Kernel::printk(sprintf(t::_('DEAMONIZED, log file: %s'), $this->options['log_file']).PHP_EOL);
        }
        Kernel::printk(sprintf(t::_('End of startup messages. Swoole server is now serving requests.')).PHP_EOL );
        Kernel::printk(PHP_EOL);
    }

    public function stop() : bool
    {
        return $this->SwooleHttpServer->stop();
    }

    public function on(string $event_name, callable $callable) : void
    {
        $this->handlers[$event_name] = $callable;
        $this->SwooleHttpServer->on($event_name, $callable);
    }

    /**
     * The event name is case insensitive.
     * @param string $event_name
     * @return callable|null
     */
    public function get_handler(string $event_name) : ?callable
    {
        //return $this->handlers[$event_name] ?? NULL;
        $ret = NULL;
        foreach ($this->handlers as $handler_name=>$callable) {
            if (strtolower($handler_name)===strtolower($event_name)) {
                $ret = $callable;
                break;
            }
        }
        return $ret;
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

//    public function get_swoole_server() : \Swoole\Http\Server
//    {
//        return $this->SwooleHttpServer;
//    }

    public function get_worker_id() : int
    {
        return $this->SwooleHttpServer->worker_id;
    }

    /**
     * Validates swooole server configuration options
     * @param array $options this array will be passed to $SwooleHttpServer->set()
     *
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     */
    public static function validate_server_configuration_options(array $options) : void
    {
        foreach ($options as $option_name => $option_value) {
            if (!in_array($option_name, self::SUPPORTED_OPTIONS)) {
                throw new \Guzaba2\Base\Exceptions\InvalidArgumentException(sprintf(t::_('Invalid option "%s" provided to server configuration.'), $option_name));
            }
        }

        if (!empty($options['document_root']) && empty($options['enable_static_handler'])) {
            throw new RunTimeException(sprintf(t::_('The Swoole server has the "document_root" option set to "%s" but the "enable_static_handler" is not enabled. To serve static content the "enable_static_handler" setting needs to be enabled.'), $options['document_root']));
        }

        if (!empty($options['enable_static_handler']) && empty($options['document_root'])) {
            throw new RunTimeException(sprintf(t::_('The Swoole server has the "enable_static_handler" setting enabled but the "document_root" is not configured. To serve static content the "document_root" setting needs to be set.')));
        }
        if (!empty($options['open_http2_protocol']) && (empty($options['ssl_cert_file']) || empty($options['ssl_key_file']))) {
            throw new RunTimeException(sprintf(t::_('HTTP2 is enabled but no SSL is configured. ssl_cert_file or ssl_key_file is not set.')));
        }
        if (!empty($options['ssl_cert_file']) && !is_readable($options['ssl_cert_file'])) {
            throw new RunTimeException(sprintf(t::_('The specified SSL certificate file %s is not readable.'), $options['ssl_cert_file']));
        }
        if (!empty($options['ssl_key_file']) && !is_readable($options['ssl_key_file'])) {
            throw new RunTimeException(sprintf(t::_('The specified SSL key file %s is not readable.'), $options['ssl_cert_file']));
        }
        //since Swoole 4.4.14 this is supported
        //if (!empty($options['open_http2_protocol']) && !empty($options['enable_static_handler'])) {
        //    throw new RunTimeException(sprintf(t::_('Swoole does not support HTTP2 and static handler to be enabled both. The static handler can only be used with HTTP 1.1.')));
        //}

        if (!empty($options['daemonize']) && empty($options['log_file'])) {
            throw new RunTimeException(sprintf(t::_('The "daemonize" option is set but there is no "log_file" option specified.')));
        }
        if (!empty($options['daemonize']) && file_exists($options['log_file']) && !is_writable($options['log_file'])) {
            throw new RunTimeException(sprintf(t::_('The specified log_file path %s exists but is not writable.'), $options['log_file'] ));
        }
        if (!empty($options['daemonize']) && !file_exists($options['log_file']) && !is_writable(dirname($options['log_file']))) {
            throw new RunTimeException(sprintf(t::_('The specified log_file path %s does not exists but can not be created because the directory %s is not writeable.'), $options['log_file'] , dirname($options['log_file']) ));
        }
    }

    public function option_is_set(string $option) : bool
    {
        return array_key_exists($option, $this->options);
    }

    public function get_option(string $option) /* mixed */
    {
        if (!$this->option_is_set($option)) {
            throw new RunTimeException(sprintf(t::_('The option %s is not set.'), $option));
        }
        return $this->options[$option];
    }

    public function get_document_root() : ?string
    {
        return $this->option_is_set('document_root') ? $this->get_option('document_root') : NULL;
    }

    public function get_worker_pid() : int
    {
        return $this->SwooleHttpServer->worker_pid;
    }

//    public function get_master_pid() : int
//    {
//        return $this->SwooleHttpServer->master_pid;
//    }
}
