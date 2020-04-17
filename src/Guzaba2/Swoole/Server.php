<?php
declare(strict_types=1);

namespace Guzaba2\Swoole;

//use Guzaba2\Base\Base as Base;
use Azonmedia\Utilities\SysUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Event\Event;
use Guzaba2\Http\Body\Structured;
use Guzaba2\Http\Interfaces\WorkerInterface;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Swoole\Debug\Debugger;
use Guzaba2\Swoole\Interfaces\IpcRequestInterface;
use Guzaba2\Swoole\Interfaces\IpcResponseInterface;
use Guzaba2\Translator\Translator as t;
use Psr\Log\LogLevel;

/**
 * Class Server
 * Swoole implementation of HTTP server
 * @package Guzaba2\Swoole
 */
class Server extends \Guzaba2\Http\Server
{

    protected const CONFIG_DEFAULTS = [

        'ipc_responses_cleanup_time'        => 10,// in seconds - older responses than this will be removed, also used for the tick for the cleanup
        'ipc_responses_default_timeout'     => 5,//the default time to await for response

        'services'      => [
            'Events',
        ]
    ];

    protected const CONFIG_RUNTIME = [];

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

    /**
     * Associative array of IpcResponseInterface. The key is the IpcRequestInterface ID.
     * @var IpcResponseInterface[]
     */
    private array $ipc_responses = [];

    private ?float $start_microtime = NULL;

    /**
     * @var float[]
     */
    private array $worker_start_times = [];

    private Worker $Worker;

    /**
     * Server constructor.
     * @param string $host
     * @param int $port
     * @param array $options
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     */
    public function __construct(string $host = self::SWOOLE_DEFAULTS['host'], int $port = self::SWOOLE_DEFAULTS['port'], array $options = [])
    {

        $this->host = $host;
        $this->port = $port;
        $this->dispatch_mode = $options['dispatch_mode'] ?? self::SWOOLE_DEFAULTS['dispatch_mode'];

        if ($options['worker_num'] === NULL) {
            //$options['worker_num'] = swoole_cpu_num() * 2;
            //TODO - https://github.com/AzonMedia/guzaba2/issues/22
            $options['worker_num'] = swoole_cpu_num();
        }
        if (empty($options['task_worker_num'])) {
            $options['task_worker_num'] = 0;
        }

        self::validate_server_configuration_options($options);

        //TODO - do not allow task_ipc_mode = 3
        //If the configuration message_queue_key has been set, the data of message queue would not be deleted and the swoole server could get the data after a restart.
        if (empty($options['message_queue_key'])) {
            $options['message_queue_key'] = 'swoole_mq1';//if set
        }

        if (empty($options['task_enable_coroutine'])) {
            $options['task_enable_coroutine'] = TRUE;//always true... no matter what is provided
        }

        $this->options = $options;


        parent::__construct($this->host, $this->port, $this->options);//TODO - sock type needed?

        $sock_type = SWOOLE_SOCK_TCP;
        if (!empty($this->options['ssl_cert_file'])) {
            $sock_type |= SWOOLE_SSL;
        }
        $this->SwooleHttpServer = new \Swoole\Http\Server($this->host, $this->port, $this->dispatch_mode, $sock_type);
        


        $this->SwooleHttpServer->set($options);

        Kernel::set_http_server($this);
    }

    /**
     * To be invoked by WorkerStart
     * @param Worker $Worker
     */
    public function set_worker(Worker $Worker): void
    {
        $this->Worker = $Worker;
    }

    /**
     * @param int $worker_id
     * @return Worker
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     * @throws LogicException
     */
    public function get_worker(): WorkerInterface
    {
        if ($this->get_start_microtime() === NULL) {
            throw new RunTimeException(sprintf(t::_('The server is not yet started. There are no workers.')));
        }
        return $this->Worker;
    }


