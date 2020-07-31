<?php

declare(strict_types=1);

/**
 * Guzaba Framework 2
 * http://framework2.guzaba.org
 *
 * This source file is subject to the BSD license that is bundled with this
 * package in the file LICENSE.txt and available also at:
 * http://www.opensource.org/licenses/bsd-license.php
 *
 * @category    Guzaba2 Framework
 * @package        Base
 * @license        http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author        Veselin Kenashkov <kenashkov@azonmedia.com>
 */

namespace Guzaba2\Kernel;

use Azonmedia\Reflection\ReflectionClass;
use Azonmedia\Registry\Interfaces\RegistryInterface;
use Azonmedia\Utilities\ArrayUtil;
use Azonmedia\Utilities\GeneralUtil;
use Azonmedia\Utilities\StackTraceUtil;
use Azonmedia\Utilities\SysUtil;
use Composer\Util\Platform;
use Guzaba2\Application\Application;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Base\Interfaces\ConfigInterface;
use Guzaba2\Base\TraceInfoObject;
use Guzaba2\Base\Traits\SupportsConfig;
use Guzaba2\Coroutine\Coroutine;
//use Guzaba2\Database\Connection;
use Guzaba2\Http\Body\Stream;
use Guzaba2\Http\Server;
use Guzaba2\Kernel\Exceptions\ConfigurationException;
use Guzaba2\Kernel\Interfaces\ClassInitializationInterface;
use Guzaba2\Orm\ActiveRecord;
//use Guzaba2\Translator\Translator as t;
use Azonmedia\Translator\Translator as t;
use Monolog\Handler\StreamHandler;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Azonmedia\Watchdog\Watchdog;
use Guzaba2\Kernel\Interfaces\ClassDeclarationValidationInterface;

/**
 * Class Kernel
 * @package Guzaba2\Kernel
 * A static class.
 * @example
 * Kernel::initialize($Registry, $Logger);
 * Kernel::register_autoloader_path($path_to_classes);
 * Kernel::set_di($Di);//optional
 * Kernel::run($callable);
 */
abstract class Kernel
{

    /**
     *
     */
    public const FRAMEWORK_NAME = 'Guzaba2';

    /**
     *
     */
    public const FRAMEWORK_VERSION = '2-dev';

    public const RUN_OPTIONS_DEFAULTS = [
        'disable_all_class_load'            => false,
        'disable_all_class_validation'      => false,
    ];

    public const EXIT_SUCCESS = 0;

    public const EXIT_GENERAL_ERROR = 1;

    /**
     * Currently microtime(TRUE) returns up to 100 microseconds.
     * Time differences between two microtimes make sense to be rounded up to the 4 digit.
     */
    public const MICROTIME_ROUNDING = 4;

    public const MICROTIME_EPS = 100;//100 microseconds

    //http://patorjk.com/software/taag/#p=display&f=ANSI%20Shadow&t=guzaba%202%20framework
    public const FRAMEWORK_BANNER = <<<BANNER

 ██████╗ ██╗   ██╗███████╗ █████╗ ██████╗  █████╗     ██████╗     ███████╗██████╗  █████╗ ███╗   ███╗███████╗██╗    ██╗ ██████╗ ██████╗ ██╗  ██╗
██╔════╝ ██║   ██║╚══███╔╝██╔══██╗██╔══██╗██╔══██╗    ╚════██╗    ██╔════╝██╔══██╗██╔══██╗████╗ ████║██╔════╝██║    ██║██╔═══██╗██╔══██╗██║ ██╔╝
██║  ███╗██║   ██║  ███╔╝ ███████║██████╔╝███████║     █████╔╝    █████╗  ██████╔╝███████║██╔████╔██║█████╗  ██║ █╗ ██║██║   ██║██████╔╝█████╔╝ 
██║   ██║██║   ██║ ███╔╝  ██╔══██║██╔══██╗██╔══██║    ██╔═══╝     ██╔══╝  ██╔══██╗██╔══██║██║╚██╔╝██║██╔══╝  ██║███╗██║██║   ██║██╔══██╗██╔═██╗ 
╚██████╔╝╚██████╔╝███████╗██║  ██║██████╔╝██║  ██║    ███████╗    ██║     ██║  ██║██║  ██║██║ ╚═╝ ██║███████╗╚███╔███╔╝╚██████╔╝██║  ██║██║  ██╗
 ╚═════╝  ╚═════╝ ╚══════╝╚═╝  ╚═╝╚═════╝ ╚═╝  ╚═╝    ╚══════╝    ╚═╝     ╚═╝  ╚═╝╚═╝  ╚═╝╚═╝     ╚═╝╚══════╝ ╚══╝╚══╝  ╚═════╝ ╚═╝  ╚═╝╚═╝  ╚═╝
                                                  
BANNER;


    /**
     * @var string
     */
    protected static string $cwd = '';

    /**
     * @var string
     */
    protected static string $kernel_dir = '';

    /**
     * @var string
     */
    protected static string $framework_root_dir = '';

    /**
     * @var array
     */
    protected static array $loaded_classes = [];

    /**
     * Additional places where the autoloader should look.
     * An associative array containing namespace prefix as key and lookup path as value.
     * @var array
     */
    protected static array $autoloader_lookup_paths = [];


    /**
     * Is the kernel initialized
     * @var bool
     */
    protected static bool $is_initialized_flag = false;

    /**
     * ['class_name'] => 'parent' => X, 'children' => [Z, Y]
     * @var array
     */
    protected static array $class_structure = [];

    /**
     * @var LoggerInterface
     */
    protected static LoggerInterface $Logger;

    /**
     * @var RegistryInterface
     */
    protected static RegistryInterface $Registry;

    /**
     * @var ContainerInterface
     */
    protected static ContainerInterface $Container;

    /**
     * @var Server
     */
    protected static ?Server $HttpServer = null;

    /**
     * @var Watchdog
     */
    public static Watchdog $Watchdog;

    private static ?float $init_microtime = null;

