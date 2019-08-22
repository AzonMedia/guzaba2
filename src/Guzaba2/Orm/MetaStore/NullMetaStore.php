<?php


namespace Guzaba2\Orm\MetaStore;

use Guzaba2\Base\Exceptions\InvalidArgumentException;
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
    public function get_meta_data(string $key) : ?array
    {
        throw new RecordNotFoundException(sprintf(t::_('No metadata for record of class %s with lookup index %s is found.'), $class, $lookup_index));
        return NULL;
    }

    /**
     * @param ActiveRecord $ActiveRecord
     * @return array|null
     */
    public function get_meta_data_by_object(ActiveRecord $ActiveRecord) : ?array
    {
        $key = self::get_key($ActiveRecord);
        $data = $this->get_update_data($key);
        return $data;
    }

    /**
     * @param string $key
     * @return float|null
     * @throws RecordNotFoundException
     */
    public function get_last_update_time(string $key) : ?float
    {
        $ret = NULL;
        $data = $this->get_meta_data($key);
        if (isset($data['updated_microtime'])) {
            $ret = $data['updated_microtime'];
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
        $key = self::get_key($ActiveRecord);
        return $this->get_last_update_time($key);
    }


    /**
     * To be invoked when a record is updated or when a record is not present in the SwooleTable and was accessed and the lock information needs to be updated.
     * @param string $key
     * @param array $data
     * @throws InvalidArgumentException
     */
    public function set_update_data(string $key, array $data) : void
    {
        self::validate_data($data);

        //does nothing
    }

    /**
     * @param ActiveRecord $activeRecord
     * @param array $data
     * @throws InvalidArgumentException
     */
    public function set_update_data_by_object(ActiveRecord $activeRecord, array $data) : void
    {
        $key = self::get_key($activeRecord);
        $this->set_update_data($key, $data);
    }
}
