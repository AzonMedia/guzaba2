<?php
declare(strict_types=1);

namespace Guzaba2\Orm\BlockingStore\NoSql;

use Guzaba2\Orm\Store\Database;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Store\Interfaces\StructuredStoreInterface;
use Guzaba2\Base\Exceptions\BadMethodCallException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Translator\Translator as t;
use Ramsey\Uuid\Uuid;
use Guzaba2\Kernel\Kernel;

class MongoDB extends Database
{    
    protected const CONFIG_DEFAULTS = [
        'meta_table'    => 'object_meta'
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * @var string
     */
    protected $connection_class;

    public function __construct(StoreInterface $FallbackStore, string $connection_class)
    {
        parent::__construct();
        $this->FallbackStore = $FallbackStore ?? new NullStore();
        $this->connection_class = $connection_class;
    }

    public static function get_meta_table() : string
    {
        return self::CONFIG_RUNTIME['meta_table'];
    }


    public function get_meta(string $class_name, /* int | string */ $object_id) : array
    {
        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $CR);

        $coll = $Connection::get_tprefix() . self::get_meta_table();

        if ($this->FallbackStore instanceof StructuredStoreInterface) {
            $filter = [
                'meta_class_name'   => $class_name,
                'meta_object_id'    => $object_id
            ];
        } else {
            $filter = [
                'meta_class_name'   => $class_name,
                'meta_object_uuid'  => $object_id
            ];
        }

        $result = $Connection->query($coll, $filter);

        if (empty($result) && $this->FallbackStore instanceof StructuredStoreInterface) {
            $result[0] = $this->FallbackStore->get_meta($class_name, $object_id);
            $this->create_meta_from_array($result[0]);
        }

        return $result[0];
    }

    public function get_meta_by_uuid(string $uuid) : array
    {
        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $CR);

        $coll = $Connection::get_tprefix() . self::get_meta_table();
        $filter = ['meta_object_uuid' => $uuid];

        $result = $Connection->query($coll, $filter);

        if (empty($result) && $this->FallbackStore instanceof StructuredStoreInterface) {
            $result[0] = $this->FallbackStore->get_meta_by_uuid($uuid);
            $this->create_meta_from_array($result[0]);
        }

        if (!count($result)) {
            throw new RunTimeException(sprintf(t::_('No meta data is found for object with UUID %s.'), $uuid));
        }