    public const APM_DATA_STRUCTURE = [
        'worker_pid'                            => 0,
        'worker_id'                             => 0,
        'coroutine_id'                          => 0,
        'execution_start_microtime'             => 0,
        'execution_end_microtime'               => 0,
        'cnt_used_connections'                  => 0,
        'time_used_connections'                 => 0,//for all connections - for how long the connection was held
        'time_waiting_for_connection'           => 0,//waiting time to obtain connection from the Pool
        'cnt_total_current_coroutines'          => 0,
        'cnt_subcoroutines'                     => 0,
        'memory_store_time'                     => 0,//time for lookups in memory store

        'cnt_dql_statements'                    => 0,
        'time_dql_statements'                   => 0,

        //counter added in pdoStatement::execute()
        'cnt_cached_dql_statements'             => 0,
        'time_cached_dql_statements'            => 0,

        //counter added in pdoStatement::fetchAllAsArray()
        'time_fetching_data'                    => 0,//to measure fetchAll and similar - see swoole\mysql fetch_mode - if FALSE then the fetch is done as part of the execute()

        //counter added in pdoStatement::execute()
        'cnt_dml_statements'                    => 0,
        'time_dml_statements'                   => 0,

        //counter added in pdoStatement::execute()
        'cnt_ddl_statements'                    => 0,
        'time_ddl_statements'                   => 0,

        //counter added in pdoStatement::execute()
        'cnt_dcl_statements'                    => 0,
        'time_dcl_statements'                   => 0,

        //counter added in pdoStatement::execute()
        'cnt_dal_statements'                    => 0,
        'time_dal_statements'                   => 0,

        //counter added in pdoStatement::execute()
        'cnt_tcl_statements'                    => 0,//these usually are not issued directly but by using PDO's methods... so the PDO::beginTransction and PDO::commit are wrapped to add to this number
        'time_tcl_statements'                   => 0,

        'cnt_nosql_read_statements'             => 0,
        'time_nosql_read_statements'            => 0,
        'cnt_nosql_write_statements'            => 0,
        'time_nosql_write_statements'           => 0,

        'cnt_api_requests'                      => 0,//curl
        'time_api_requests'                     => 0,

        'cnt_file_reads'                        => 0,
        'time_file_reads'                       => 0,
        'cnt_file_writes'                       => 0,
        'time_file_writes'                      => 0,

        'cnt_acquired_locks'                    => 0,
        'time_acquired_locks'                   => 0,

        'time_in_transactions'                  => 0,
        'time_in_commits'                       => 0,

        'cnt_master_transctions'                => 0,//this is also the number of commits
        'cnt_nested_transactions'               => 0,

        //'request_handling_time'                 => 0,
    ];

    //
    /**
     * @see https://www.php.net/manual/en/function.php-sapi-name.php
     * Although not exhaustive, the possible return values include aolserver, apache, apache2filter, apache2handler, caudium, cgi (until PHP 5.3), cgi-fcgi, cli, cli-server, continuity, embed, fpm-fcgi, isapi, litespeed, milter, nsapi, phpdbg, phttpd, pi3web, roxen, thttpd, tux, and webjames.
     *
     * Guzaba2 supports only cli, apache2handler, cgi-fcgi and a custom one - swoole
     * swoole will be returned if there is a \Swoole\Server started - until then it returns cli
     * @see self::get_php_sapi_name()
     * cgi-fcgi needs special detection as sometimes php_sapi_name() may return cli
     * The SAPI constants only list the supported APIs by Guzaba
     * There is a check in @see self::initialize() for the SAPI.
     */
    public const SAPI = [
        'APACHE'        => 'apache2handler',//mod_apache
        'CLI'           => 'cli',
        'CGI'           => 'cgi-fcgi',
        'SWOOLE'        => 'swoole',
    ];

    private function __construct()
    {
    }

    //////////////////////
    /// PUBLIC METHODS ///
    //////////////////////

    public static function set_init_microtime(float $microtime): void
    {
        self::$init_microtime = $microtime;
    }

    /**
     * @param RegistryInterface $Registry
     * @param LoggerInterface $Logger
     */
    public static function initialize(RegistryInterface $Registry, LoggerInterface $Logger, array $options = []): void
    {

        //first and foremost check is the current SAPI supported
        $sapi = self::get_php_sapi_name();
        if (!in_array($sapi, self::SAPI)) {
            throw new \Exception(sprintf('Guzaba2 does not support the %s SAPI.', $sapi));
        }

        if (self::$init_microtime === null) {
            self::$init_microtime = microtime(true);
        }

        self::$Registry = $Registry;
        self::$Logger = $Logger;

        self::$cwd = getcwd();

        self::$kernel_dir = dirname(__FILE__);

        self::$framework_root_dir = realpath(self::$kernel_dir . '/../../');

        self::register_autoloader_path(self::FRAMEWORK_NAME, self::$framework_root_dir);


        spl_autoload_register([__CLASS__, 'autoloader'], true, true);//prepend before Composer's autoloader
        set_exception_handler([__CLASS__, 'exception_handler']);
        set_error_handler([__CLASS__, 'error_handler']);
        register_shutdown_function([__CLASS__,'fatal_error_handler']);

        $source_stream_options = $options[SourceStream::class] ?? [];

        SourceStream::initialize($source_stream_options);

        stream_wrapper_register(SourceStream::PROTOCOL, SourceStream::class);

        self::$is_initialized_flag = true;

        self::print_initialization_messages();
    }


