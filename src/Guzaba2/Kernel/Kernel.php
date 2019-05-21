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
use Guzaba2\Kernel\Exceptions\ConfigurationException;
use Guzaba2\Translator\Translator as t;
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
    protected static $guzaba2_root_dir;

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

        self::$guzaba2_root_dir = realpath(self::$kernel_dir.'/../../');

        self::register_autoloader_path(self::FRAMEWORK_NAME, self::$guzaba2_root_dir);
        //print self::$guzaba2_root_dir.PHP_EOL;


        spl_autoload_register([__CLASS__, 'autoloader'], TRUE, TRUE);//prepend before Composer's autoloader
        set_exception_handler([__CLASS__, 'exception_handler']);
        set_error_handler([__CLASS__, 'error_handler']);



//        if (interface_exists(ConfigInterface::class)) {
//            die('aaa');
//        } else {
//            die('nnn');
//        }



        self::$is_initialized_flag = TRUE;

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

    //public static function run_swoole(?LoggerInterface $logger = NULL, string $host, int $port, array $options = [], ?callable $request_handler = NULL) : int
    //NOT USED
    public static function run_swoole(string $host, int $port, array $options = [], ?callable $request_handler = NULL) : int
    {

        //self::run(function(){});
        //$o = new \Guzaba2\Http\Response();


        $callable = function() use ($host, $port, $options, $request_handler) : int
        {
            $request_handler = $request_handler ?? new \Guzaba2\Swoole\RequestHandler();
            $http_server = new \Guzaba2\Swoole\Server($host, $port, $options);
            $http_server->on('request', $request_handler);
            $http_server->start();

            return self::EXIT_SUCCESS;
        };

        return self::run($callable);

    }

    /**
     * Exception handler does not work in Swoole worker context so everything in the request is in try/catch \Throwable and manual call to the exception handler
     * @param \Throwable $exception
     */
    public static function exception_handler(\Throwable $exception) : void
    {
        $output = '';
        $output .= sprintf(t::_('%s in %s#%s'), $exception->getMessage(), $exception->getFile(), $exception->getLine() );
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
        //$path = self::$guzaba2_root_dir . DIRECTORY_SEPARATOR . '../logs'. DIRECTORY_SEPARATOR . $file_name;
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
        /*
        if (strpos($class_name,self::FRAMEWORK_NAME) === 0) { //starts with Guzaba2
            $class_path = str_replace('\\', \DIRECTORY_SEPARATOR, self::$guzaba2_root_dir.\DIRECTORY_SEPARATOR.$class_name).'.php';
            if (is_readable($class_path)) {
                require_once($class_path);
                self::$loaded_classes[] = $class_name;
                self::$loaded_paths[] = $class_path;
                $ret = TRUE;
            } else {
                $message = sprintf(t::_('Class %s (path %s) is not found (or not readable).'), $class_name, $class_path);
                throw new \Guzaba2\Kernel\Exceptions\AutoloadException($message);
            }

        } else {
            //not found - proceed with the next autoloader - probably this would be composer's autoloader
        }
        */
        foreach (self::$autoloader_lookup_paths as $namespace_base=>$lookup_path) {
            if (strpos($class_name, $namespace_base) === 0) {
                $class_path = str_replace('\\', \DIRECTORY_SEPARATOR, $lookup_path.\DIRECTORY_SEPARATOR.$class_name).'.php';
                //$class_path = realpath($class_path);
                if (is_readable($class_path)) {
                    require_once($class_path);
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

    /**
     * @param string $class_name
     */
    protected static function initialize_class(string $class_name) : void
    {

        $RClass = new ReflectionClass($class_name);
        

        if ($RClass->hasOwnMethod('_initialize_class')) {
            call_user_func([$class_name, '_initialize_class']);
        }

        
        //if (is_a($class_name, ConfigInterface::class)) { //not working?
        if ($RClass->implementsInterface(ConfigInterface::class)) {

            //instead of requiring the classes to extend Base (so that they provide the needed methods)
            //Kernel can work with reflection and directly with the static property
            //the classes supporting config must implement a ConfigInterface
            //$config_array = $RClass->getConstant('CONFIG_DEFAULTS');//false if not found
            //if (defined($class_name.'::CONFIG_DEFAULTS') && !isset($class_name::$CONFIG_RUNTIME)) {
            //if ($RClass->hasConstant('CONFIG_DEFAULTS') && !$RClass->hasStaticProperty('CONFIG_RUNTIME')) {
            //    throw new ConfigurationException(sprintf(t::_('The class %s has CONFIG_DEFAULTS constant defined but has no $CONFIG_RUNTIME static property defined. For the configuration settings to work both need to be defined.'), $class_name));
            //    //throw new ConfigurationException(sprintf('The class %s has CONFIG_DEFAULTS constant defined but has no $CONFIG_RUNTIME static property defined. For the configuration settings to work both need to be defined.', $class_name));
            //}
            //the above case is not possible because the Base class uses the SupportsConfig trait which always defines $CONFIG_RUNTIME

//            //if (!defined($class_name.'::CONFIG_DEFAULTS') && isset($class_name::$CONFIG_RUNTIME)) {
            //if (!$RClass->hasConstant('CONFIG_DEFAULTS') && $RClass->hasStaticProperty('CONFIG_RUNTIME')) {
            //    throw new ConfigurationException(sprintf(t::_('The class %s has no CONFIG_DEFAULTS constant defined but has $CONFIG_RUNTIME static property defined. For the configuration settings to work both need to be defined.'), $class_name));
            //    //throw new ConfigurationException(sprintf('The class %s has no CONFIG_DEFAULTS constant defined but has $CONFIG_RUNTIME static property defined. For the configuration settings to work both need to be defined.', $class_name));
            //}
            //the above case is allowed - if the class does not specify CONFIG_DEFAULTS this means it is not using configuration (no lookup in the registry will be done)

            //$class_name::$CONFIG_RUNTIME += $class_name::CONFIG_DEFAULTS;//the $CONFIG_RUNTIME may already contain values too
            if ($RClass->hasOwnConstant('CONFIG_DEFAULTS') && $RClass->hasOwnStaticProperty('CONFIG_RUNTIME')) {

                $RProperty = $RClass->getProperty('CONFIG_RUNTIME');
                $RProperty->setAccessible(TRUE);

                $default_config = ( new \ReflectionClassConstant($class_name, 'CONFIG_DEFAULTS') )->getValue();

                $runtime_config = $default_config;

                //get the configuration from the parent class
                $RParentClass = $RClass->getParentClass();
                $parent_class_name = $RParentClass->name;
                $parent_config = $parent_class_name::get_runtime_configuration();

                $runtime_config += $parent_config;

                //get configuration from the registry
                //only variables defined in CONFIG_DEFAULTS will be imported from the Registry
                $registry_config = self::$Registry->get_class_config_values($class_name);

                foreach ($default_config as $key_name=>$key_value) {
                    if (array_key_exists($key_name, $registry_config)) {
                        $runtime_config[$key_name] = $registry_config[$key_name];
                    }
                }

                $RProperty->setValue($runtime_config);
            } else {
                //this class is not defining config values - will have access to the parent::$CONFIG_RUNTIME
            }

        } else {
            //do nothing - does not require configuration
        }




    }


}