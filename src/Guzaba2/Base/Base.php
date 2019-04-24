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

namespace Guzaba2\Base;

use \Guzaba2\Base\Traits\SupportsObjectInternalId;

/**
 * Class Base
 * @package Guzaba2\Base
 * All classes except Kernel and the various exceptions inherit this class.
 * Kernel inherits nothing and the exceptions inherit BaseException
 */
abstract class Base
{

    use SupportsObjectInternalId;

    /**
     * Base constructor.
     * All children must invoke the parent constructor
     */
    public function __construct() {
        $this->set_object_internal_id();
    }


    public function __destruct()
    {
        if (method_exists($this, '_before_destruct')) {
            call_user_func_array([$this, '_before_destruct'], []);
        }
    }
}