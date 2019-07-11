<?php
declare(strict_types=1);

/**
 * Guzaba Framework
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
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Base\Interfaces\ConfigInterface;
use Guzaba2\Base\Traits\SupportsConfig;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Kernel\Exceptions\ConfigurationException;
use Guzaba2\Translator\Translator as t;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Kernel
 * @package Guzaba2\Kernel
 */
class Kernel
{
    public const EXIT_SUCCESS = 0;

    /**
     * @var string
     */
    protected static $cwd;

    /**
     * @var string
     */
    protected static $kernel_dir;

    /**
     * @var string
     */
    protected static $framework_root_dir;

    /**
     *
     */
    public const FRAMEWORK_NAME = 'Guzaba2';

    /**
     * @var array
     */
    protected static $loaded_classes = [];

    /**
     * @var array
     */
    protected static $loaded_paths = [];

    /**
     * Additional places where the autoloader should look.
     * An associative array containing namespace prefix as key and lookup path as value.
     * @var array
     */
    protected static $autoloader_lookup_paths = [];

    /**
     * @var LoggerInterface
     */
    protected static $Logger;

    /**
     * @var RegistryInterface
     */
    protected static $Registry;

    /**
     * @var ContainerInterface
     */
    protected static $Container;

    /**
     * Is the kernel initialized
     * @var bool
     */
    protected static $is_initialized_flag = FALSE;


    private function __construct() {

    }

    //////////////////////
    /// PUBLIC METHODS ///
    //////////////////////

    //public static function initialize(\Guzaba2\Registry\Interfaces\Registry $Registry, LoggerInterface $Logger) : void
    public static function initialize(RegistryInterface $Registry, LoggerInterface $Logger) : void
    {
        self::$Registry = $Registry;
        self::$Logger = $Logger;

        self::$cwd = getcwd();

        self::$kernel_dir = dirname(__FILE__);

        self::$framework_root_dir = realpath(self::$kernel_dir.'/../../');

        self::register_autoloader_path(self::FRAMEWORK_NAME, self::$framework_root_dir);


        spl_autoload_register([__CLASS__, 'autoloader'], TRUE, TRUE);//prepend before Composer's autoloader
        set_exception_handler([__CLASS__, 'exception_handler']);
        set_error_handler([__CLASS__, 'error_handler']);

        //stream_wrapper_register('guzaba.source', SourceStream::class);
        stream_wrapper_register(SourceStream::PROTOCOL, SourceStream::class);

        self::$is_initialized_flag = TRUE;

    }

    public static function set_di_container(ContainerInterface $Container) : void
    {
        self::$Container = $Container;
    }

    public static function get_service(string $id) : object
    {
        return self::$Container->get($id);
    }

    public static function has_service(string $id) : bool
    {
        return self::$Container->has($id);
    }

    public static function is_initialized() : bool
    {
        return self::$is_initialized_flag;
    }

    public static function run(callable $callable) : int
    {

        if (!self::is_initialized()) {
            throw new \Exception('Kernel is not initialized. Please execute Kernel::initialize() first.');
        }

        $ret = $callable();

        if (!is_int($ret)) {
            $ret = self::EXIT_SUCCESS;
        }
        return $ret;
    }

    /**
     * Terminates the execution and prints the provided message
     * @param string $message
     */
    public static function stop(string $message) : void
    {
        die($message.PHP_EOL);
    }


    /**
     * Exception handler does not work in Swoole worker context so everything in the request is in try/catch \Throwable and manual call to the exception handler
     * @param \Throwable $exception
     */
    public static function exception_handler(\Throwable $exception) : void
    {
        $output = '';
        $output .= sprintf(t::_('Exception %s: %s in %s#%s'), get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine() );
        $output .= PHP_EOL;
        $output .= $exception->getTraceAsString();
        self::logtofile($output);
        //die($output);
        print $output;
        die(1);//kill that worker
    }

    /**
     * Error handler works even in Swoole worker context
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @param array $errcontext
     * @throws Exceptions\ErrorException
     */
    public static function error_handler( int $errno , string $errstr, string $errfile, int $errline , array $errcontext = []) : void
    {
        throw new \Guzaba2\Kernel\Exceptions\ErrorException($errno, $errstr, $errfile, $errline , $errcontext);
    }

    public static function logtofile(string $content, array $context = []) : void
    {
        //$path = self::$framework_root_dir . DIRECTORY_SEPARATOR . '../logs'. DIRECTORY_SEPARATOR . $file_name;
        //die(self::$cwd);
        //$path = self::$cwd . DIRECTORY_SEPARATOR . '../logs'. DIRECTORY_SEPARATOR . $file_name;
        //file_put_contents($path, $content.PHP_EOL.PHP_EOL, FILE_APPEND);
        //$content = time().' '.date('Y-m-d H:i:s').' '.$content.PHP_EOL.PHP_EOL;//no need of this
        self::$Logger->debug($content, $context);
    }

    /**
     * Registers a new namespace base that is to be looked up.
     * To be used if the application needs to use the Guzaba2 autoloader
     * @param string $namespace_prefix
     * @param string $base_path
     */
    public static function register_autoloader_path(string $namespace_base, string $base_path) : void
    {
        self::$autoloader_lookup_paths[$namespace_base] = $base_path;
    }

    /**
     * @return array
     */
    public static function get_registered_autoloader_paths() : array
    {
        return self::$autoloader_lookup_paths;
    }

    public static function namespace_base_is_registered(string $namespace_base) : bool
    {
        return array_key_exists($namespace_base, self::$autoloader_lookup_paths);
    }

