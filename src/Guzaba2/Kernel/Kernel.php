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
use Azonmedia\Utilities\StackTraceUtil;
use Azonmedia\Utilities\SysUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Base\Interfaces\ConfigInterface;
use Guzaba2\Base\TraceInfoObject;
use Guzaba2\Base\Traits\SupportsConfig;
use Guzaba2\Coroutine\Coroutine;
//use Guzaba2\Database\Connection;
use Guzaba2\Http\Server;
use Guzaba2\Kernel\Exceptions\ConfigurationException;
use Guzaba2\Kernel\Interfaces\ClassInitializationInterface;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Authorization\IpBlackList;
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
        'disable_all_class_load'            => FALSE,
        'disable_all_class_validation'      => FALSE,
    ];

    public const EXIT_SUCCESS = 0;

    public const EXIT_GENERAL_ERROR = 1;

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
     * @var array
     */
    protected static array $loaded_paths = [];

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
    protected static bool $is_initialized_flag = FALSE;

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
    protected static ?Server $HttpServer = NULL;

    /**
     * @var Watchdog
     */
    public static Watchdog $Watchdog;

    private static float $init_microtime;

    private function __construct()
    {
    }

    //////////////////////
    /// PUBLIC METHODS ///
    //////////////////////

    /**
     * @param RegistryInterface $Registry
     * @param LoggerInterface $Logger
     */
    public static function initialize(RegistryInterface $Registry, LoggerInterface $Logger): void
    {

        self::$init_microtime = microtime(TRUE);

        self::$Registry = $Registry;
        self::$Logger = $Logger;

        self::$cwd = getcwd();

        self::$kernel_dir = dirname(__FILE__);

        self::$framework_root_dir = realpath(self::$kernel_dir . '/../../');

        self::register_autoloader_path(self::FRAMEWORK_NAME, self::$framework_root_dir);


        spl_autoload_register([__CLASS__, 'autoloader'], TRUE, TRUE);//prepend before Composer's autoloader
        set_exception_handler([__CLASS__, 'exception_handler']);
        set_error_handler([__CLASS__, 'error_handler']);

        //stream_wrapper_register('guzaba.source', SourceStream::class);
        stream_wrapper_register(SourceStream::PROTOCOL, SourceStream::class);

        self::print_initialization_messages();

        self::$is_initialized_flag = TRUE;
    }


    /**
     * @param callable $callable
     * @return int
     * @throws \Exception
     */
    public static function run(callable $callable, array $options = []) : int
    {
        if (!self::is_initialized()) {
            throw new \Exception('Kernel is not initialized. Please execute Kernel::initialize() first.');
        }

        //due to concurrency issues it is better to load all classes used by the application and the framework before the server is started
        $validated_options = [];
//        foreach ($options as $option_name => $option_value) {
//            if (!array_key_exists($option_name,self::OPTIONS_DEFAULTS)) {
//                throw new \InvalidArgumentException(sprintf('An invalid option %s was provided to %s.', $option_name, __METHOD__));
//            }
//        }
        $run_options_validation = [];
        foreach (self::RUN_OPTIONS_DEFAULTS as $option=>$value) {
            $run_options_validation[$option] = gettype($value);
        }
        ArrayUtil::validate_array($options, $run_options_validation, $errors);
        if ($errors) {
            throw new \InvalidArgumentException(sprintf('Kernel options error: %s', implode(' ', $errors) ));
        }

        $options += self::RUN_OPTIONS_DEFAULTS;

        //it is really a bad idea to skip the class load
        //if (!$options['disable_all_class_load']) {
            self::load_all_classes();
            $loaded_classes_info = t::_('Loaded all classes from:').PHP_EOL;
            foreach (Kernel::get_registered_autoloader_paths() as $ns_prefix => $fs_path) {
                $loaded_classes_info .= str_repeat(' ',4).'- '.$fs_path.PHP_EOL;
            }
            self::printk($loaded_classes_info);
            self::printk(PHP_EOL);
        //}

        $initialization_classes = self::run_all_initializations();
        $initializations_info = t::_('Initializations run:').PHP_EOL;
        foreach ($initialization_classes as $initialization_class => $initialization_methods) {
            $initializations_info .= str_repeat(' ',4).'- '.$initialization_class.PHP_EOL;
            foreach ($initialization_methods as $initialization_method) {
                $initializations_info .= str_repeat(' ',8).'- '.$initialization_method.'()'.PHP_EOL;
            }
        }
        self::printk($initializations_info);
        self::printk(PHP_EOL);

        if (!$options['disable_all_class_validation']) {
            $validation_classes = self::run_all_validations();
            $validations_info = t::_('Validations run:').PHP_EOL;
            foreach ($validation_classes as $validation_class => $validation_methods) {
                $validations_info .= str_repeat(' ',4).'- '.$validation_class.PHP_EOL;
                foreach ($validation_methods as $validation_method) {
                    $validations_info .= str_repeat(' ',8).'- '.$validation_method.'()'.PHP_EOL;
                }
            }
            self::printk($validations_info);
            self::printk(PHP_EOL);
        }


        $ret = $callable();

        if (!is_int($ret)) {
            $ret = self::EXIT_SUCCESS;
        }
        return $ret;
    }

    /**
     * @param ContainerInterface $Container
     */
    public static function set_di_container(ContainerInterface $Container) : void
    {
        self::$Container = $Container;
    }

    /**
     * @param Watchdog $Watchdog
     */
    public static function set_watchdog(Watchdog $Watchdog) : void
    {
        self::$Watchdog = $Watchdog;
    }

    /**
     * @param Server $HttpServer
     */
    public static function set_http_server(Server $HttpServer) : void
    {
        self::$HttpServer = $HttpServer;
    }

    /**
     * @return Server|null
     */
    public static function get_http_server() : ?Server
    {
        return self::$HttpServer;
    }

    /**
     * Returns -1 if there is Server set but it is not yet started (meaning not executing in worker).
     * @return int
     * @throws RunTimeException
     */
    public static function get_worker_id() : int
    {
        if (!isset(self::$HttpServer)) {
            throw new RunTimeException(sprintf(t::_('No Http Server is set (Kernel::set_http_server()).')));
        }
        return self::$HttpServer->get_worker_id();
    }

    /**
     * @param string $id
     * @return object
     * @throws RunTimeException
     */
    public static function get_service(string $id) : object
    {
        if (!isset(self::$Container)) {
            throw new RunTimeException(sprintf(t::_('There is no Dependency Injection container set (Kernel::set_di_container()). The services are not available.')));
        }
        return self::$Container->get($id);
    }

    /**
     * @param string $id
     * @return bool
     * @throws RunTimeException
     */
    public static function has_service(string $id) : bool
    {
        if (!isset(self::$Container)) {
            throw new RunTimeException(sprintf(t::_('There is no Dependency Injection container set (Kernel::set_di_container()). The services are not available.')));
        }
        return self::$Container->has($id);
    }

    /**
     * @return bool
     */
    public static function is_initialized() : bool
    {
        return self::$is_initialized_flag;
    }

    public static function get_init_microtime() : float
    {
        return self::$init_microtime;
    }

    /**
     * @return LoggerInterface
     */
    public static function get_logger() : LoggerInterface
    {
        return self::$Logger;
    }

    public static function dump(/* mixed */ $var) : void
    {
        $frame = StackTraceUtil::get_stack_frame(2);
        $str = '';
        $str = print_r($var, TRUE);
        if ($frame) {
            $str .= 'printed in '.$frame['file'].'#'.$frame['line'].PHP_EOL;
        }
        $str .= PHP_EOL;
        print $str;
    }


    /**
     * Terminates the execution and prints the provided message
     * @param string $message
     */
    public static function stop(string $message, int $exit_code = self::EXIT_GENERAL_ERROR) : void
    {
        print $message.PHP_EOL;
        die($exit_code);//in swoole context in worker / coroutine this will throw Swoole\ExitException
    }

    /**
     * Exception handler does not work in Swoole worker context so everything in the request is in try/catch \Throwable and manual call to the exception handler
     * @param \Throwable $exception
     */
    public static function exception_handler(\Throwable $exception, ?int $exit_code = self::EXIT_GENERAL_ERROR): void
    {
        $output = '';
        //$output .= sprintf(t::_('Exception %s: %s in %s#%s'), get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine());
        //should not depend on the translator (t::_()) or any other code that is additionally autoloaded
        $output .= sprintf('Exception %s: %s in %s#%s', get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine());
        $output .= PHP_EOL;
        $output .= $exception->getTraceAsString();

        self::log($output, LogLevel::EMERGENCY);
        //file_put_contents('AAA', $output.PHP_EOL, FILE_APPEND);
        //self::logtofile($output);
        //die($output);
        //print $output;
        //die(1);//kill that worker
        //why kill the whole worker... why not just terminate the coroutine/request
        //in fact this code will be used only before the server is started
        //print $output.PHP_EOL;//the logger is used instead
        if ($exit_code !== NULL) {
            die($exit_code);
        } else {
            //when NULL is provided just print the message but do not exit
            //this is to be used in Server context - no point to kill the worker along with the rest of the coroutines
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
     */
    public static function error_handler(int $errno, string $errstr, string $errfile, int $errline, array $errcontext = []): void
    {
        throw new \Guzaba2\Kernel\Exceptions\ErrorException($errno, $errstr, $errfile, $errline, $errcontext);
    }

    public static function logtofile(string $content, array $context = []): void
    {
        //$path = self::$framework_root_dir . DIRECTORY_SEPARATOR . '../logs'. DIRECTORY_SEPARATOR . $file_name;
        //die(self::$cwd);
        //$path = self::$cwd . DIRECTORY_SEPARATOR . '../logs'. DIRECTORY_SEPARATOR . $file_name;
        //file_put_contents($path, $content.PHP_EOL.PHP_EOL, FILE_APPEND);
        //$content = time().' '.date('Y-m-d H:i:s').' '.$content.PHP_EOL.PHP_EOL;//no need of this
        self::$Logger->debug($content, $context);
    }
    
    /* DEBUG
    public static function logtofile_backtrace(string $filename) : void
    {
        //self::raise_memory_limit(4096);
        foreach(self::simplify_trace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)) as $key => $row){
            self::logtofile($filename,array($key, $row));//NOVERIFY
        }


    }

     public static function simplify_trace($debug_trace) {
        foreach($debug_trace as &$call) {
            unset($call['object']);
            unset($call['args']);
        }
        return $debug_trace;
    }
    */
    
    public static function bt(array $context = []) : void
    {
        $bt = print_r(Coroutine::getSimpleBacktrace(), TRUE);
        self::$Logger->debug($bt, $context);
    }

    /**
     * Prints a message to the default output of the server (in daemon mode this is redirected to file).
     * @param $message
     */
    public static function printk(string $message) : void
    {
        //TODO - would be nice if this can also print to any connected debugger session...
        //better - instead there can be Console sessions attached for these messages (which is different from debug session)

        if (trim($message)) {
            $microtime = microtime(TRUE);
            $microtime_diff = round($microtime - self::$init_microtime, 6);
            if (self::get_http_server()) {
                $worker_id = self::get_worker_id();
                if ($worker_id != -1) {
                    $worker_str = ' Worker #'.$worker_id;
                } else {
                    $worker_str = 'Startup';
                }
            } else {
                $worker_str = 'Startup';
            }
            // todo fix padding
            $message = sprintf('[%s %s] %s', $microtime_diff, $worker_str, $message);
        }
        //$handlers = self::$Logger->getHandlers();
        //TODO log to the main log too
        print $message;
    }

    /**
     * Registers a new namespace base that is to be looked up.
     * To be used if the application needs to use the Guzaba2 autoloader
     * @param string $namespace_prefix
     * @param string $base_path
     */
    public static function register_autoloader_path(string $namespace_base, string $base_path): void
    {
        self::$autoloader_lookup_paths[$namespace_base] = $base_path;
    }

    /**
     * @return array
     */
    public static function get_registered_autoloader_paths(): array
    {
        return self::$autoloader_lookup_paths;
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
    public static function check_syntax(string $path, ?string &$error = NULL) : bool
    {
        exec("php -l {$path} 2>&1", $output, $return);
        $error = $output[0];
        return $return ? TRUE : FALSE;
//        $ret = FALSE;
//        $output = \shell_exec("php -l {$path} 2>&1");
//        if (strpos($output, 'No syntax errors detected') === FALSE) {
//            $ret = TRUE;
//        }
//        return $ret;
    }

    public static function get_runtime_configuration(string $class_name) : array
    {
        $runtime_config = [];
        $RClass = new ReflectionClass($class_name);

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
//                if ($real_class_name === \GuzabaPlatform\Platform\Application\MysqlConnection::class) {
//                    print_r($registry_config);
//                }

                foreach ($default_config as $key_name=>$key_value) {
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
//                if ($real_class_name === \GuzabaPlatform\Platform\Application\MysqlConnection::class) {
//                    print_r($runtime_config);
//                }

                self::$Registry->add_to_runtime_config_file($real_class_name, "\nFINAL CONFIG_RUNTIME for {$real_class_name}:\n" . print_r($runtime_config, TRUE));
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

    public static function get_class_structure() : array
    {
        return self::$class_structure;
    }

    public static function get_class_all_children(string $class_name) : array
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

    public static function get_class_children(string $class) : array
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
    public static function get_class_parent(string $class) : ?string
    {
        return isset(self::$class_structure[$class]['parent']) ? self::$class_structure[$class]['parent']['name'] : NULL;
    }

    /**
     * @param string $class
     * @return array
     */
    public static function get_class_all_parents(string $class) : array
    {
        $ret = [];
        do {
            $parent_class = self::$class_structure[$class]['parent'];
            if (!$parent_class) {
                break;
            }
            $ret[] = $parent_class['name'];
            $class = $parent_class['name'];
        } while (TRUE);
        return $ret;
    }

    /**
     * Loads all classes found under the registered autoload paths.
     * @see self::$autoloader_lookup_paths
     * @see self::register_autoloader_path()
     * @return void
     */
    public static function load_all_classes() : void
    {
        foreach (self::$autoloader_lookup_paths as $namespace_base=>$autoload_lookup_path) {
            $Directory = new \RecursiveDirectoryIterator($autoload_lookup_path);
            $Iterator = new \RecursiveIteratorIterator($Directory);
            $Regex = new \RegexIterator($Iterator, '/^.+\.php$/i', \RegexIterator::GET_MATCH);
            foreach ($Regex as $path=>$match) {

                //print $path.' ';
                /*
                $ns_with_forward_slash = str_replace('\\', '/', $namespace_base);
                if (($pos = strpos($path, $ns_with_forward_slash)) !== FALSE) {



                    $class = str_replace(['/','.php'], ['\\',''], substr($path, $pos));

//                    if (strpos($class, 'CachingMiddleware') !== FALSE) {
//                        print 'GGGGGGGGGGGGGGGGGG';
//                    }

                    //we also need to check again already included files
                    //as including a certain file may trigger the autoload and load other classes that will be included a little later
                    $included_files = get_included_files();
                    if (in_array($path, $included_files) || in_array(SourceStream::PROTOCOL.'://'.$path, $included_files)) {
                        //skip this file - it is already included
                        continue;
                    }
                    //print 'OK'.PHP_EOL;
                    class_exists($class);//this will trigger the autoloader if the class doesnt already exist
                    //self::autoloader($class);//an explicit call will trigger an error if the class is already loaded
                } else {
                    //print 'NOOOO'.PHP_EOL;
                    print $path.' '.$ns_with_forward_slash.PHP_EOL;
                }
                */


                $class_name = str_replace($autoload_lookup_path, '', $path);
                $class_name = str_replace('\\\\','\\', $class_name);
                $class_name = str_replace('/', '\\', $class_name);
                $class_name = str_replace('\\\\','\\', $class_name);
                $class_name = str_replace($namespace_base, '', $class_name);//some may contain it
                $class_name = str_replace('\\\\','\\', $class_name);
                $class_name = $namespace_base.'\\'.$class_name;
                $class_name = str_replace('\\\\','\\', $class_name);
                $class_name = str_replace('.php', '', $class_name);

                //we also need to check again already included files
                //as including a certain file may trigger the autoload and load other classes that will be included a little later
                $included_files = get_included_files();
                if (in_array($path, $included_files) || in_array(SourceStream::PROTOCOL.'://'.$path, $included_files)) {
                    //skip this file - it is already included
                    continue;
                }
                class_exists($class_name);//this will trigger the autoloader if the class doesnt already exist
                //self::autoloader($class_name);//an explicit call will trigger an error if the class is already loaded
            }
        }
    }

    /**
     * Returns a twodimensional associative array: class_name=>['method1','method2']
     * @return array
     */
    public static function run_all_validations() : array
    {
        $validation_classes = [];
        foreach (self::$loaded_classes as $loaded_class) {
            if (
                is_a($loaded_class, ClassDeclarationValidationInterface::class, TRUE)
                && $loaded_class !== ClassDeclarationValidationInterface::class
            ) {

                $methods_run = $loaded_class::run_all_validations();
                $validation_classes[$loaded_class] = $methods_run;
            }
        }
        return $validation_classes;
    }

    public static function run_all_initializations() : array
    {
        $initialization_classes = [];
        foreach (self::$loaded_classes as $loaded_class) {
            if (
                is_a($loaded_class, ClassInitializationInterface::class, TRUE)
                && $loaded_class !== ClassInitializationInterface::class
            ) {

                $methods_run = $loaded_class::run_all_initializations();
                $initialization_classes[$loaded_class] = $methods_run;
            }
        }
        return $initialization_classes;
    }

    public static function get_loaded_classes() : array
    {
        return self::$loaded_classes;
    }

    public static function get_loaded_paths() : array
    {
        return self::$loaded_paths;
    }

    /**
     * Logs a message using the default logger
     *
     * @param string $message
     * @param string $level
     * @param array $context
     * @return bool
     */
    public static function log(string $message, string $level = LogLevel::INFO, array $context = []): bool
    {
        $Logger = self::get_logger();
        if (self::get_http_server()) {
            $message = 'Worker #'.self::get_worker_id().': '.$message;
        }
        $Logger->log($level, $message, $context);
        return TRUE;
    }



    /////////////////////////
    /// PROTECTED METHODS ///
    /////////////////////////

    /**
     * Just prints initialization messages.
     */
    protected static function print_initialization_messages() : void
    {
        self::printk(PHP_EOL);
        self::printk(self::FRAMEWORK_BANNER);
        self::printk(PHP_EOL);

        Kernel::printk(sprintf('Initialization at: %s %s %s', self::$init_microtime, date('Y-m-d H:i:s'), date_default_timezone_get() ).PHP_EOL);

        Kernel::printk(sprintf(t::_('PHP %s, Swoole %s, Guzaba %s').PHP_EOL, PHP_VERSION, SWOOLE_VERSION, Kernel::FRAMEWORK_VERSION));
        Kernel::printk(SysUtil::get_basic_sysinfo().PHP_EOL);

        self::printk(PHP_EOL);

        $registry_backends = self::$Registry->get_backends();
        $registry_str = 'Registry backends:'.PHP_EOL;
        foreach ($registry_backends as $RegistryBackend) {
            $registry_str .= str_repeat(' ',4).'- '.get_class($RegistryBackend).PHP_EOL;
        }
        self::printk($registry_str);
        self::printk(PHP_EOL);


        $handlers = self::$Logger->getHandlers();
        $error_handlers_str = 'Logger Handlers:'.PHP_EOL;
        foreach ($handlers as $Handler) {
            $error_handlers_str .= str_repeat(' ',4).'- '.get_class($Handler).' : '.$Handler->getUrl().' : '.self::$Logger::getLevelName($Handler->getLevel()).PHP_EOL;
        }
        Kernel::printk($error_handlers_str);
        self::printk(PHP_EOL);

    }

    protected static function autoloader(string $class_name): bool
    {
        $ret = FALSE;

        foreach (self::$autoloader_lookup_paths as $namespace_base=>$lookup_path) {
            //needed because swoole is not available on windows and CI may run on windows.
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN' && strpos($class_name, 'ReplacementClasses')) {
                continue;
            }

            if (strpos($class_name, $namespace_base) === 0) {

                if ($namespace_base === self::FRAMEWORK_NAME) {
                    $class_path = realpath($lookup_path.'/'.str_replace('\\', '/', $class_name).'.php');
                } else {
                    $class_path = realpath($lookup_path.'/'.str_replace('\\', '/', str_replace($namespace_base, '', $class_name)).'.php');
                }

                if ($class_path && is_readable($class_path)) {

                    self::require_class($class_path, $class_name);
                    //the file may exist but it may not contain the needed file
                    if (!class_exists($class_name) && !interface_exists($class_name) && !trait_exists($class_name)) {
                        $message = sprintf('The file %s is readable but does not contain the class/interface/trait %s. Please check the class and namespace declarations.', $class_path, $class_name);
                        throw new \Guzaba2\Kernel\Exceptions\AutoloadException($message);
                    }
                    self::initialize_class($class_name);
                    self::$loaded_classes[] = $class_name;
                    self::$loaded_paths[] = $class_path;
                    $ret = TRUE;

                    $parent_class = get_parent_class($class_name);
                    if (!$parent_class) {
                        $parent_class = NULL;
                        self::$class_structure[$class_name] = ['name' => $class_name, 'parent' => $parent_class, 'children' => [] ];
                    } else {
                        self::$class_structure[$class_name] = ['name' => $class_name, 'parent' => &self::$class_structure[$parent_class], 'children' => [] ];
                    }

                    self::$class_structure[$parent_class]['children'][] =& self::$class_structure[$class_name];

                } else {
                    //$message = sprintf(t::_('Class %s (path %s) is not found (or not readable).'), $class_name, $class_path);
                    $message = sprintf('Class %s (path %s) is not found (path does not exist or not readable).', $class_name, $class_path);
                    throw new \Guzaba2\Kernel\Exceptions\AutoloadException($message);
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
     */
    protected static function require_class(string $class_path, string $class_name) /* mixed */
    {

        //print $class_path.' '.$class_name.PHP_EOL;

        $ret = NULL;

        try {
            if (\Swoole\Coroutine::getCid() > 0) {
                $class_source = \Swoole\Coroutine::readFile($class_path);
            } else {
                $class_source = file_get_contents($class_path);
            }

            //TODO - he below is a very primitive check - needs to be improved and use tokenizer
            if ($class_name != SourceStream::class && $class_name != self::class && strpos($class_source, 'protected const CONFIG_RUNTIME =') !== FALSE) {

//                if ($class_name === 'GuzabaPlatform\Platform\Application\MysqlConnection') {
//                    print '==================================';
//                    print file_get_contents(SourceStream::PROTOCOL.'://'.$class_path);
//                    print '==================================';
//                }

                //use stream instead of eval because of the error reporting - it becomes more obscure with eval()ed code
                $ret = require_once(SourceStream::PROTOCOL.'://'.$class_path);
            } else {
                $ret = require_once($class_path);
            }
        } catch (\Throwable $exception) {
            //print '==================='.PHP_EOL;
            print 'ERROR IN CLASS GENERATION'.PHP_EOL;
            print $exception->getMessage().' in file '.$exception->getFile().'#'.$exception->getLine().PHP_EOL.$exception->getTraceAsString();
            //print '==================='.PHP_EOL;
        }



        return $ret;
    }



    /**
     * @param string $class_name
     * @throws \ReflectionException
     */
    protected static function initialize_class(string $class_name) : void
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
