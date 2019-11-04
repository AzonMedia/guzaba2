<?php

namespace Guzaba2\Orm\Store\Nosql;

use Azonmedia\Glog\Application\RedisConnection;
use Guzaba2\Base\Exceptions\BadMethodCallException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Database\Nosql\Redis\ConnectionCoroutine;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Store\Database;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Store\Interfaces\StructuredStore;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Orm\Store\NullStore;
use Ramsey\Uuid\Uuid;

class Redis extends Database
{
    /**
     * @var StoreInterface|null
     */
    protected $FallbackStore;

    protected $connection_class;

    public function __construct(StoreInterface $FallbackStore, string $connection_class)
    {
        parent::__construct();
        $this->FallbackStore = $FallbackStore ?? new NullStore();
        $this->connection_class = $connection_class;
    }

    /**
     * @param string $class
     * @return array
     * @throws BadMethodCallException
     * @throws RunTimeException
     */
    public function get_unified_columns_data(string $class) : array
    {
        // TODO check deeper for a structured store
        if ($this->FallbackStore instanceof StructuredStore) {
            $ret = $this->FallbackStore->get_unified_columns_data($class);
        } else {
            // $class is instance of Guzaba2\Orm\ActiveRecord
            if (!method_exists($class, 'get_structure')) {
                throw new BadMethodCallException(sprintf(t::_('Class %s requires a get_structure() method'), $class));
            }

            $ret = $class::get_structure();

            if (!$ret) {
                throw new RunTimeException(sprintf(t::_('Empty structure provided in class %s'), $class));
            }
        }

        return $ret;
    }

    /**
     * @param string $class
     * @return array
     * @throws BadMethodCallException
     * @throws RunTimeException
     */
    public function get_storage_columns_data(string $class) : array
    {
        if ($this->FallbackStore instanceof StructuredStore) {
            $ret = $this->FallbackStore->get_storage_columns_data($class);
        } else {
            // $class is instance of Guzaba2\Orm\ActiveRecord
            if (!method_exists($class, 'get_structure')) {
                throw new BadMethodCallException(sprintf(t::_('Class %s requires a get_structure() method'), $class));
            }

            $ret = $class::get_structure();

            if (!$ret) {
                throw new RunTimeException(sprintf(t::_('Empty structure provided in class %s'), $class));
            }
        }

        return $ret;
    }

    /**
     * @param ActiveRecordInterface $ActiveRecord
     * @return string
     * @throws RunTimeException
     * @throws \Exception
     */
    public function update_record(ActiveRecordInterface $ActiveRecord) : string
    {
        /** @var ActiveRecord $ActiveRecord */
        if ($this->FallbackStore instanceof StructuredStore) {
            // Saves record in fallback and gets uuid
            $uuid = $this->FallbackStore->update_record($ActiveRecord);
        } elseif ($ActiveRecord->is_new()) {
            $uuid = $this->create_uuid();
        } else {
            $uuid = $ActiveRecord->get_uuid();
        }

        $record_data = $ActiveRecord->get_record_data();

        /** @var ConnectionCoroutine $Connection */
        $Connection = self::ConnectionFactory()->get_connection($this->connection_class, $CR);

        // Create or update
        foreach ($record_data as $key => $value) {
            $Connection->hSet($uuid, $key, $value);
        }

        if ($Connection->getExpiryTime()) {
            $Connection->expire($uuid, $Connection->getExpiryTime());
        }

        // Add "class-index" association ot the uuid of the object
        if ($this->FallbackStore instanceof StructuredStore) {
            $index = $ActiveRecord->get_primary_index();
            $redis_id_key = $this->create_active_record_id($ActiveRecord, $index);
            $Connection->set($redis_id_key, $uuid);
            if ($Connection->getExpiryTime()) {
                $Connection->expire($redis_id_key, $Connection->getExpiryTime());
            }
        }

        // Meta
        $metakey = $uuid . ':meta';
        $time = time();
        if (!$Connection->exists($metakey)) {
            $Connection->hSet($metakey, 'class_name', get_class($ActiveRecord));
            $Connection->hSet($metakey, 'object_create_microtime', $time);
            $Connection->hSet($metakey, 'object_uuid', $uuid);
            if ($this->FallbackStore instanceof StructuredStore) {
                $Connection->hSet($metakey, 'object_id', $ActiveRecord->get_id());
            }
        }
        $Connection->hSet($metakey, 'object_last_update_microtime', $time);
        if ($Connection->getExpiryTime()) {
            $Connection->expire($metakey, $Connection->getExpiryTime());
        }

        return $uuid;
    }

