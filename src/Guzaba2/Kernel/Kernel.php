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

    private function __construct() {

    }

    public static function run(callable $callable) : int
    {
        spl_autoload_register([__CLASS__, 'autoloader']);

        $ret = $callable();

        if (!is_int($ret)) {
            $ret = self::EXIT_SUCCESS;
        }
        return $ret;
    }

    public static function run_swoole(callable $callable) : int
    {

    }

    public static function run_swoole_mvc(callable $callable) : int
    {

    }

    protected static function autoloader(string $class_name) : bool
    {
        //print $class_name;
    }
}