        return $result[0];
    }

    protected function update_meta(ActiveRecordInterface $ActiveRecord) : array
    {
        // it can happen to call update_ownership on a record that is new but this can happen if there is save() recursion
        if ($ActiveRecord->is_new() /* &&  !$object->is_in_method_twice('save') */) {
            throw new RunTimeException(sprintf(t::_('Trying to update the meta data of a new object of class "%s". Instead the new obejcts have their metadata created with Mysql::create_meta() method.'), get_class($ActiveRecord)));
        }

        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $CR);

        if ($this->FallbackStore instanceof StructuredStoreInterface) {
            $filter = [
                'meta_class_name'   => get_class($ActiveRecord),
                'meta_object_id'    => $ActiveRecord->get_id()
            ];
        } else {
            $filter = [
                'meta_class_name'   => get_class($ActiveRecord),
                'meta_object_uuid'  => $ActiveRecord->get_uuid()
            ];
        }

        $data = [
            'object_last_update_microtime'  => (int) microtime(TRUE) * 1000000
        ];

        $Connection->update($filter, $Connection::get_tprefix() . self::get_meta_table(), $data);
        return $this->get_meta(get_class($ActiveRecord), $ActiveRecord->get_id() );
    }

    /**
     * Creates meta data
     *
     * @param ActiveRecordInterface $ActiveRecord
     * @param string $uuid
     * if $this->FallbackStore instanceof StructuredStoreInterface (Mysql) the uuid is already created; use the same uuid
     * @return string UUID
     * @throws \Exception
     */
    protected function create_meta(ActiveRecordInterface $ActiveRecord, string $uuid = NULL) : array
    {
        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $CR);
        $meta_table = $Connection::get_tprefix() . self::get_meta_table();

        $object_create_microtime = (int) microtime(TRUE) * 1000000;

        $data = [
            'meta_object_uuid'                   => $uuid,
            'meta_class_name'                    => get_class($ActiveRecord),
            'meta_object_create_microtime'       => $object_create_microtime,
            'meta_object_last_update_microtime'  => $object_create_microtime,
        ];

        if ($this->FallbackStore instanceof StructuredStoreInterface) {
            $data['meta_object_id'] = $ActiveRecord->get_id();
        }

        $Connection->insert(
            $meta_table, 
            $data
        );

        return $data;
    }

    /**
     * Creates meta data with array param
     *
     * @param array $meta_data
     * @return void
     * @throws \Exception
     */
    protected function create_meta_from_array(array $meta_data) : void
    {
        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $CR);
        $meta_table = $Connection::get_tprefix() . self::get_meta_table();

        $object_create_microtime = (int) microtime(TRUE) * 1000000;

        $data = [
            'meta_object_uuid'                          => $meta_data['meta_object_uuid'],
            'meta_class_name'                           => $meta_data['meta_class_name'],
            'meta_object_id'                            => $meta_data['meta_object_id'],
            'meta_object_create_microtime'              => $object_create_microtime,
            'meta_object_last_update_microtime'         => $object_create_microtime,
            'meta_object_create_transaction_id'         => 0,
            'meta_object_last_update_transaction_id'    => 0,
        ];

        $Connection->insert(
            $meta_table,
            $data
        );
    }

    /**
     * @param ActiveRecordInterface $ActiveRecord
     * @return string UUID
     * @throws RunTimeException
     * @throws \Exception
     */
    public function update_record(ActiveRecordInterface $ActiveRecord) : array
    {
        /** @var ActiveRecord $ActiveRecord */
        if ($this->FallbackStore instanceof StructuredStoreInterface) {
            // Saves record in fallback and gets uuid
            //$uuid = $this->FallbackStore->update_record($ActiveRecord);
            $all_data = $this->FallbackStore->update_record($ActiveRecord);
            $uuid = $all_data['meta']['meta_object_uuid'];
            $record_data = $all_data['data'];
        } elseif ($ActiveRecord->is_new()) {
            $uuid = Uuid::uuid4()->toString();
        } else {
            $uuid = $ActiveRecord->get_uuid();
        }

        if (empty($record_data)) {
            $record_data = $ActiveRecord->get_record_data();
        }

        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $CR);

        if ($ActiveRecord->is_new()) {
            $meta_data = $this->create_meta($ActiveRecord, $uuid);
        } else {
            $meta_data = $this->update_meta($ActiveRecord);
        }

        if ($this->FallbackStore instanceof StructuredStoreInterface) {
            $main_index = $ActiveRecord->get_primary_index_columns();

            $filter = [];
            foreach ($main_index as $field_name) {
                $filter[$field_name] = $record_data[$field_name];
            }
        } else {
            $filter = ['meta_object_uuid' => $uuid];
            $ActiveRecord->update_primary_index($uuid);
        }

        $field_names_arr = $ActiveRecord::get_property_names();
        $record_data_to_save = [];
        foreach ($field_names_arr as $field) {
            if (!isset($filter[$field])) {
                $record_data_to_save[$field] = $record_data[$field];
            }
        }

        // insert or update record
        $Connection->update($filter, $Connection::get_tprefix().$ActiveRecord::get_main_table(), $record_data_to_save, TRUE);

        //return $uuid;
        return ['data' => $record_data, 'meta' => $meta_data ];
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
        $ret = [];
        //lookup in DB

        $data = $this->get_data_by($class, $index); 

        if (count($data)) {
            if ($this->FallbackStore instanceof StructuredStoreInterface) {
                $primary_index = $class::get_index_from_data($data[0]);

                if (is_null($primary_index)) {
                    throw new RunTimeException(sprintf(t::_('The primary index for class %s is not found in the retreived data.'), $class));
                }
                if (count($primary_index) > 1) {
                    throw new RunTimeException(sprintf(t::_('The class %s has compound index and can not have meta data.'), $class));
                }
            } else {
                $primary_index[0] = $data[0]['meta_object_uuid'];
            }

            $ret['meta'] = $this->get_meta($class, current($primary_index));
            $ret['data'] = $data[0];
        } elseif ($this->FallbackStore instanceof StructuredStoreInterface) {
            return $this->FallbackStore->get_data_pointer($class, $index);
            get_data_pointer((string) $class, (array) $index);
        } else {
            $this->throw_not_found_exception($class, self::form_lookup_index($index));
        }

        return $ret;
    }
    
    public function &get_data_pointer_for_new_version(string $class, array $primary_index) : array
    {
        $data = $this->get_data_pointer($class, $primary_index);
        // TODO fill modified
        $data['modified'] = [];
        return $data;
    }

    public function there_is_pointer_for_new_version(string $class, array $primary_index) : bool
    {
        //this store doesnt use pointers
        return FALSE;
    }

    public function free_pointer(ActiveRecordInterface $ActiveRecord) : void
    {
        //does nothing
    }

    public function debug_get_data() : array
    {
        return [];
    }

    public function remove_record(ActiveRecordInterface $ActiveRecord): void
    {
        if ($this->FallbackStore instanceof StructuredStoreInterface) {
            $this->FallbackStore->remove_record($ActiveRecord);
        }

        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $CR);
        $primary_index = $ActiveRecord->get_primary_index();
        $uuid = $ActiveRecord->get_uuid();

        $Connection->delete($primary_index, $Connection::get_tprefix() . $ActiveRecord::get_main_table());
        $Connection->delete(['meta_object_uuid' => $uuid], $Connection::get_tprefix() . self::get_meta_table());
    }

    public function get_data_by(string $class, array $index, int $offset = 0, int $limit = 0, bool $use_like = FALSE, ?string $sort_by = NULL, bool $sort_desc = FALSE, ?int &$total_found_rows = NULL) : iterable
    {
        if ($this->FallbackStore instanceof StructuredStoreInterface) {
            $ret = $this->FallbackStore->get_data_by($class, $index, $offset, $limit, $use_like, $sort_by, $sort_desc, $total_found_rows);
            return $ret;
        }

        /** @var MongoDBConnection $Connection */
        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $CR);

        // UUID is NEVER provided

        $coll = $Connection::get_tprefix() . $class::get_main_table();

        $data = $Connection->query($coll, $index);

        if (!count($data)) {
            // $this->throw_not_found_exception($class, self::form_lookup_index($index));
        }

        return $data;
    } 

}
