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
        return $class.self::KEY_SEPARATOR.Store::form_lookup_index($primary_index);
    }

    public static function get_key_by_object(ActiveRecordInterface $ActiveRecord) : string
    {
        return self::get_key(get_class($ActiveRecord), $ActiveRecord->get_primary_index());
    }
}