    /**
     * @param callable $callable
     * @param array $options
     * @return int
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public static function run(callable $callable, array $options = []): int
    {
        if (!self::is_initialized()) {
            throw new \Exception(t::_('Kernel is not initialized. Please execute Kernel::initialize() first.'));
        }

        self::printk(t::_('Kernel::run() invoked') . PHP_EOL);
        self::printk(PHP_EOL);

        $ret = 0;
        try {
            //due to concurrency issues it is better to load all classes used by the application and the framework before the server is started
            $validated_options = [];
//        foreach ($options as $option_name => $option_value) {
//            if (!array_key_exists($option_name,self::OPTIONS_DEFAULTS)) {
//                throw new \InvalidArgumentException(sprintf('An invalid option %s was provided to %s.', $option_name, __METHOD__));
//            }
//        }
            $run_options_validation = [];
            foreach (self::RUN_OPTIONS_DEFAULTS as $option => $value) {
                $run_options_validation[$option] = gettype($value);
            }
            ArrayUtil::validate_array($options, $run_options_validation, $errors);
            if ($errors) {
                throw new \InvalidArgumentException(sprintf(t::_('Kernel options error: %s'), implode(' ', $errors)));
            }

            $options += self::RUN_OPTIONS_DEFAULTS;

            //it is really a bad idea to skip the class load
            //if (!$options['disable_all_class_load']) {
            self::load_all_classes();
            $loaded_classes_info = t::_('Loaded all classes from:') . PHP_EOL;
            foreach (Kernel::get_registered_autoloader_paths() as $ns_prefix => $fs_path) {
                $loaded_classes_info .= str_repeat(' ', 4) . '- ' . $fs_path . PHP_EOL;
            }
            self::printk($loaded_classes_info);
            self::printk(PHP_EOL);
            //}

            $initialization_classes = self::run_all_initializations();
            $initializations_info = t::_('Initializations run:') . PHP_EOL;
            foreach ($initialization_classes as $initialization_class => $initialization_methods) {
                $initializations_info .= str_repeat(' ', 4) . '- ' . $initialization_class . PHP_EOL;
                foreach ($initialization_methods as $initialization_method) {
                    $initializations_info .= str_repeat(' ', 8) . '- ' . $initialization_method . '()' . PHP_EOL;
                }
            }
            self::printk($initializations_info);
            self::printk(PHP_EOL);

            if (!$options['disable_all_class_validation']) {
                $validation_classes = self::run_all_validations();
                $validations_info = t::_('Validations run:') . PHP_EOL;
                foreach ($validation_classes as $validation_class => $validation_methods) {
                    $validations_info .= str_repeat(' ', 4) . '- ' . $validation_class . PHP_EOL;
                    foreach ($validation_methods as $validation_method) {
                        $validations_info .= str_repeat(' ', 8) . '- ' . $validation_method . '()' . PHP_EOL;
                    }
                }
                self::printk($validations_info);
                self::printk(PHP_EOL);
            }


            $ret = $callable();

            if (!is_int($ret)) {
                $ret = self::EXIT_SUCCESS;
            }
        } catch (\Throwable $Exception) {
            self::exception_handler($Exception);
            $ret = 1;
        }

        return $ret;
    }

    /**
     * @param ContainerInterface $Container
     */
    public static function set_di_container(ContainerInterface $Container): void
    {
        self::$Container = $Container;
        $Container->initialize();
        //self::printk(sprintf('All global services are initialized.').PHP_EOL);
    }

    public static function get_di_container(): ?ContainerInterface
    {
        return self::$Container;
    }

    /**
     * @param Watchdog $Watchdog
     */
    public static function set_watchdog(Watchdog $Watchdog): void
    {
        self::$Watchdog = $Watchdog;
    }

    /**
     * @param Server $HttpServer
     */
    public static function set_http_server(Server $HttpServer): void
    {
        self::$HttpServer = $HttpServer;
    }

    /**
     * @return Server|null
     */
    public static function get_http_server(): ?Server
    {
        return self::$HttpServer;
    }

    /**
     * Returns -1 the code is not executed in worker context
     * @return int
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     */
    public static function get_worker_id(): int
    {
//Returns -1 if there is Server set but it is not yet started (meaning not executing in worker).
//        if (!isset(self::$HttpServer)) {
//            throw new RunTimeException(sprintf(t::_('No Http Server is set (Kernel::set_http_server()).')));
//        }
//        return self::$HttpServer->get_worker_id();
        $worker_id = -1;
        if (isset(self::$HttpServer)) {
            $worker_id = self::$HttpServer->get_worker_id();
        }
        return $worker_id;
    }

    /**
     * Corresponds to \php_sapi_name() but with some additional checks and also one additional type - "swoole".
     * "swoole" is returned if there is \Swoole\Server started.
     * @return string The name of the SAPI as per php_sapi_name()
     */
    public static function get_php_sapi_name(): string
    {

        if (self::get_http_server() instanceof \Guzaba2\Swoole\Server) {
            return 'swoole';
        }
        //TODO - add additional checks to detect cgi - sometimes it may return cli instead of cgi-fcgi
        return php_sapi_name();
    }

    /**
     * @param string $id
     * @return object
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     */
    public static function get_service(string $id): object
    {
        if (!isset(self::$Container)) {
            throw new RunTimeException(sprintf(t::_('There is no Dependency Injection container set (Kernel::set_di_container()). The services are not available.')));
        }
        return self::$Container->get($id);
    }

    /**
     * Whether the provided service exists.
     * @param string $id
     * @return bool
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     */
    public static function has_service(string $id): bool
    {
        if (!isset(self::$Container)) {
            throw new RunTimeException(sprintf(t::_('There is no Dependency Injection container set (Kernel::set_di_container()). The services are not available.')));
        }
        return self::$Container->has($id);
    }

//    /**
//     * @param string $id
//     * @return bool
//     * @throws RunTimeException
//     */
//    public static function is_service_instantiated(string $id) : bool
//    {
//        if (!isset(self::$Container)) {
//            throw new RunTimeException(sprintf(t::_('There is no Dependency Injection container set (Kernel::set_di_container()). The services are not available.')));
//        }
//        return self::$Container->is_dependency_instantiated($id);
//    }

    /**
     * @return bool
     */
    public static function is_initialized(): bool
    {
        return self::$is_initialized_flag;
    }

    public static function get_init_microtime(): float
    {
        return self::$init_microtime;
    }

    /**
     * @return LoggerInterface
     */
    public static function get_logger(): LoggerInterface
    {
        return self::$Logger;
    }

