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
 * @package        Patterns
 * @license        http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author        Veselin Kenashkov <kenashkov@azonmedia.com>
 */

namespace Guzaba2\Patterns;

use Guzaba2\Patterns\Interfaces\SingletonInterface;

/**
 * Class WorkerSingleton
 * @package Guzaba2\Patterns
 */
class WorkerSingleton extends Singleton
{

    /**
     * Array of ExecutionSingleton
     * @var array
     */
    private static $instances = [];

    /**
     * @return ExecutionSingleton
     */
    public static function &get_instance() : SingletonInterface
    {
        $called_class = get_called_class();
        if (!array_key_exists($called_class, self::$instances) || !self::$instances[$called_class] instanceof $called_class) {
            self::$instances[$called_class] = new $called_class();
        }
        return self::$instances[$called_class];
    }

    public static function get_instances() : array
    {
        return self::$instances;
    }

    public function destroy() : void
    {
        $called_class = get_class($this);
        self::$instances[$called_class] = NULL;
        unset(self::$instances[$called_class]);
    }
}