    /**
     * Fetch active record data by primary index
     * The Index can have multiple fields
     *
     * @param string $class
     * @param array $index
     * @return array
     * @throws \Guzaba2\Orm\Exceptions\RecordNotFoundException
     */
    public function &get_data_pointer(string $class, array $index) : array
    {
        /** @var ConnectionCoroutine $Connection */
        $Connection = self::ConnectionFactory()->get_connection($this->connection_class, $CR);

        $primary_index_columns = $class::get_primary_index_columns();
        $id_column = reset($primary_index_columns);
        if (!isset($index['object_uuid']) && !isset($index[$id_column])) {
            return $this->FallbackStore->get_data_pointer($class, $index);
        }

        if (isset($index['object_uuid'])) {
            $uuid = $index['object_uuid'];
        } else {
            $redis_id_key = $this->create_class_id($class, $index);
            $uuid = $Connection->get($redis_id_key);
        }

        if (strlen($uuid) && !$Connection->exists($uuid)) {
            return $this->FallbackStore->get_data_pointer($class, $index);
        }

        $result = $Connection->hGetAll($uuid);
        if (empty($result)) {
            return $this->FallbackStore->get_data_pointer($class, $index);
        }

        $meta = $this->get_meta($class, $index[$id_column]);
        $return = ['data' => $result, 'meta' => $meta];

        return $return;
    }
    
    public function get_meta(string $class_name, int $object_id) : array
    {
        $Connection = self::ConnectionFactory()->get_connection($this->connection_class, $TCR);
        $redis_id_key = $this->create_class_id($class_name, [$object_id]);
        $uuid = $Connection->get($redis_id_key);
        $metakey = $uuid . ':meta';

        if (!$Connection->exists($metakey)) {
            return $this->FallbackStore->get_meta($class_name, $object_id);
        }

        $result = $Connection->hGetAll($metakey);

        foreach ($result as $key => $value) {
            if (is_numeric($value)) {
                $result[$key] = (int) $value;
            }
        }

        return $result;
    }

    public function &get_data_pointer_for_new_version(string $class, array $primary_index) : array
    {
        return $this->get_data_pointer($class, $primary_index);
    }

    public function there_is_pointer_for_new_version(string $class, array $primary_index) : bool
    {
        return FALSE;
    }

    public function free_pointer(ActiveRecordInterface $ActiveRecord) : void
    {
    }

    public function debug_get_data() : array
    {
        return [];
    }

    public function get_meta_by_uuid(string $uuid) : array
    {
        $metakey = $uuid . ':meta';
        $Connection = self::ConnectionFactory()->get_connection($this->connection_class, $CR);
        if ($Connection->exists($metakey)) {
            return $this->FallbackStore->get_meta_by_uuid($uuid);
        }

        $result = $Connection->hGetAll($metakey);
        return $result;
    }

    /**
     * Creates a unique id for an object of the ActiveRecord type so it can be easily retrieved later on
     *
     * @param ActiveRecordInterface $ActiveRecord
     * @param array $index
     * @return string
     */
    protected function create_active_record_id(ActiveRecordInterface $ActiveRecord, array $index): string
    {
        return  $this->create_class_id(get_class($ActiveRecord), $index);
    }

    /**
     * Creates a unique id for certain class so it can be easily retrieved later on
     *
     * @param string $class
     * @param array $index
     * @return string
     */
    protected function create_class_id(string $class, array $index): string
    {
        if (empty($index)) {
            throw new \RuntimeException('Empty index provided for class ' . $class);
        }

        if (count($index) == 1) {
            return $class . ':' . reset($index);
        }

        ksort($index);
        return $class . ':' . json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
    }

    /**
     * Generates a uuid
     *
     * @param int $tries Used for checking if we're in an infinite loop
     * @return string
     * @throws \Exception
     */
    protected function create_uuid($tries = 0)
    {
        $uuid = Uuid::uuid4();

        // Checks if the uuid exists in the db; such occurences are rare
        /** @var ConnectionCoroutine $Connection */
        $Connection = self::ConnectionFactory()->get_connection($this->connection_class, $CRR);
        if ($Connection->exists($uuid)) {
            if ($tries > 7) {
                throw new \RuntimeException('Cannot create a unique uuid after 7 attempts');
            }
            $uuid = $this->create_uuid(++$tries);
        }

        return $uuid;
    }

    /**
     * Removes an active record data from the Store
     * @param ActiveRecordInterface $ActiveRecord
     * @throws RunTimeException
     */
    public function remove_record(ActiveRecordInterface $ActiveRecord): void
    {
        $this->FallbackStore->remove_record($ActiveRecord);
        $uuid = $ActiveRecord->get_uuid();
        $id = $ActiveRecord->get_id();
        $class_id = $this->create_class_id(get_class($ActiveRecord), [$id]);
        /** @var ConnectionCoroutine $Connection */
        $Connection = self::ConnectionFactory()->get_connection($this->connection_class, $TCR);
        $Connection->del($uuid);
        $Connection->del($uuid . ':meta');
        $Connection->del($class_id);
    }
}