    /////////////////////////
    /// PROTECTED METHODS ///
    /////////////////////////

    protected static function autoloader(string $class_name) : bool
    {
        //print $class_name.PHP_EOL;
        $ret = FALSE;

        foreach (self::$autoloader_lookup_paths as $namespace_base=>$lookup_path) {
            if (strpos($class_name, $namespace_base) === 0) {
                $class_path = str_replace('\\', \DIRECTORY_SEPARATOR, $lookup_path.\DIRECTORY_SEPARATOR.$class_name).'.php';
                //$class_path = realpath($class_path);
                if (is_readable($class_path)) {
                    //require_once($class_path);
                    self::require_class($class_path, $class_name);
                    //the file may exist but it may not contain the needed file
                    if (!class_exists($class_name) && !interface_exists($class_name) && !trait_exists($class_name) ) {

                        $message = sprintf('The file %s is readable but does not contain the class/interface/trait %s. Please check the class and namespace declarations.', $class_path, $class_name);
                        throw new \Guzaba2\Kernel\Exceptions\AutoloadException($message);
                    }
                    self::initialize_class($class_name);
                    self::$loaded_classes[] = $class_name;
                    self::$loaded_paths[] = $class_path;
                    $ret = TRUE;
                } else {
                    //$message = sprintf(t::_('Class %s (path %s) is not found (or not readable).'), $class_name, $class_path);
                    $message = sprintf('Class %s (path %s) is not found (or not readable).', $class_name, $class_path);
                    throw new \Guzaba2\Kernel\Exceptions\AutoloadException($message);
                }
            } else {
                //this autoloader can not serve this request - skip this class and leave to the next autoloader (probably Composer) to load it
            }

        }

        return $ret;
    }

    protected static function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir."/".$object))
                        self::rrmdir($dir."/".$object);
                    else
                        unlink($dir."/".$object);
                }
            }
            rmdir($dir);
        }
    }

    protected static function require_class(string $class_path, string $class_name) /* mixed */
    {

        $ret = NULL;

        try {

            $class_source = file_get_contents($class_path);

            if ($class_name != SourceStream::class && strpos($class_source, 'protected const CONFIG_RUNTIME') !== FALSE) {

                //use stream instead of eval because of the error reporting - it becomes more obscure with eval()ed code
                $ret = require_once(SourceStream::PROTOCOL.'://'.$class_path);

            } else {
                $ret = require_once($class_path);
            }
        } catch (\Throwable $exception) {
            print '==================='.PHP_EOL;
            print 'ERROR IN CLASS GENERATION'.PHP_EOL;
            print $exception->getMessage().' in file '.$exception->getFile().'#'.$exception->getLine().PHP_EOL.$exception->getTraceAsString();
            print '==================='.PHP_EOL;
        }



        return $ret;
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
    }

    public static function get_runtime_configuration(string $class_name) : array
    {
        $runtime_config = [];
        $RClass = new ReflectionClass($class_name);

        if ($RClass->implementsInterface(ConfigInterface::class)) {


            //if ($RClass->hasOwnConstant('CONFIG_DEFAULTS') && $RClass->hasOwnStaticProperty('CONFIG_RUNTIME')) {
            if ($RClass->hasOwnConstant('CONFIG_DEFAULTS') && $RClass->hasOwnConstant('CONFIG_RUNTIME')) {


                $default_config = ( new \ReflectionClassConstant($class_name, 'CONFIG_DEFAULTS') )->getValue();

                $runtime_config = $default_config;

                //get the configuration from the parent class
                $RParentClass = $RClass->getParentClass();
                $parent_class_name = $RParentClass->name;
                //$parent_config = $parent_class_name::get_runtime_configuration();
                $parent_config = [];


                //if (is_a($parent_class_name, ConfigInterface::class)) {
                if ($RParentClass->implementsInterface(ConfigInterface::class)) {
                    $parent_config = $parent_class_name::get_runtime_configuration();
                }


                $runtime_config += $parent_config;//the parent config does not overwrite the current config

                //get configuration from the registry
                //only variables defined in CONFIG_DEFAULTS will be imported from the Registry
                $registry_config = self::$Registry->get_class_config_values($class_name);


                foreach ($default_config as $key_name=>$key_value) {
                    if (array_key_exists($key_name, $registry_config)) {
                        $runtime_config[$key_name] = $registry_config[$key_name];
                    }
                    //check also if there any any prefix in the var name that matches a prefix in the config array
                    if (is_array($key_value)) {
                        //look for $key_name as part of a var name
                        //assuming _ as separator between the array

                        foreach ($registry_config as $reg_key_name=>$reg_key_value) {
                            if (strpos($reg_key_name, $key_name) === 0) { //begins with
                                $runtime_config[$key_name][str_replace($key_name.'_', '', $reg_key_name)] = $reg_key_value;
                            }
                        }
                        //todo - rework with a closure to handle multidomentional arrays
//                        $WalkArrays = function () use (&$runtime_config, $registry_config) : void
//                        {
//
//                        };
                    }
                }

            } else {
                //this class is not defining config values - will have access to the parent::CONFIG_RUNTIME
            }

        } else {
            //do nothing - does not require configuration
        }
        return $runtime_config;
    }

    /**
     * @param string $class_name
     */
    protected static function initialize_class(string $class_name) : void
    {
        
        $RClass = new ReflectionClass($class_name);
        

        if ($RClass->hasOwnMethod('_initialize_class')) {
            call_user_func([$class_name, '_initialize_class']);
        }

    }


}