    /**
     * Validates swooole server configuration options
     * @param array $options this array will be passed to $SwooleHttpServer->set()
     *
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     */
    public static function validate_server_configuration_options(array $options) : void
    {
        foreach ($options as $option_name => $option_value) {
            if (!in_array($option_name, self::SUPPORTED_OPTIONS)) {
                throw new \Guzaba2\Base\Exceptions\InvalidArgumentException(sprintf(t::_('Invalid option "%s" provided to server configuration.'), $option_name));
            }
        }

        if (array_key_exists('task_enable_coroutine', $options) && $options['task_enable_coroutine'] !== TRUE) {
            throw new InvalidArgumentException(sprintf(t::_('It is not allowed to disable the coroutines in the task workers (task_enable_coroutine = FALSE).')));
        }

        if (!empty($options['task_ipc_mode']) && $options['task_ipc_mode'] === 3) {
            throw new InvalidArgumentException(sprintf(t::_('task_ipc_mode = 3 is not supported. Mode 3 does not support to specify task worker process in Server::task().')));
        }

        if (!empty($options['document_root']) && empty($options['enable_static_handler'])) {
            throw new RunTimeException(sprintf(t::_('The Swoole server has the "document_root" option set to "%s" but the "enable_static_handler" is not enabled. To serve static content the "enable_static_handler" setting needs to be enabled.'), $options['document_root']));
        }
        if (!empty($options['upload_tmp_dir'])) {
            if (!file_exists($options['upload_tmp_dir'])) {
                throw new RunTimeException(sprintf(t::_('The upload_tmp_dir path %s does not exist. It must be a writable directory.'), $options['upload_tmp_dir'] ));
            }
            if (!is_dir($options['upload_tmp_dir'])) {
                throw new RunTimeException(sprintf(t::_('The upload_tmp_dir path %s exists but it is a file. It must be a writable directory.'), $options['upload_tmp_dir'] ));
            }
            if (!is_writeable($options['upload_tmp_dir'])) {
                throw new RunTimeException(sprintf(t::_('The upload_tmp_dir path %s exists but it is not writeable. It must be a writable directory.'), $options['upload_tmp_dir'] ));
            }
        }

        if (!empty($options['enable_static_handler']) && empty($options['document_root'])) {
            throw new RunTimeException(sprintf(t::_('The Swoole server has the "enable_static_handler" setting enabled but the "document_root" is not configured. To serve static content the "document_root" setting needs to be set.')));
        }
        if (!empty($options['open_http2_protocol']) && (empty($options['ssl_cert_file']) || empty($options['ssl_key_file']))) {
            throw new RunTimeException(sprintf(t::_('HTTP2 is enabled but no SSL is configured. ssl_cert_file or ssl_key_file is not set. You can also try to start the application server with --enable-ssl.')));
        }
        if (!empty($options['ssl_cert_file']) && !is_readable($options['ssl_cert_file'])) {
            throw new RunTimeException(sprintf(t::_('The specified SSL certificate file %s is not readable. Please check the filesystem permissions. The file must be readable by the user executing the server.'), $options['ssl_cert_file']));
        }
        if (!empty($options['ssl_key_file']) && !is_readable($options['ssl_key_file'])) {
            throw new RunTimeException(sprintf(t::_('The specified SSL key file %s is not readable. Please check the filesystem permissions. The file must be readable by the user executing the server.'), $options['ssl_cert_file']));
        }
        //since Swoole 4.4.14 this is supported
        //if (!empty($options['open_http2_protocol']) && !empty($options['enable_static_handler'])) {
        //    throw new RunTimeException(sprintf(t::_('Swoole does not support HTTP2 and static handler to be enabled both. The static handler can only be used with HTTP 1.1.')));
        //}

        if (!empty($options['daemonize']) && empty($options['log_file'])) {
            throw new RunTimeException(sprintf(t::_('The "daemonize" option is set but there is no "log_file" option specified.')));
        }
        if (!empty($options['daemonize']) && file_exists($options['log_file']) && !is_writable($options['log_file'])) {
            throw new RunTimeException(sprintf(t::_('The specified log_file path %s exists but is not writable. Please check the filesystem permissions. File file must be writable by the user executing the server.'), $options['log_file'] ));
        }
        if (!empty($options['daemonize']) && !file_exists($options['log_file']) && !is_writable(dirname($options['log_file']))) {
            throw new RunTimeException(sprintf(t::_('The specified log_file path %s does not exists but can not be created because the directory %s is not writeable. Please check the filesystem permissions. File directory must be writable by the user executing the server.'), $options['log_file'] , dirname($options['log_file']) ));
        }
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
        self::get_service('Events')->create_event($this, '_before_start');
        \Swoole\Runtime::enableCoroutine(TRUE);//we will be running everything in coroutine context and makes sense to enable all hooks

        $this->start_microtime = microtime(TRUE);
        $this->SwooleHttpServer->start();
        //self::get_service('Events')->create_event($this, '_after_start');//no code is being executed after the server is started... the next code that is being executed is in the worker start or Start handler
    }