    public static function get_main_log_file(): ?string
    {
        $ret = null;
        $handlers = self::get_logger()->getHandlers();
        foreach ($handlers as $Handler) {
            if ($Handler instanceof StreamHandler) {
                $url = $Handler->getUrl();

                if ($url && $url[0] === '/') { //skip php://output
                    $ret = $url;
                    break;
                }
            }
        }
        return $ret;
    }

    /**
     * Returns the log level as string as per LogLevel
     * @return string
     */
    public static function get_log_level(): ?string
    {
        $ret = null;
        $Logger = self::get_logger();
        $handlers = $Logger->getHandlers();
        foreach ($handlers as $Handler) {
            if ($Handler instanceof StreamHandler) {
                $url = $Handler->getUrl();

                if ($url && $url[0] === '/') { //skip php://output
                    $ret = $Logger::getLevelName($Handler->getLevel());
                    break;
                }
            }
        }
        return $ret;
    }

    public static function get_main_log_file_handler(): ?StreamHandler
    {
        $ret = null;
        $Logger = self::get_logger();
        $handlers = $Logger->getHandlers();
        foreach ($handlers as $Handler) {
            if ($Handler instanceof StreamHandler) {
                $url = $Handler->getUrl();

                if ($url && $url[0] === '/') { //skip php://output
                    $ret = $Handler;
                    break;
                }
            }
        }
        return $ret;
    }

    public static function dump(/* mixed */ $var): void
    {
        $frame = StackTraceUtil::get_stack_frame(2);
        $str = '';
        $str = var_dump($var, true);
        if ($frame) {
            //$str .= 'printed in '.$frame['file'].'#'.$frame['line'].PHP_EOL;
            $str .= sprintf(t::_('printed in %s#%s'), $frame['file'], $frame['line']) . PHP_EOL;
        }
        $str .= PHP_EOL;
        print $str;
    }


    /**
     * Terminates the execution and prints the provided message
     * @param string $message
     */
    public static function stop(string $message, int $exit_code = self::EXIT_GENERAL_ERROR): void
    {
        print $message . PHP_EOL;
        die($exit_code);//in swoole context in worker / coroutine this will throw Swoole\ExitException
    }

    /**
     * Exception handler does not work in Swoole worker context so everything in the request is in try/catch \Throwable and a manual call to the exception handler
     * It works outside swoole context so it is still explicitly registered in Kernel::initialize()
     * @param \Throwable $Exception
     * @param NULL|int $exit_code If int exit code is provided this will terminate the program/worker
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public static function exception_handler(\Throwable $Exception, string $log_level = LogLevel::EMERGENCY, ?int $exit_code = null): void
    {
        //if we reaching this this request/coroutine cant proceed and all own locks should be released
        //then disable the locking for this coroutine
        //self::$Container->get('LockManager')->release_all_own_locks();
        //if the below is not invoked on Swoole 4.4.12 / PHP 7.4.0 there is an error on the line $ret = $Context->orm_locking_enabled_flag ?? self::$orm_locking_enabled_flag
        //ActiveRecord::disable_locking();

//        $output = '';
//        do {
//
//            //$output .= sprintf(t::_('Exception %s: %s in %s#%s'), get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine());
//            //should not depend on the translator (t::_()) or any other code that is additionally autoloaded
//            $output .= sprintf('Exception %s: %s in %s#%s', get_class($Exception), $Exception->getMessage(), $Exception->getFile(), $Exception->getLine());
//            $output .= PHP_EOL;
//            $output .= $Exception->getTraceAsString().PHP_EOL.PHP_EOL;
//            $Exception = $Exception->getPrevious();
//        } while ($Exception);


        $output = (string) $Exception;

        self::log($output, $log_level);

        if ($exit_code !== null) {
            die($exit_code);
        } else {
            //when NULL is provided just print the message but do not exit
            //this is to be used in Server context - no point to kill the worker along with the rest of the coroutines
            if (self::get_http_server() instanceof \Guzaba2\Swoole\Server) {
                //TODO - check is the server actually started or just defined
                //if it is started do not exit
            } else {
                //outside swoole context - exit
                die((string) $Exception);
            }
        }
    }

    /**
     * Error handler works even in Swoole worker context.
     * Converts all notices/errors/warnings to ErrorException.
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @param array $errcontext
     * @throws Exceptions\ErrorException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public static function error_handler(int $errno, string $errstr, string $errfile, int $errline, array $errcontext = []): void
    {
        //throw new \Guzaba2\Kernel\Exceptions\ErrorException($errno, $errstr, $errfile, $errline, $errcontext);
        //self::exception_handler(new \Guzaba2\Kernel\Exceptions\ErrorException($errno, $errstr, $errfile, $errline, $errcontext));
        //must throw the exception instead of passing it directly to the exception handler as in Server mode the exception handler does not interrupt the execution

        throw new \Guzaba2\Kernel\Exceptions\ErrorException($errno, $errstr, $errfile, $errline, $errcontext);
    }

    /**
     * Used to catch uncaught exceptions that cause worker restart and pass them to the error/exception handler of the Kernel.
     * This is called only at worker shutdown.
     * There are no coroutines in this phase.
     * @throws Exceptions\ErrorException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public static function fatal_error_handler(): void
    {
        $Server = self::get_http_server();
        if ($Server) {
            self::printk(sprintf(t::_('Worker %1$s shutdown'), $Server->get_worker_id()));
        } else {
            self::printk(sprintf(t::_('Main process shutdown')));
        }

        $error = error_get_last();
        if ($error) {
            self::error_handler($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }

    //not used
    public static function logtofile(string $content, array $context = []): void
    {
        self::$Logger->debug($content, $context);
    }

    public static function bt(array $context = []): void
    {
        $bt = print_r(Coroutine::getSimpleBacktrace(), true);
        self::$Logger->debug($bt, $context);
    }

    /**
     * Prints a message to the default output of the server (in daemon mode this is redirected to file).
     * It can be used even if the Kernel is not initialized
     * @param string $message
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public static function printk(string $message): void
    {
        //TODO - would be nice if this can also print to any connected debugger session...
        //better - instead there can be Console sessions attached for these messages (which is different from debug session)
        if (self::$init_microtime === null) {
            self::$init_microtime = microtime(true);
        }

        if (trim($message)) {
            $microtime = microtime(true);
            $microtime_diff = round($microtime - self::$init_microtime, self::MICROTIME_ROUNDING);
            if (self::get_http_server()) {
                $worker_id = self::get_worker_id();
                if ($worker_id != -1) {
                    //$worker_str = ' Worker #'.$worker_id;
                    $worker_str = ' ' . sprintf(t::_('Worker #%s'), $worker_id);
                } else {
                    $worker_str = t::_('Startup');
                }
            } else {
                $worker_str = t::_('Startup');
            }
            // todo fix padding
            $message = sprintf('[%.4f %s] %s', $microtime_diff, $worker_str, $message);
        }

        //if the kernel is not yet initialized and the logger is not available
        //store the messages that are not logged and flush them with the first printk call in which the kernel is initialized
        static $messages_buffer_arr = [];

        if (self::is_initialized()) {
            //first flush the buffer
            foreach ($messages_buffer_arr as $buffered_message) {
                self::write_to_log($buffered_message);
                $messages_buffer_arr = [];
            }
            self::write_to_log($message);
        } else {
            $messages_buffer_arr[] = $message;
        }

        //if (self::get_php_sapi_name() === self::SAPI['SWOOLE']) {
        if (in_array(self::get_php_sapi_name(), [self::SAPI['SWOOLE'], self::SAPI['CLI']])) {
            print $message;
        }

    }

    /**
     * Checks the logger for any handlers that start with / and pushes the provided $message to these.
     * Returns the number of handlers to which the message was pushed to.
     * @param string $message
     * @return int
     */
    private static function write_to_log(string $message): int
    {
        $ret = 0;
        $handlers = self::get_logger()->getHandlers();
        foreach ($handlers as $Handler) {
            if ($Handler instanceof StreamHandler) {
                $url = $Handler->getUrl();

                if ($url && $url[0] === '/') { //skip php://output
                    self::file_put_contents($url, $message, \FILE_APPEND);
                    $ret++;
                }
            }
        }
        return $ret;
    }

