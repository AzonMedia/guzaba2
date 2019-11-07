<?php
declare(strict_types=1);

/**
 * Guzaba Framework 2
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

use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Interfaces\ConfigInterface;
use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;
use Guzaba2\Base\Interfaces\UsesServicesInterface;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Translator\Translator as t;

use Guzaba2\Base\Traits\SupportsConfig;
use Guzaba2\Base\Traits\SupportsObjectInternalId;
use Guzaba2\Base\Traits\UsesServices;
use Guzaba2\Base\Traits\StaticStore;
use Guzaba2\Base\Traits\ContextAware;

/**
 * Class Base
 * @package Guzaba2\Base
 * All classes except Kernel and the various exceptions inherit this class.
 * Kernel inherits nothing and the exceptions inherit BaseException
 */
abstract class Base implements ConfigInterface, ObjectInternalIdInterface, UsesServicesInterface
{
    use SupportsObjectInternalId;
    use SupportsConfig;
    use UsesServices;
    //use StaticStore;//this becomes too expensive to use
    use ContextAware;

    /**
     * Base constructor.
     * All children must invoke the parent constructor
     */
    public function __construct()
    {
        $this->set_object_internal_id();
        $this->set_created_coroutine_id();
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
    public function __set(string $property, /* mixed */ $value): void
    {
        //throw new RunTimeException(sprintf(t::_('The instance is of class %s which inherits %s which is a strict class. It is not allowed to set new object properties at run time. The other case is to access a non initialized typed property.'), get_class($this), __CLASS__));
        //when typed properties are used this can be triggered not only on undefined property but also when setting a typed property that was not initialized
        //for example public string $dir;//not initialized - setting it with $obj->dir will trigger __set()
        //the correct is public string $dir = '';
        $class = get_class($this);
        //if (property_exists($class, $property)) {
        //Bug https://bugs.php.net/bug.php?id=78226 is now fixed and there is no longer need of this check
        if (false) {
            //throw new RunTimeException(sprintf(t::_('Attempting to set a dynamic uninitialized typed property $%s on an instance of class %s. All typed properties must be initialized'), $property, $class));
            //can not throw an exception here as the object properties can not be initializen on declaration and will go thorugh the overloading
            if (is_object($value)) {
                $this->{$property} = $value;
            } else {
                throw new RunTimeException(sprintf(t::_('Attempting to set a dynamic uninitialized typed property $%s on an instance of class %s. All typed properties must be initialized'), $property, $class));
            }

        } else {
            throw new RunTimeException(sprintf(t::_('The instance is of class %s which inherits %s which is a strict class. It is not allowed to set new object properties at run time.'), get_class($this), __CLASS__));
        }

    }
}