    /**
     * @return float|null
     */
    public function get_start_microtime(): ?float
    {
        return $this->start_microtime;
    }

    public function set_worker_start_time(int $worker_id, float $time): void
    {
        $this->validate_worker_id($worker_id);
        $this->worker_start_times[$worker_id] = $time;
    }

    public function get_worker_start_time(?int $worker_id = NULL): ?float
    {
        if ($worker_id === NULL) {
            $worker_id = $this->get_worker_id();
        }
        return $this->worker_start_times[$worker_id] ?? NULL ;
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
            Kernel::printk(sprintf(t::_('Worker debug ports enabled: %s - %s'), $base_port, $base_port + $this->get_total_workers()).PHP_EOL);
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
        Kernel::printk(sprintf(t::_('End of startup messages - Swoole server is now serving requests')).PHP_EOL );
        Kernel::printk(PHP_EOL);
    }

    public function stop(): void
    {
        $this->SwooleHttpServer->stop();
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

    public function get_worker_id(): int
    {
        return $this->SwooleHttpServer->worker_id;
        //return $this->SwooleHttpServer->getWorkerId();//this returns Bool instead of -1 when not started
    }

    public function get_worker_pid() : int
    {
        return $this->SwooleHttpServer->worker_pid;
    }

    public function get_manager_pid(): int
    {
        return $this->SwooleHttpServer->getManagerPid();
    }

    public function get_master_pid(): int
    {
        return $this->SwooleHttpServer->getMasterPid();
    }

    public function is_task_worker(): bool
    {
        //there is no Swoole\Server method for that
        return $this->SwooleHttpServer->taskworker;
    }


//    /**
//     * @param string $option
//     * @return bool
//     */
//    public function option_is_set(string $option): bool
//    {
//        return array_key_exists($option, $this->options);
//    }

    /**
     * @param string $option
     * @return mixed
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     */
    public function get_option(string $option) /* mixed */
    {
//        if (!$this->option_is_set($option)) {
//            throw new RunTimeException(sprintf(t::_('The option %s is not set.'), $option));
//        }
//        return $this->options[$option];
        if (!in_array($option, self::SUPPORTED_OPTIONS)) {
            throw new InvalidArgumentException(sprintf(t::_('An unsupported option %1s is provided.'), $option));
        }
        return $this->SwooleHttpServer->setting[$option];
    }

    /**
     * Returns all options passed to the Swoole\Http\Server
     * @return array
     */
    public function get_options(): array
    {
        return $this->options;
    }

    /**
     * Returns all options from Swoole\Http\Server as they are set internally.
     * @return array
     */
    public function get_all_options(): array
    {
        //return $this->options;
        return $this->SwooleHttpServer->setting;
    }

    /**
     * @return string|null
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     */
    public function get_document_root() : ?string
    {
        //return $this->option_is_set('document_root') ? $this->get_option('document_root'): NULL;
        return $this->get_option('document_root');
    }

    /**
     * Sends an IPC
     * @param IpcRequest $IpcRequest
     * @param int $dest_worker_id
     * @return bool
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function send_icp_message(IpcRequest $IpcRequest, int $dest_worker_id): bool
    {
        $this->validate_destination_worker_id($dest_worker_id);
        return $this->SwooleHttpServer->sendMessage($IpcRequest, $dest_worker_id);
    }

    /**
     * Unlike send_ipc_message() this method awaits and returns the response
     * @param IpcResponseInterface $IpcResponse
     * @param string $ipc_request_id
     * @param int $src_worker_id
     * @throws LogicException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function set_ipc_request_response(IpcResponseInterface $IpcResponse, string $ipc_request_id, int $src_worker_id): void
    {

        if (isset($this->ipc_responses[$ipc_request_id][$src_worker_id])) {
            Kernel::log(sprintf(t::_('There is already has IpcResponse for IpcRequest ID %1s from worker %2s.'), $ipc_request_id, $src_worker_id), LogLevel::NOTICE);
        }
        if ( !( $IpcResponse->getBody()) instanceof Structured) {
            throw new LogicException(sprintf(t::_('The IpcResponse Body is not of class %1s but is of class %2s.'), Structured::class, get_class($IpcResponse->getBody()) ));
        }
        $IpcResponse->set_received_microtime(microtime(TRUE));
        $this->ipc_responses[$ipc_request_id][$src_worker_id] = $IpcResponse;
    }

    /**
     * @param IpcRequest $IpcRequest
     * @param int $dest_worker_id
     * @param int $timeout
     * @return IpcResponse|null
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function send_ipc_request(IpcRequestInterface $IpcRequest, int $dest_worker_id, int $timeout = self::CONFIG_RUNTIME['ipc_responses_default_timeout']): ?IpcResponseInterface
    {
        new Event($this, '_before_send_ipc_request', func_get_args());

        $ret = NULL;

        $this->validate_destination_worker_id($dest_worker_id);
        if ($timeout > self::CONFIG_RUNTIME['ipc_responses_cleanup_time']) {
            throw new InvalidArgumentException(sprintf(t::_('The maximum timeout for awaiting an IpcResponse is %1s seconds.'), self::CONFIG_RUNTIME['ipc_responses_cleanup_time']));
        }

        $microtime_start = microtime(TRUE);
        $request_id = $IpcRequest->get_request_id();
        $IpcRequest->set_requires_response(TRUE);

        if ($this->SwooleHttpServer->sendMessage($IpcRequest, $dest_worker_id)) {
            while (true) {
                if (microtime(TRUE) > $microtime_start + $timeout) {
                    //return NULL;
                    goto ret;
                }
                Coroutine::sleep(0.001);
                if (isset($this->ipc_responses[$request_id][$dest_worker_id])) {
                    $IpcResponse = $this->ipc_responses[$request_id][$dest_worker_id];
                    unset($this->ipc_responses[$request_id][$dest_worker_id]);
                    //return $IpcResponse;
                    $ret = $IpcResponse;
                    goto ret;
                }
            }
        } else {
            throw new RunTimeException(sprintf(t::_('The %1s::%2s() call returned FALSE.'), \Swoole\Http\Server::class, 'sendMessage'));
        }

        ret:
        new Event($this, '_after_send_ipc_request', func_get_args());
        return $ret;//just in case...
    }

    /**
     * Sends a request to all other workers.
     * @param IpcRequestInterface $IpcRequest
     * @param int $timeout
     * @return IpcResponseInterface[]
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function send_broadcast_ipc_request(IpcRequestInterface $IpcRequest, int $timeout = self::CONFIG_RUNTIME['ipc_responses_default_timeout']): array
    {
        $total_workers = $this->get_total_workers();
        $dest_worker_ids = range(0, $total_workers - 1);
        //remove the worker_id of the current one
        $current_worker_id = $this->get_worker_id();
        $key = array_search($current_worker_id, $dest_worker_ids, TRUE);
        if ($key === FALSE) {
            throw new LogicException(sprintf(t::_('The ID %1s of the current worker is not found in the list of IDs of all the workers.'), $current_worker_id ));
        }
        unset($dest_worker_ids[$key]);
        $dest_worker_ids = array_values($dest_worker_ids);
        return $this->send_multicast_ipc_request($IpcRequest, $dest_worker_ids, $timeout);
    }

    /**
     * Sends an IpcRequest to multiple workers
     * @param IpcRequestInterface $IpcRequest
     * @param array $dest_worker_ids
     * @param int $timeout
     * @return IpcResponseInterface[]
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function send_multicast_ipc_request(IpcRequestInterface $IpcRequest, array $dest_worker_ids, int $timeout = self::CONFIG_RUNTIME['ipc_responses_default_timeout']): array
    {
        $callables = [];
        if (count($dest_worker_ids)) {
            foreach ($dest_worker_ids as $dest_worker_id) {
                $callables[] = function() use ($IpcRequest, $dest_worker_id, $timeout): ?IpcResponseInterface {
                    return $this->send_ipc_request($IpcRequest, $dest_worker_id, $timeout);
                };
            }
            return Coroutine::executeMulti(...$callables);
        } else {
            return [];
        }

    }

    /**
     * Returns the total number (request handling workers + task workers) of the workers
     * @return int
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function get_total_workers(): int
    {
        return $this->get_option('worker_num') + $this->get_option('task_worker_num');
    }

    /**
     * @param int $dest_worker_id
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    private function validate_destination_worker_id(int $dest_worker_id): void
    {

//        } elseif ($dest_worker_id === $this->SwooleHttpServer->worker_id) {
//            throw new InvalidArgumentException(sprintf(t::_('It is not possible to send IPC message to the same $dest_worker_id as the current worker_id %1s.'), $this->SwooleHttpServer->worker_id));
//        }
        $this->validate_worker_id($dest_worker_id);
        if ($dest_worker_id === $this->get_worker_id()) {
            throw new InvalidArgumentException(sprintf(t::_('It is not possible to send IPC message to the same $dest_worker_id as the current worker_id %1s.'), $this->get_worker_id() ));
        }
    }

    /**
     * Throws InvalidArgumentException if the provided $worker_id is not a valid one (there is no such worker).
     * @param int $worker_id
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function validate_worker_id(int $worker_id): void
    {
        $worker_num = $this->get_option('worker_num');
        $task_worker_num = $this->get_option('task_worker_num');
        $total_workers = $worker_num + $task_worker_num;
        if (!$worker_id < 0) {
            throw new InvalidArgumentException(sprintf(t::_('The $dest_worker_id must be positive number.')));
        } elseif ($worker_id >= $total_workers) { //the worker IDs always start from 0 and even if restarted they get the same ID
            $message = sprintf(t::_('Invalid $dest_worker_id %1s is provided. There are %2s workers and %3s task workers. The valid range for $dest_worker_id is %4s - %5s.'), $worker_id, $worker_num, $task_worker_num, 0, $worker_num + $task_worker_num - 1);
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * Removes old IPC responses.
     * This is needed as an IPC response may remain uncollected.
     */
    public function ipc_responses_cleanup(): void
    {

        foreach ($this->ipc_responses as $ipc_request_id => $worker_data) {
            foreach ($worker_data as $worker_id => $IpcResponse) {
                if ($IpcResponse->get_received_microtime() < microtime(TRUE) - self::CONFIG_RUNTIME['ipc_responses_cleanup_time']) {
                    unset($this->ipc_responses[$ipc_request_id][$worker_id]);
                }
            }
        }
        foreach ($this->ipc_responses as $ipc_request_id => $worker_data) {
            if (!count($worker_data)) {
                unset($this->ipc_responses[$ipc_request_id]);
            }
        }
    }

    /**
     * @return int
     */
    public function get_ipc_responses_cleanup_time(): int
    {
        return self::CONFIG_RUNTIME['ipc_responses_cleanup_time'];
    }
}