    /**
     * Must be used instead of \file_put_contents() as this method is coroutine aware.
     * If running in coroutine context it will use \Swoole\Coroutine\System::writeFile() instead.
     * @param string $file
     * @param string $contents
     * @param int $flags
     * @return int
     */
    public static function file_put_contents(string $file, string $contents, int $flags = 0): int
    {
        if (self::get_cid() > 0) {
            $ret = \Swoole\Coroutine\System::writeFile($file, $contents, $flags);
        } else {
            $ret = file_put_contents($file, $contents, $flags);
        }
        return $ret;
    }

    /**
     * @param string $file
     * @return string
     */
    public static function file_get_contents(string $file): string
    {
        if (self::get_cid() > 0) {
            $ret = \Swoole\Coroutine\System::readFile($file);
        } else {
            $ret = file_get_contents($file);
        }
        return $ret;
    }

    /**
     * Registers a new namespace base that is to be looked up.
     * To be used if the application needs to use the Guzaba2 autoloader
     * @param string $namespace_base
     * @param string $base_path
     */
    public static function register_autoloader_path(string $namespace_base, string $base_path): void
    {
        self::$autoloader_lookup_paths[$namespace_base] = $base_path;
    }

    /**
     * Returns the registered for autoloading namespaces and their respective paths
     * @return array An associative array containing namespace prefix as key and lookup path as value.
     */
    public static function get_registered_autoloader_paths(): array
    {
        return self::$autoloader_lookup_paths;
    }

    /**
     * Returns the Component class based on a provided class
     * @param string $class
     * @return string|null
     */
    public static function get_component_by_class(string $class): ?string
    {
        $ret = null;
        foreach (Kernel::get_registered_autoloader_paths() as $ns_prefix => $path) {
            if (strpos($class, $ns_prefix) === 0) {
                $ret = $ns_prefix . '\\Component';
                break 1;
            }
        }
        return $ret;
    }

    public static function namespace_base_is_registered(string $namespace_base): bool
    {
        return array_key_exists($namespace_base, self::$autoloader_lookup_paths);
    }

    /**
     * @param string $path
     * @param string|null $error
     * @return bool
     */
    public static function check_syntax(string $path, ?string &$error = null): bool
    {
        exec("php -l {$path} 2>&1", $output, $return);
        $error = $output[0];
        return $return ? true : false;
//        $ret = FALSE;
//        $output = \shell_exec("php -l {$path} 2>&1");
//        if (strpos($output, 'No syntax errors detected') === FALSE) {
//            $ret = TRUE;
//        }
//        return $ret;
    }

