<?php


namespace Guzaba2\Orm\MetaStore;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\MetaStore\Interfaces\MetaStoreInterface;
use Guzaba2\Orm\Store\Store;
use Guzaba2\Translator\Translator as t;

abstract class MetaStore extends Base implements MetaStoreInterface
{
    protected const KEY_SEPARATOR = '|';
    protected const LOOKUP_INDEX_LENGTH = 36;

    /**
     * Validates the provided lock data.
     * Throws InvalidArgumentException on data error.
     * @param array $data
     * @throws InvalidArgumentException
     */
    protected static function validate_data(array $data): void
    {
        foreach ($data as $key => $value) {
            if (!isset(self::DATA_STRUCT[$key])) {
                throw new InvalidArgumentException(sprintf(t::_('The provided meta data contains an unsupported key %s.'), $key));
            } elseif (gettype($value) !== self::DATA_STRUCT[$key]) {
                throw new InvalidArgumentException(sprintf(t::_('The provided meta data contains key %s which has a value of type %s while it must be of type %s'), $key, gettype($value), self::DATA_STRUCT[$key]));
            }
        }
        
        if (count($data) !== count(self::DATA_STRUCT)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided meta contains less keys %s than the expected in DATA_STRUCT %s.'), count($data), count(self::DATA_STRUCT)));
        }
    }

    public static function get_key(string $class, array $primary_index) : string
    {
        $lookup_index = Store::form_lookup_index($primary_index);

        // uuid length = 36 simbols
        // if $lookup_index length == 36 => this is uuid; it is unique for the data base => use crc32 for the class
        // else => this is NOT uuid and is not unique; collisions may occur => use md5 for the class

        if (strlen($lookup_index) >= self::LOOKUP_INDEX_LENGTH) {
            $class_hash = crc32($class);
        } else {
            $class_hash = md5($class);

        }
        return $class_hash.self::KEY_SEPARATOR.$lookup_index;
    }

    public static function get_key_by_object(ActiveRecordInterface $ActiveRecord) : string
    {
        return self::get_key(get_class($ActiveRecord), $ActiveRecord->get_primary_index());
    }
}
