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

    private function __construct() {

    }

    public static function run(callable $callable) : int
    {

        self::$cwd = getcwd();

        self::$kernel_dir = dirname(__FILE__);

        self::$guzaba2_root_dir = realpath(self::$kernel_dir.'/../../');
        //print self::$guzaba2_root_dir.PHP_EOL;


        spl_autoload_register([__CLASS__, 'autoloader'], TRUE, TRUE);//prepend before Composer's autoloader
        set_exception_handler([__CLASS__, 'exception_handler']);
        set_error_handler([__CLASS__, 'error_handler']);



        $ret = $callable();

        if (!is_int($ret)) {
            $ret = self::EXIT_SUCCESS;
        }
        return $ret;
    }

    //public static function run_swoole(?LoggerInterface $logger = NULL, string $host, int $port, array $options = [], ?callable $request_handler = NULL) : int
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

    public static function run_swoole_mvc(callable $callable) : int
    {

    }

    protected static function autoloader(string $class_name) : bool
    {
        //print $class_name.PHP_EOL;
        $ret = FALSE;
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
            //TODO implement project class loading
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
        $output .= sprintf(t::_('%s in %s#%s'), $exception->getMessage(), $exception->getFile(), $exception->getLine() );
        $output .= PHP_EOL;
        $output .= $exception->getTraceAsString();
        //self::logtofile('UNCAUGHT_EXCEPTIONS', $output);
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

    public static function logtofile(string $file_name, string $content) : void
    {
        die('disabled');
        //$path = self::$guzaba2_root_dir . DIRECTORY_SEPARATOR . '../logs'. DIRECTORY_SEPARATOR . $file_name;
        //die(self::$cwd);
        $path = self::$cwd . DIRECTORY_SEPARATOR . '../logs'. DIRECTORY_SEPARATOR . $file_name;
        file_put_contents($path, $content.PHP_EOL.PHP_EOL, FILE_APPEND);
    }
}