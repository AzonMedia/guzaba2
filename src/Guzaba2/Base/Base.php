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

use Guzaba2\Base\Interfaces\ConfigInterface;
use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;
use Guzaba2\Base\Interfaces\UsesServicesInterface;

use Guzaba2\Base\Traits\SupportsConfig;
use Guzaba2\Base\Traits\SupportsObjectInternalId;
use Guzaba2\Base\Traits\UsesServices;

use Guzaba2\Base\Exceptions\RunTimeException;

use Guzaba2\Translator\Translator as t;

/**
 * Class Base
 * @package Guzaba2\Base
 * All classes except Kernel and the various exceptions inherit this class.
 * Kernel inherits nothing and the exceptions inherit BaseException
 */
abstract class Base
implements ConfigInterface, ObjectInternalIdInterface, UsesServicesInterface
{

    use SupportsObjectInternalId;

    use SupportsConfig;

    use UsesServices;

    /**
     * Base constructor.
     * All children must invoke the parent constructor
     */
    protected function __construct() {
        $this->set_object_internal_id();
    }

    public function __destruct()
    {
        if (method_exists($this, '_before_destruct')) {
            call_user_func_array([$this, '_before_destruct'], []);
        }
    }

    /**
     * @param string $property
     * @param $value
     * @throws RunTimeException
     */
    public function __set(string $property, /* mixed */ $value) : void
    {
        throw new \Guzaba2\Base\Exceptions\RunTimeException(sprintf(t::_('The instance is of class %s which inherits %s which is a strict class. It is now allowed to set new object properties at run time.'), get_class($this), __CLASS__ ));
    }
}