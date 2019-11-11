<?php
declare(strict_types=1);

namespace Guzaba2\Orm\BlockingStore\NoSql;

use Guzaba2\Orm\Store\Database;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Store\Interfaces\StructuredStore;
use Guzaba2\Base\Exceptions\BadMethodCallException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Translator\Translator as t;
use Ramsey\Uuid\Uuid;

class MongoDB extends Database
{    
    protected const CONFIG_DEFAULTS = [
        'meta_table'    => 'object_meta'
    ];

    protected const CONFIG_RUNTIME = [];

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
     * Returns a unified structure
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
     * Returns the backend storage structure
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

    public static function get_meta_table() : string
    {
        return self::CONFIG_RUNTIME['meta_table'];
    }


    public function get_meta(string $class_name, /* int | string */ $object_id) : array
    {
        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $CR);

        $coll = $Connection::get_tprefix() . self::get_meta_table();

        if ($this->FallbackStore instanceof StructuredStore) {
            $filter = [
                'class'         => $class_name,
                'object_id'     => $object_id
            ];
        } else {
            $filter = [
                'class'         => $class_name,
                'object_uuid'   => $object_id
            ];
        }

        $result = $Connection->query($coll, $filter);

        return $result[0];
    }

    public function get_meta_by_uuid(string $uuid) : array
    {
        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $CR);

        $coll = $Connection::get_tprefix() . self::get_meta_table();
        $filter = ['object_uuid' => $uuid];

        $data = $Connection->query($coll, $filter);

        if (!count($data)) {
            throw new RunTimeException(sprintf(t::_('No meta data is found for object with UUID %s.'), $uuid));
        }

        // $ret['object_id'] = $data[0]['object_id'];
        // $ret['class'] = $data[0]['class'];

        return $data[0];
    }

    protected function update_meta(ActiveRecordInterface $ActiveRecord) : void
    {
        // it can happen to call update_ownership on a record that is new but this can happen if there is save() recursion
        if ($ActiveRecord->is_new() /* &&  !$object->is_in_method_twice('save') */) {
            throw new RunTimeException(sprintf(t::_('Trying to update the meta data of a new object of class "%s". Instead the new obejcts have their metadata created with Mysql::create_meta() method.'), get_class($ActiveRecord)));
        }

        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $CR);

        if ($this->FallbackStore instanceof StructuredStore) {
            $filter = [
                'class'         => get_class($ActiveRecord),
                'object_id'     => $ActiveRecord->get_id()
            ];
        } else {
            $filter = [
                'class'         => get_class($ActiveRecord),
                'object_uuid'   => $ActiveRecord->get_uuid()
            ];
        }

        $data = [
            'object_last_update_microtime'  => (int) microtime(TRUE) * 1000000
        ];

        $Connection->update($filter, $Connection::get_tprefix() . self::get_meta_table(), $data);
    }

    /**
     * Creates meta data
     *
     * @param ActiveRecordInterface $ActiveRecord
     * @param string $uuid
     * if $this->FallbackStore instanceof StructuredStore (Mysql) the uuid is already created; use the same uuid
     * @return string UUID
     * @throws \Exception
     */
    protected function create_meta(ActiveRecordInterface $ActiveRecord, string $uuid = NULL) : void
    {
        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $CR);
        $meta_table = $Connection::get_tprefix() . self::get_meta_table();

        $object_create_microtime = (int) microtime(TRUE) * 1000000;

        $data = [
            'object_uuid'                   => $uuid,
            'class'                         => get_class($ActiveRecord),
            'object_create_microtime'       => $object_create_microtime,
            'object_last_update_microtime'  => $object_create_microtime,
        ];

        if ($this->FallbackStore instanceof StructuredStore) {
            $data['object_id'] = $ActiveRecord->get_id();
        }

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
    public function update_record(ActiveRecordInterface $ActiveRecord) : string
    {
        /** @var ActiveRecord $ActiveRecord */
        if ($this->FallbackStore instanceof StructuredStore) {
            // Saves record in fallback and gets uuid
            $uuid = $this->FallbackStore->update_record($ActiveRecord);
        } elseif ($ActiveRecord->is_new()) {
            $uuid = Uuid::uuid4()->toString();
        } else {
            $uuid = $ActiveRecord->get_uuid();
        }

        $record_data = $ActiveRecord->get_record_data();

        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $CR);

        if ($ActiveRecord->is_new()) {
            $this->create_meta($ActiveRecord, $uuid);
        } else {
            $this->update_meta($ActiveRecord);
        }

        if ($this->FallbackStore instanceof StructuredStore) {
            $main_index = $ActiveRecord->get_primary_index_columns();

            $filter = [];
            foreach ($main_index as $field_name) {
                $filter[$field_name] = $record_data[$field_name];
            }
        } else {
            $filter = ['object_uuid' => $uuid];
            $ActiveRecord->update_primary_index($uuid);
        }

        $field_names_arr = $ActiveRecord->get_property_names();
        $record_data_to_save = [];
        foreach ($field_names_arr as $field) {
            if (!isset($filter[$field])) {
                $record_data_to_save[$field] = $record_data[$field];
            }
        }

        // insert or update record
        $Connection->update($filter, $Connection::get_tprefix().$ActiveRecord::get_main_table(), $record_data_to_save, TRUE);

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
        $ret = [];
        //lookup in DB

        /** @var MongoDBConnection $Connection */
        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $CR);

        // UUID is NEVER provided

        $coll = $Connection::get_tprefix() . $class::get_main_table();

        $data = $Connection->query($coll, $index);

        if (count($data)) {
            if ($this->FallbackStore instanceof StructuredStore) {
                $primary_index = $class::get_index_from_data($data[0]);

                if (is_null($primary_index)) {
                    throw new RunTimeException(sprintf(t::_('The primary index for class %s is not found in the retreived data.'), $class));
                }
                if (count($primary_index) > 1) {
                    throw new RunTimeException(sprintf(t::_('The class %s has compound index and can not have meta data.'), $class));
                }
            } else {
                $primary_index[0] = $data[0]['object_uuid'];
            }

            $ret['meta'] = $this->get_meta($class, current($primary_index));
            $ret['data'] = $data[0];
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
        if ($this->FallbackStore instanceof StructuredStore) {
            $this->FallbackStore->remove_record($ActiveRecord);
        }

        $Connection = static::get_service('ConnectionFactory')->get_connection($this->connection_class, $CR);
        $primary_index = $ActiveRecord->get_primary_index();
        $uuid = $ActiveRecord->get_uuid();

        $Connection->delete($primary_index, $Connection::get_tprefix() . $ActiveRecord::get_main_table());
        $Connection->delete(['object_uuid' => $uuid], $Connection::get_tprefix() . self::get_meta_table());
    }

}
