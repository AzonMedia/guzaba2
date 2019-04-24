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

use Guzaba2\Base\Base;

/**
 * Class Singleton
 * @package Guzaba2\Patterns
 */
abstract class Singleton extends Base
{
    public abstract static function &get_instance() : self ;

    public abstract function destroy() : void;
}