    public static function get_runtime_configuration(string $class_name): array
    {
        $runtime_config = [];

        try {
            $RClass = new ReflectionClass($class_name);
        } catch (\ReflectionException $Exception) {
            $message = sprintf(t::_('[DEBUG] Dumping registered autoloader paths because of %s exception due autoloading:'), get_class($Exception));
            $paths = self::get_registered_autoloader_paths();
            foreach ($paths as $reg_ns => $reg_path) {
                $message .= PHP_EOL . ' - ' . $reg_ns . ': ' . $reg_path;
            }
            $message .= PHP_EOL;
            self::printk($message);
            throw $Exception;
        }

        if ($RClass->implementsInterface(ConfigInterface::class)) {
            //if ($RClass->hasOwnConstant('CONFIG_DEFAULTS') && $RClass->hasOwnStaticProperty('CONFIG_RUNTIME')) {
            if ($RClass->hasOwnConstant('CONFIG_DEFAULTS') && $RClass->hasOwnConstant('CONFIG_RUNTIME')) {
                $default_config = (new \ReflectionClassConstant($class_name, 'CONFIG_DEFAULTS'))->getValue();

                $runtime_config = $default_config;

                //get the configuration from the parent class
                $RParentClass = $RClass->getParentClass();
                $parent_config = [];
                if ($RParentClass) {
                    $parent_class_name = $RParentClass->name;
                    if ($RParentClass->implementsInterface(ConfigInterface::class)) {
                        $parent_config = $parent_class_name::get_runtime_configuration();
                    }
                }

                $runtime_config += $parent_config;//the parent config does not overwrite the current config
                //there is exception though for the 'services' key - this needs to be merged
                if (isset($parent_config['services'])) {
                    if (!isset($runtime_config['services'])) {
                        $runtime_config['services'] = [];
                    }
                    foreach ($parent_config['services'] as $service_name) {
                        if (!in_array($service_name, $runtime_config['services'])) {
                            $runtime_config['services'][] = $service_name;
                        }
                    }
                }

                //get configuration from the registry
                //only variables defined in CONFIG_DEFAULTS will be imported from the Registry
                $real_class_name = str_replace('_without_config', '', $class_name);

                self::$Registry->add_to_runtime_config_file($real_class_name, "\n==============================\nClass: {$real_class_name}\n");

                $registry_config = self::$Registry->get_class_config_values($real_class_name);

                foreach ($default_config as $key_name => $key_value) {
                    if (array_key_exists($key_name, $registry_config)) {
                        if (is_array($key_value)) {
                            $runtime_config[$key_name] = array_replace_recursive($key_value, $registry_config[$key_name]);
                        } else {
                            $runtime_config[$key_name] = $registry_config[$key_name];
                        }
                    }
                    //check also if there any any prefix in the var name that matches a prefix in the config array
                    if (is_array($key_value)) {
                        $WalkArrays = function ($registry_config) use (&$WalkArrays, $key_name) {
                            foreach ($registry_config as $reg_key_name => $reg_key_value) {
                                if (is_array($reg_key_value)) {
                                    $WalkArrays($reg_key_value);
                                } else {
                                    //look for $key_name as part of a var name
                                    //assuming _ as separator between the array
                                    if (strpos($reg_key_name, $key_name) === 0) {
                                        $runtime_config[$key_name][str_replace($key_name . '_', '', $reg_key_name)] = $reg_key_value;
                                    }
                                }
                            }
                        };
                    }
                }



                self::$Registry->add_to_runtime_config_file($real_class_name, "\nFINAL CONFIG_RUNTIME for {$real_class_name}:\n" . print_r($runtime_config, true));
                // the word FINAL is required here as it announces for final write in the file, when "return" is added
                self::$Registry->add_to_runtime_files($real_class_name, $runtime_config, "FINAL CONFIG_RUNTIME");
            } else {
                //this class is not defining config values - will have access to the parent::CONFIG_RUNTIME
            }
        } else {
            //do nothing - does not require configuration
        }
        return $runtime_config;
    }

    public static function get_class_structure(): array
    {
        return self::$class_structure;
    }

    public static function get_class_all_children(string $class_name): array
    {
        $children = [];
        $Function = static function ($class_name) use (&$Function, &$children) {
            $class_children = self::$class_structure[$class_name]['children'];
            foreach ($class_children as $class_child) {
                $children[] = $class_child['name'];
                $Function($class_child['name']);
            }
        };
        $Function($class_name);
        return $children;
    }

    public static function get_class_children(string $class): array
    {
        $children = [];
        foreach (self::$class_structure[$class]['children'] as $class_child) {
            $children[] = $class_child['name'];
        }
        return $children;
    }

    /**
     * @param string $class
     * @return string|null
     */
    public static function get_class_parent(string $class): ?string
    {
        return isset(self::$class_structure[$class]['parent']) ? self::$class_structure[$class]['parent']['name'] : null;
    }

    /**
     * @param string $class
     * @return array
     */
    public static function get_class_all_parents(string $class): array
    {
        $ret = [];
        do {
            $parent_class = self::$class_structure[$class]['parent'];
            if (!$parent_class) {
                break;
            }
            $ret[] = $parent_class['name'];
            $class = $parent_class['name'];
        } while (true);
        return $ret;
    }


    /**
     * Returns an associative array with path=>class that match the provided $ns_prefixes and (if provided) are of class/interface $class.
     * @param array $ns_prefixes
     * @param string $class
     * @return array
     * @throws InvalidArgumentException
     */
    public static function get_classes(array $ns_prefixes = [], string $class = ''): array
    {
        $ret = [];
        if (!$ns_prefixes) {
            $ns_prefixes = array_keys(self::get_registered_autoloader_paths());
        }
        if ($class && !class_exists($class) && !interface_exists($class)) {
            throw new InvalidArgumentException(sprintf('Class/interface %1$s does not exist.', $class));
        }
        $loaded_classes = Kernel::get_loaded_classes();

        foreach ($ns_prefixes as $ns_prefix) {
            foreach ($loaded_classes as $class_path => $loaded_class) {
                if (strpos($loaded_class, '_without_config') !== false) {
                    continue;
                }
                if (strpos($loaded_class, $ns_prefix) === 0) {
                    if ($class) {
                        if (is_a($loaded_class, $class, true)) {
                            if ($loaded_class === $class) {
                                continue;
                            }
                            $ret[$class_path] = $loaded_class;
                        }
                    } else {
                        $ret[$class_path] = $loaded_class;
                    }
                }
            }
        }
        return $ret;
    }

    /**
     * Loads all classes found under the registered autoload paths.
     * @see self::$autoloader_lookup_paths
     * @see self::register_autoloader_path()
     * @return void
     */
    public static function load_all_classes(): void
    {
        foreach (self::$autoloader_lookup_paths as $namespace_base => $autoload_lookup_path) {
            $Directory = new \RecursiveDirectoryIterator($autoload_lookup_path);
            $Iterator = new \RecursiveIteratorIterator($Directory);
            $Regex = new \RegexIterator($Iterator, '/^.+\.php$/i', \RegexIterator::GET_MATCH);
            foreach ($Regex as $path => $match) {
                $class_name = str_replace($autoload_lookup_path, '', $path);
                $class_name = str_replace('\\\\', '\\', $class_name);
                $class_name = str_replace('/', '\\', $class_name);
                $class_name = str_replace('\\\\', '\\', $class_name);
                $class_name = str_replace($namespace_base, '', $class_name);//some may contain it
                $class_name = str_replace('\\\\', '\\', $class_name);
                $class_name = $namespace_base . '\\' . $class_name;
                $class_name = str_replace('\\\\', '\\', $class_name);
                $class_name = str_replace('.php', '', $class_name);

                //we also need to check again already included files
                //as including a certain file may trigger the autoload and load other classes that will be included a little later
                $included_files = get_included_files();
                if (in_array($path, $included_files) || in_array(SourceStream::PROTOCOL . '://' . $path, $included_files)) {
                    //skip this file - it is already included
                    continue;
                }
                class_exists($class_name);//this will trigger the autoloader if the class doesnt already exist
                //self::autoloader($class_name);//an explicit call will trigger an error if the class is already loaded
            }
        }
    }

