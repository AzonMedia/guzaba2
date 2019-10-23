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

namespace Guzaba2\Base\Traits;

use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;

/**
 * Trait SupportsObjectInternalId
 *
 */
trait SupportsObjectInternalId
{



    /**
     * Unique ID
     * @var string
     */
    protected string $object_internal_id = '';

    /**
     * Sets the object internal ID. To be called from the base class constructor.
     */
    protected function set_object_internal_id() : void
    {
        $this->object_internal_id = self::generate_unique_id(ObjectInternalIdInterface::UNIQUE_ID_LENGTH);
    }

    /**
     * Returns the unique object id.
     * @return string
     */
    public function get_object_internal_id() : string
    {
        return $this->object_internal_id;
    }

    /**
     * Creates a unique ID based on the microtime and a random string
     * @param int $length The length of the random string part of the ID
     * @return string
     */
    protected static function generate_unique_id(int $length) : string
    {
        $ret = microtime(TRUE).'_'.self::generate_random_string($length);
        return $ret;
    }

    /**
     * @param int $length
     * @return string
     */
    protected static function generate_random_string(int $length) : string
    {
        $str = '';
        static $list_length;
        if ($list_length === NULL) {
            $list_length = strlen(ObjectInternalIdInterface::DEFAULT_CHARACTERS_LIST);
        }
        for ($aa = 0; $aa < $length; $aa++) {
            $str .= ObjectInternalIdInterface::DEFAULT_CHARACTERS_LIST[mt_rand(0, $list_length-1)];
        }

        return $str;
    }
}
