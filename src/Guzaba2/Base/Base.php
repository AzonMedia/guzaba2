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
use Guzaba2\Base\Interfaces\BaseInterface;
use Guzaba2\Base\Interfaces\ConfigInterface;
use Guzaba2\Base\Interfaces\ContextAwareInterface;
use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;
use Guzaba2\Base\Interfaces\UsesServicesInterface;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Translator\Translator as t;

//use Guzaba2\Base\Traits\SupportsConfig;
//use Guzaba2\Base\Traits\SupportsObjectInternalId;
//use Guzaba2\Base\Traits\UsesServices;
//use Guzaba2\Base\Traits\StaticStore;
//use Guzaba2\Base\Traits\ContextAware;
use Guzaba2\Base\Traits\BaseTrait;

/**
 * Class Base
 * @package Guzaba2\Base
 * All classes except Kernel and the various exceptions inherit this class.
 * Kernel inherits nothing and the exceptions inherit BaseException
 */
abstract class Base implements BaseInterface
{
//    use SupportsObjectInternalId;
//    use SupportsConfig;
//    use UsesServices;
//    //use StaticStore;//this becomes too expensive to use
//    use ContextAware;

    use BaseTrait;


    protected const CONFIG_DEFAULTS = [
        'services' => [
            'Events',
        ]
    ];

    protected const CONFIG_RUNTIME = [];

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
        //the _before_destruct event will not be created here (as this would fire it for all objects) but if a class needs to have it then it should implement the _before_destruct() method and fire the event there
    }

    /**
     * @param string $property
     * @param $value
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function __set(string $property, /* mixed */ $value): void
    {
        $class = get_class($this);
        //if running on PHP 7.4.0 RC4 or lower see bug:
        // https://bugs.php.net/bug.php?id=78226
        throw new RunTimeException(sprintf(t::_('The instance is of class %s which inherits %s which is a strict class. It is not allowed to set new object properties at run time.'), get_class($this), __CLASS__), 0, NULL, 'f8d21186-d6cd-4bb7-ad56-10bb3e1a3381' );
    }
}