    /**
     * Returns a two-dimensional associative array: class_name=>['method1','method2']
     * @return array
     */
    public static function run_all_validations(): array
    {
        $validation_classes = [];
        foreach (self::$loaded_classes as $loaded_class) {
            if (
                is_a($loaded_class, ClassDeclarationValidationInterface::class, true)
                && $loaded_class !== ClassDeclarationValidationInterface::class
            ) {
                $methods_run = $loaded_class::run_all_validations();
                $validation_classes[$loaded_class] = $methods_run;
            }
        }
        return $validation_classes;
    }

    public static function run_all_initializations(): array
    {
        $initialization_classes = [];
        foreach (self::$loaded_classes as $loaded_class) {
            if (
                is_a($loaded_class, ClassInitializationInterface::class, true)
                && $loaded_class !== ClassInitializationInterface::class
            ) {
                $methods_run = $loaded_class::run_all_initializations();
                $initialization_classes[$loaded_class] = $methods_run;
            }
        }
        return $initialization_classes;
    }

    /**
     * Returns the classes loaded through Kernel::autoload().
     * Returns an associative array with $class_path=>$class_name
     * @return array
     */
    public static function get_loaded_classes(): array
    {
        return self::$loaded_classes;
    }

    /**
     * Returns the path of the provided class if the class was loaded through Kernel::autoload().
     * Otherwise returns NULL.
     * @param string $class_name
     * @return string|null
     * @throws InvalidArgumentException
     */
    public static function get_class_path(string $class_name): ?string
    {
        if ($class_name && !class_exists($class_name) && !interface_exists($class_name)) {
            throw new InvalidArgumentException(sprintf('Class/interface %s does not exist.', $class_name));
        }
        $ret = array_search($class_name, self::$loaded_classes);
        if ($ret === false) {
            //throw new InvalidArgumentException(sprintf('The provided class %s is not loaded through the Kernel::autoload().', $class_name));
            $ret = null;
        }
        return $ret;
    }


    /**
     * Logs a message using the default logger
     *
     * @param string $message
     * @param string $level
     * @param array $context
     * @return bool
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public static function log(string $message, string $level = LogLevel::INFO, array $context = []): bool
    {
        $Logger = self::get_logger();
        if (self::get_cid() > 0) {
            $message = sprintf(t::_('Coroutine #%s: %s'), self::get_cid(), $message);
        }
        if (self::get_http_server()) {
            //$message = 'Worker #'.self::get_worker_id().': '.$message;
            $message = sprintf(t::_('Worker #%s: %s'), self::get_worker_id(), $message);
        }

        $Logger->log($level, $message, $context);
        return true;
    }

    /**
     * Returns the current coroutine if it is in swoole contex and there is a coroutine.
     * Otherwise returns -1.
     * @return int
     */
    public static function get_cid(): int
    {
        $cid = -1;
        if (extension_loaded('swoole')) {
            $cid = \Swoole\Coroutine::getCid();//this may also return -1
        }
        return $cid;
    }

    /////////////////////////
    /// PROTECTED METHODS ///
    /////////////////////////

    /**
     * Just prints initialization messages.
     */
    protected static function print_initialization_messages(): void
    {
        self::printk(PHP_EOL);
        self::printk(self::FRAMEWORK_BANNER);
        self::printk(PHP_EOL);

        Kernel::printk(sprintf(t::_('Initialization at: %s %s %s'), self::$init_microtime, date('Y-m-d H:i:s'), date_default_timezone_get()) . PHP_EOL);

        if (extension_loaded('swoole')) {
            Kernel::printk(sprintf(t::_('Versions: PHP %s, Swoole %s, Guzaba %s') . PHP_EOL, PHP_VERSION, SWOOLE_VERSION, Kernel::FRAMEWORK_VERSION));
        } elseif (self::get_php_sapi_name() === self::SAPI['APACHE']) {
            Kernel::printk(sprintf(t::_('Versions: PHP %s, Apache %s, Guzaba %s') . PHP_EOL, PHP_VERSION, apache_get_version(), Kernel::FRAMEWORK_VERSION));
        } else {
            Kernel::printk(sprintf(t::_('Versions: PHP %s, Guzaba %s') . PHP_EOL, PHP_VERSION, Kernel::FRAMEWORK_VERSION));
        }

        Kernel::printk(SysUtil::get_basic_sysinfo() . PHP_EOL);

        self::printk(PHP_EOL);

        $registry_backends = self::$Registry->get_backends();
        $registry_str = t::_('Registry backends:') . PHP_EOL;
        foreach ($registry_backends as $RegistryBackend) {
            $registry_str .= str_repeat(' ', 4) . '- ' . get_class($RegistryBackend) . PHP_EOL;
        }
        self::printk($registry_str);
        self::printk(PHP_EOL);


        $handlers = self::$Logger->getHandlers();
        $error_handlers_str = t::_('Logger Handlers:') . PHP_EOL;
        foreach ($handlers as $Handler) {
            $error_handlers_str .= str_repeat(' ', 4) . '- ' . get_class($Handler) . ' : ' . $Handler->getUrl() . ' : ' . self::$Logger::getLevelName($Handler->getLevel()) . PHP_EOL;
        }
        Kernel::printk($error_handlers_str);
        self::printk(PHP_EOL);
        self::printk(t::_('Kernel is initialized') . PHP_EOL);
        self::printk(PHP_EOL);
    }

