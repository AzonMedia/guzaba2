<?php


namespace Guzaba2\Orm\MetaStore;

use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;

class NullMetaStore extends MetaStore
{
    /**
     * NullMetaStore constructor.
     * @param StoreInterface|null $FallbackStore
     * @throws InvalidArgumentException
     */
    public function __construct(?StoreInterface $FallbackStore = NULL)
    {
        parent::__construct();
        if ($FallbackStore) {
            throw new InvalidArgumentException(sprintf(t::_('ORM Meta Store %s does not support fallback store.'), __CLASS__));
        }
    }

    /**
     * @param string $key
     * @return array|null
     * @throws RecordNotFoundException
     */
    public function get_meta_data(string $class, array $primary_index) : ?array
    {
        throw new RecordNotFoundException(sprintf(t::_('No metadata for class %s, object_id %s was found.'), $class, print_r($primary_index, TRUE)));
        return NULL;
    }

    /**
     * @param ActiveRecord $ActiveRecord
     * @return array|null
     */
    public function get_meta_data_by_object(ActiveRecord $ActiveRecord) : ?array
    {
        $key = self::get_key_by_object($ActiveRecord);
        $data = $this->get_update_data($key);
        return $data;
    }

    /**
     * @param string $key
     * @return float|null
     * @throws RecordNotFoundException
     */
    public function get_last_update_time(string $class, array $primary_index) : ?float
    {
        $ret = NULL;
        $key = self::get_key($class, $primary_index);
        $data = $this->get_meta_data($key);
        if (isset($data['object_last_update_microtime'])) {
            $ret = $data['object_last_update_microtime'];
        }
        return $ret;
    }

    /**
     * @param ActiveRecord $ActiveRecord
     * @return float|null
     * @throws RecordNotFoundException
     */
    public function get_last_update_time_by_object(ActiveRecord $ActiveRecord) : ?float
    {
        $key = self::get_key_by_object($ActiveRecord);
        return $this->get_last_update_time($key);
    }


    /**
     *
     * @param string $key
     * @param array $data
     * @throws InvalidArgumentException
     */
    public function set_meta_data(string $class, array $primary_index, array $data) : void
    {

        //it is expected to be called - do nothing
    }

    /**
     * @param ActiveRecord $activeRecord
     * @param array $data
     * @throws InvalidArgumentException
     */
    public function set_meta_data_by_object(ActiveRecord $ActiveRecord, array $data) : void
    {
        $key = self::get_key_by_object($ActiveRecord);
        $this->set_update_data($key, $data);
    }
}
