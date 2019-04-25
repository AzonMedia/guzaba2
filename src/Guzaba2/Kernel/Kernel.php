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

        spl_autoload_register([__CLASS__, 'autoloader']);

        $ret = $callable();

        if (!is_int($ret)) {
            $ret = self::EXIT_SUCCESS;
        }
        return $ret;
    }

    public static function run_swoole(callable $callable) : int
    {

        $http_server = new \Guzaba2\Http\HttpServer();
        $http_server->on('start', )
        $http_server->run();

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
            }
        }
        //print getcwd();



        return $ret;
    }
}