    /**
     * @param string $class_name
     * @return bool
     * @throws Exceptions\AutoloadException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    protected static function autoloader(string $class_name): bool
    {

        $ret = false;

        foreach (self::$autoloader_lookup_paths as $namespace_base => $lookup_path) {
            //needed because swoole is not available on windows and CI may run on windows.
            //if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN' && strpos($class_name, 'ReplacementClasses')) {
            if (extension_loaded('swoole') && strpos($class_name, 'Swoole\\ReplacementClasses') !== FALSE) {
                continue;
            }

            if (strpos($class_name, $namespace_base) === 0) {
                if ($namespace_base === self::FRAMEWORK_NAME) {
                    $formed_class_path = $lookup_path . '/' . str_replace('\\', '/', $class_name) . '.php';
                    $class_path = realpath($formed_class_path);
                } else {
                    $formed_class_path = $lookup_path . '/' . str_replace('\\', '/', str_replace($namespace_base, '', $class_name)) . '.php';
                    $class_path = realpath($formed_class_path);
                }

                if ($class_path && is_readable($class_path)) {
                    self::require_class($class_path, $class_name);
                    //the file may exist but it may not contain the needed file
                    if (strpos($class_name, 'Swoole\\ReplacementClasses') !== FALSE) { //do not check these classes
                        //it is good to set the correct class name as this is stored in loaded classes
                        $class_name = substr($class_name, strrpos($class_name, '\\') + 1);
                        $class_name = str_replace('_', '\\', $class_name);
                    } else {
                        if (!class_exists($class_name) && !interface_exists($class_name) && !trait_exists($class_name)) {

                            if (!GeneralUtil::check_syntax($class_path, $syntax_error)) {
                                $message = $syntax_error;
                                if (class_exists(\Guzaba2\Kernel\Exceptions\AutoloadException::class)) {
                                    throw new \Guzaba2\Kernel\Exceptions\AutoloadException($message);
                                } else {
                                    throw new \Exception($message);
                                }
                            } else {
                                $message = sprintf('The file %s is readable but does not contain the class/interface/trait %s. Please check the class and namespace declarations and is there a parent class that does not exist/can not be loaded.', $class_path, $class_name);
                                if (class_exists(\Guzaba2\Kernel\Exceptions\AutoloadException::class)) {
                                    throw new \Guzaba2\Kernel\Exceptions\AutoloadException($message);
                                } else {
                                    throw new \Exception($message);
                                }
                            }



                        }
                    }
                    self::initialize_class($class_name);
                    self::$loaded_classes[$class_path] = $class_name;
                    $ret = true;

                    $parent_class = get_parent_class($class_name);
                    if (!$parent_class) {
                        $parent_class = null;
                        self::$class_structure[$class_name] = ['name' => $class_name, 'parent' => $parent_class, 'children' => [] ];
                    } else {
                        self::$class_structure[$class_name] = ['name' => $class_name, 'parent' => &self::$class_structure[$parent_class], 'children' => [] ];
                    }

                    self::$class_structure[$parent_class]['children'][] =& self::$class_structure[$class_name];
                } else {
                    //$message = sprintf(t::_('Class %s (path %s) is not found (or not readable).'), $class_name, $class_path);
                    //$message = sprintf('Class %s (path %s) is not found (path does not exist or not readable).', $class_name, $class_path);
                    //$message = sprintf('Class %s (path %s) is not found (path does not exist or not readable).', $class_name, $formed_class_path);
                    //throw new \Guzaba2\Kernel\Exceptions\AutoloadException($message);
                    //self::exception_handler(new \Guzaba2\Kernel\Exceptions\AutoloadException($message));
                    //there are paths that are served by the Composer autoloader and are still part of the GuzabaPlatform... do not throw an exception here... leave the next autoloader
                }
            } else {
                //this autoloader can not serve this request - skip this class and leave to the next autoloader (probably Composer) to load it
            }
        }

        return $ret;
    }


    /**
     * @param string $class_path
     * @param string $class_name
     * @return mixed|null
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    protected static function require_class(string $class_path, string $class_name) /* mixed */
    {

        $ret = null;

        try {
            if (self::get_cid() > 0) {
                $class_source = \Swoole\Coroutine::readFile($class_path);
            } else {
                $class_source = file_get_contents($class_path);
            }

            //TODO - he below is a very primitive check - needs to be improved and use tokenizer
            if ($class_name != SourceStream::class && $class_name != self::class && strpos($class_source, 'protected const CONFIG_RUNTIME =') !== false) {
                //use stream instead of eval because of the error reporting - it becomes more obscure with eval()ed code
                $ret = require_once(SourceStream::PROTOCOL . '://' . $class_path);
            } else {
                $ret = require_once($class_path);
            }
        } catch (\Throwable $exception) {
            self::printk('ERROR IN CLASS GENERATION' . PHP_EOL);
            self::printk($exception->getMessage() . ' in file ' . $exception->getFile() . '#' . $exception->getLine() . PHP_EOL . $exception->getTraceAsString() . PHP_EOL);
        }



        return $ret;
    }

    /**
     * @param string $class_name
     * @throws \ReflectionException
     */
    protected static function initialize_class(string $class_name): void
    {

        $RClass = new ReflectionClass($class_name);

        if ($RClass->hasOwnMethod('_initialize_class')) {
            call_user_func([$class_name, '_initialize_class']);
        }
    }

    /**
     * @param string $file_name
     */
//    public static function get_namespace_declarations(string $file_name)
//    {
//        // TODO implement
//    }
//
//    public static function execute_in_worker($callable)
//    {
//    }
//
//    public static function execute_delayed($callable, ?TraceInfoObject $trace_info = NULL)
//    {
//    }
//
//    public static function execute_in_shutdown($callable, ?TraceInfoObject $trace_info = NULL)
//    {
//    }
//
//    /**
//     * @return bool
//     * @todo implement
//     */
//    public static function is_production()
//    {
//        return false;
//    }
}
