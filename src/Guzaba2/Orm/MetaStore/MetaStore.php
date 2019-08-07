<?php


namespace Guzaba2\Orm\MetaStore;

use Guzaba2\Base\Base;
use Guzaba2\Orm\MetaStore\Interfaces\MetaStoreInterface;

abstract class MetaStore extends Base implements MetaStoreInterface
{
    /**
     * Validates the provided lock data.
     * Throws InvalidArgumentException on data error.
     * @param array $data
     * @throws InvalidArgumentException
     */
    protected static function validate_data(array $data) : void
    {
        foreach ($data as $key=>$value) {
            if (!isset(self::DATA_STRUCT[$key])) {
                throw new InvalidArgumentException(sprintf(t::_('The provided lock data contains an unsupported key %s.'), $key));
            } elseif (gettype($value) !== self::DATA_STRUCT[$key]) {
                throw new InvalidArgumentException(sprintf(t::_('The provided lock data contains key %s which has a value of type % while it must be of type %s'), $key, gettype($value), self::DATA_STRUCT[$key]));
            }
        }
        if (count($data) !== count(self::DATA_STRUCT)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided data contains less keys %s than the expected in DATA_STRUCT %s.'), count($data), count(self::DATA_STRUCT)));
        }
    }
}
