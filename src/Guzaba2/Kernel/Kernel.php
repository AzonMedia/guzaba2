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

        self::$guzaba2_root_dir = realpath(self::$kernel_dir.'/../../');

        self::register_autoloader_path(self::FRAMEWORK_NAME, self::$guzaba2_root_dir);
        //print self::$guzaba2_root_dir.PHP_EOL;


        spl_autoload_register([__CLASS__, 'autoloader'], TRUE, TRUE);//prepend before Composer's autoloader
        set_exception_handler([__CLASS__, 'exception_handler']);
        set_error_handler([__CLASS__, 'error_handler']);

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
            //return require_once($class_path);
            $class_source = file_get_contents($class_path);
            //print $class_source;
            if (strpos($class_source, 'protected const CONFIG_RUNTIME') !== FALSE) {

                /*
                $temp_files_base_path = sys_get_temp_dir().'/'.self::FRAMEWORK_NAME.'/'.\Swoole\Coroutine::getCid().'/';

                //needs to include first the class name without the configuration set
                //but this class name must be changed to something else as including it will create the class with the real name while we actually want to include it under different name, obtain the config and then include it with the real name
                $class_source = file_get_contents($class_path);
                $ns_arr = explode('\\', $class_name);
                $class_name_without_ns = array_pop($ns_arr);
                unset($ns_arr);
                //TODO - replace the below with tokenizer
                $class_without_config_source = str_replace('class '.$class_name_without_ns, 'class '.$class_name_without_ns.'_without_config', $class_source);

                //$fp = tmpfile();
                //$fp = fopen(sys_get_temp_dir().'/'.str_replace('\\','_',$class_name).'.php', 'w+');

                //$original_class_path = sys_get_temp_dir().'/'.str_replace('/','_',$class_path).'_without_config';
                //preserving the same path to the file in the TMP folder allowes for easier debugging
                //the only difference is that the error is reported in a file with a name/path prepended by /tmp instead of the original one

                $original_class_path = $temp_files_base_path.str_replace('.php','_original.php',$class_path);
                $original_class_dir_path = dirname($original_class_path);
                if (!file_exists($original_class_dir_path)) {
                    @mkdir($original_class_dir_path, 0777, TRUE);
                }

                $fp = fopen($original_class_path, 'w+');

                fwrite($fp, $class_without_config_source);
                $fpath = stream_get_meta_data($fp)['uri'];
                //here the original class but renamed is included

                require_once($fpath);
                fclose($fp);
                if (file_exists($original_class_path)) {
                    @unlink($original_class_path);
                }

                //the original class is used to obtain the runtime configuration
                $runtime_config = self::get_runtime_configuration($class_name.'_without_config');

                //debug info
                //print $class_name.' has the following RUNTIME_CONFIG generated: '.print_r($runtime_config, TRUE);


                $to_be_replaced_str = 'protected const CONFIG_RUNTIME = [];';
                $replacement_str = 'protected const CONFIG_RUNTIME = '.str_replace(PHP_EOL, ' ',var_export($runtime_config, TRUE)).';';//remove the new lines as this will change the line of the errors/exceptions
                $class_source = str_replace($to_be_replaced_str, $replacement_str, $class_source);

                //$fp = fopen('php://memory', 'w+');//cant include from this as require_once opens a separate fp and the content is lost
                //$fp = tmpfile();
                //$fp = fopen(sys_get_temp_dir().'/'.str_replace('\\','_',$class_name).'.php', 'w+');
                //preserving the same path to the file in the TMP folder allowes for easier debugging
                //the only difference is that the error is reported in a file with a name/path prepended by /tmp instead of the original one
                $updated_class_path = $temp_files_base_path.$class_path;

                $updated_class_dir_path = dirname($original_class_path);
                if (!file_exists($updated_class_dir_path)) {
                    @mkdir($updated_class_dir_path, 0777, TRUE);
                }
                $fp = fopen($updated_class_path, 'w+');
                fwrite($fp, $class_source);

                $fpath = stream_get_meta_data($fp)['uri'];

                $ret = require_once($fpath);
                //print_r(get_declared_classes());
                fclose($fp);

                self::rrmdir($temp_files_base_path);
                */

                //the above fails in high concurrency
                //use eval instead - it has overhead but in swoole context this happens onle once per worker
                //the below code has no blocking components - it relies entirely on the php interpreter

                //needs to include first the class name without the configuration set
                //but this class name must be changed to something else as including it will create the class with the real name while we actually want to include it under different name, obtain the config and then include it with the real name
                $class_source = file_get_contents($class_path);
                $ns_arr = explode('\\', $class_name);
                $class_name_without_ns = array_pop($ns_arr);
                unset($ns_arr);
                //TODO - replace the below with tokenizer
                $class_without_config_source = str_replace('class '.$class_name_without_ns, 'class '.$class_name_without_ns.'_without_config', $class_source);
                if (strpos($class_without_config_source,'<?php')===0) {
                    $class_without_config_source = substr($class_without_config_source,5);
                }
                //print $class_without_config_source;

                eval($class_without_config_source);

                $runtime_config = self::get_runtime_configuration($class_name.'_without_config');

                $to_be_replaced_str = 'protected const CONFIG_RUNTIME = [];';
                $replacement_str = 'protected const CONFIG_RUNTIME = '.str_replace(PHP_EOL, ' ',var_export($runtime_config, TRUE)).';';//remove the new lines as this will change the line of the errors/exceptions
                $class_source = str_replace($to_be_replaced_str, $replacement_str, $class_source);
                if (strpos($class_source,'<?php')===0) {
                    $class_source = substr($class_source,5);
                }
                eval($class_source);

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

    protected static function get_runtime_configuration(string $class_name) : array
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


                //print $class_name.' '.print_r($parent_config);

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