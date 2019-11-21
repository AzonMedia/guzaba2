<?php

namespace Guzaba2\Orm\Store\Nosql;

use Azonmedia\Glog\Application\RedisConnection;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Database\Nosql\Redis\ConnectionCoroutine;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Store\Database;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Store\Interfaces\StructuredStoreInterface;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Orm\Store\NullStore;
use Ramsey\Uuid\Uuid;

class Redis extends Database
{

    /**
     * @var string
     */
    protected string $connection_class;

    public function __construct(StoreInterface $FallbackStore, string $connection_class)
    {
        parent::__construct();
        $this->FallbackStore = $FallbackStore ?? new NullStore();
        $this->connection_class = $connection_class;
    }


    /**
     * @return ConnectionInterface
     * @throws RunTimeException
     */
    protected function get_connection() : ConnectionInterface
    {
        if (Coroutine::inCoroutine()) {
            $connection_class = $this->connection_class;
        } else {
            // TODO implement non coroutine redis class
            throw new RunTimeException(sprintf(t::_('Only coroutine redis class connection available. Non coroutine TBD')));
        }

        return static::get_service('ConnectionFactory')->get_connection($connection_class);
    }

    /**
     * @param ActiveRecordInterface $ActiveRecord
     * @return string
     * @throws RunTimeException
     * @throws \Exception
     */
    public function update_record(ActiveRecordInterface $ActiveRecord) : array
    {
        /** @var ActiveRecord $ActiveRecord */
        if ($this->FallbackStore instanceof StructuredStoreInterface) {
            // Saves record in fallback and gets uuid
            $all_data = $this->FallbackStore->update_record($ActiveRecord);
            $uuid = $all_data['meta']['object_uuid'];
            $record_data = $all_data['data'];
        } elseif ($ActiveRecord->is_new()) {
            $uuid = $this->create_uuid();
        } else {
            $uuid = $ActiveRecord->get_uuid();
        }

        if (empty($record_data)) {
            $record_data = $ActiveRecord->get_record_data();
        }

        /** @var ConnectionCoroutine $Connection */
        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $CR);

        // Create or update
        foreach ($record_data as $key => $value) {
            $Connection->hSet($uuid, $key, $value);
        }

        if ($Connection->getExpiryTime()) {
            $Connection->expire($uuid, $Connection->getExpiryTime());
        }

        // Add "class-index" association ot the uuid of the object
        if ($this->FallbackStore instanceof StructuredStoreInterface) {
            $index = $ActiveRecord->get_primary_index();
            $redis_id_key = $this->create_active_record_id($ActiveRecord, $index);
            $Connection->set($redis_id_key, $uuid);
            if ($Connection->getExpiryTime()) {
                $Connection->expire($redis_id_key, $Connection->getExpiryTime());
            }
        }

        // Meta
        $metakey = $uuid . ':meta';
        //$time = time();
        if (!$Connection->exists($metakey)) {
            /*
            $Connection->hSet($metakey, 'class_name', get_class($ActiveRecord));
            $Connection->hSet($metakey, 'object_create_microtime', $time);
            $Connection->hSet($metakey, 'object_uuid', $uuid);
            if ($this->FallbackStore instanceof StructuredStoreInterface) {
                $Connection->hSet($metakey, 'object_id', $ActiveRecord->get_id());
            }
            */
            if (isset($all_data)) { //it is coming from a fallback
                $meta_data = $all_data['meta'];
            } else {
                $object_create_microtime = (int) microtime(TRUE) * 1000000;
                $meta_data = [
                    'class_name'                => get_class($ActiveRecord),
                    'object_create_microtime'   => $object_create_microtime,
                    'object_uuid'               => $uuid,
                ];
//                $Connection->hSet($metakey, 'class_name', get_class($ActiveRecord));
//                $Connection->hSet($metakey, 'object_create_microtime', $microtime);
//                $Connection->hSet($metakey, 'object_uuid', $uuid);
//                if ($this->FallbackStore instanceof StructuredStoreInterface) {
//                    $Connection->hSet($metakey, 'object_id', $ActiveRecord->get_id());
//                }
            }
            foreach ($meta_data as $meta_key=>$meta_value) {
                $Connection->hSet($metakey, $meta_key, $meta_value);
            }

        }
        $meta_data['object_last_update_microtime'] = $meta_data['object_last_update_microtime'] ?? (int) microtime(TRUE) * 1000000;
        $Connection->hSet($metakey, 'object_last_update_microtime', $meta_data['object_last_update_microtime']);
        if ($Connection->getExpiryTime()) {
            $Connection->expire($metakey, $Connection->getExpiryTime());
        }

        $ret = ['data' => $record_data, 'meta' => $meta_data];

        return $ret;
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

        if (!is_a($class, ActiveRecordInterface::class, TRUE)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided class %s is not a %s.'), $class, ActiveRecordInterface::class));
        }

        /** @var ConnectionCoroutine $Connection */
        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $CR);

        $primary_index_columns = $class::get_primary_index_columns();
        $id_column = reset($primary_index_columns);
        if (!isset($index['object_uuid']) && !isset($index[$id_column])) {
            $ret = $this->FallbackStore->get_data_pointer($class, $index);
            return $ret;
        }

        if (isset($index['object_uuid'])) {
            $uuid = $index['object_uuid'];
        } else {
            $redis_id_key = $this->create_class_id($class, $index);
            $uuid = $Connection->get($redis_id_key);
        }

        if (strlen($uuid) && !$Connection->exists($uuid)) {
            $ret = $this->FallbackStore->get_data_pointer($class, $index);
            return $ret;
        }

        $result = $Connection->hGetAll($uuid);
        if (empty($result)) {
            $ret = $this->FallbackStore->get_data_pointer($class, $index);
            return $ret;
        } else {
            $result = $class::fix_record_data_types($result);
        }

        $meta = $this->get_meta($class, $index[$id_column]);
        $ret = ['data' => $result, 'meta' => $meta];

        return $ret;
    }
    
    public function get_meta(string $class, /*scalar */ $object_id) : array
    {

        if (!is_a($class, ActiveRecordInterface::class, TRUE)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided class %s is not a %s.'), $class, ActiveRecordInterface::class));
        }

        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $TCR);
        $redis_id_key = $this->create_class_id($class, [$object_id]);
        $uuid = $Connection->get($redis_id_key);
        $metakey = $uuid . ':meta';

        if (!$Connection->exists($metakey)) {
            return $this->FallbackStore->get_meta($class, $object_id);
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
        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $CR);
        if (!$Connection->exists($metakey)) {
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
        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $CRR);
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
        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $TCR);
        $Connection->del($uuid);
        $Connection->del($uuid . ':meta');
        $Connection->del($class_id);
    }

    public function get_data_by(string $class, array $index, int $offset = 0, int $limit = 0, bool $use_like = FALSE, string $sort_by = 'none', bool $sort_desc = FALSE) : iterable
    {
        $ret = $this->FallbackStore->get_data_by($class, $index, $offset, $limit, $use_like, $sort_by, $sort_desc);
        return $ret;
    }

    public function get_data_count_by(string $class, array $index, bool $use_like = FALSE) : int
    {
        $ret = $this->FallbackStore->get_data_count_by($class, $index, $use_like);
        return $ret;
    }
}
