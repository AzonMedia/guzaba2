<?php

namespace Guzaba2\Orm\MetaStore;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Orm\MetaStore\Interfaces\MetaStoreInterface;

class SwooleTable extends MetaStore
{
    protected const CONFIG_DEFAULTS = [
        'max_rows'                      => 100000,
        'cleanup_at_percentage_usage'   => 95,//when the cleanup should be triggered
        'cleanup_percentage_records'    => 20,//the percentage of records to be removed
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * @var \Swoole\Table
     */
    protected $SwooleTable;

    /**
     * @var MetaStoreInterface|null
     */
    protected $FallbackMetaStore;

    protected const SWOOLE_TABLE_SIZES = [
        'meta_object_create_microtime'               => 8,
        'meta_object_last_update_microtime'          => 8,
    ];


    /**
     * SwooleTable constructor.
     * @param MetaStoreInterface $FallbackMetaStore
     * @throws RunTimeException
     */
    public function __construct(MetaStoreInterface $FallbackMetaStore)
    {
        parent::__construct();
        if (Coroutine::inCoroutine()) {
            throw new RunTimeException(sprintf(t::_('Instances from %s need to be created before the swoole server is started. This instance is created in a coroutine whcih suggests it is being created inside the request (or other) handler of the server.'), __CLASS__));
        }

        $this->SwooleTable = new \Swoole\Table(static::CONFIG_RUNTIME['max_rows']);
        //the key will be the class name with the index
        //the data consists of last modified microtime, worker id, coroutine id
        //$this->SwooleTable->column('updated_microtime', \Swoole\Table::TYPE_FLOAT);
        //$this->SwooleTable->column('updated_from_worker_id', \Swoole\Table::TYPE_INT);
        //$this->SwooleTable->column('updated_from_coroutine_id', \Swoole\Table::TYPE_INT);
        foreach (self::DATA_STRUCT as $key=>$php_type) {
            $this->SwooleTable->column($key, \Guzaba2\Swoole\Table::TYPES_MAP[$php_type], self::SWOOLE_TABLE_SIZES[$key]);
        }
        $this->SwooleTable->create();

        $this->FallbackMetaStore = $FallbackMetaStore;
    }

    /**
     * Destroys the SwooleTable
     */
    public function __destruct()
    {
        $this->SwooleTable->destroy();
        $this->SwooleTable = NULL;
        parent::__destruct(); // TODO: Change the autogenerated stub
    }

    /**
     * @param string $class
     * @return int|null
     */
    public function get_class_last_update_time(string $class) : ?int
    {
        $ret = NULL;
        try {
            $ret = $this->get_last_update_time($class, []);
        } catch (RecordNotFoundException $Exception) {
        }
        return $ret;
    }
    
    /**
     * Returns data when an instance from a class was last created or modified
     * @param string $class
     */
    public function get_class_meta_data(string $class) : ?array
    {
        $ret = NULL;
        try {
            $ret = $this->get_meta_data($class, []);
        } catch (RecordNotFoundException $Exception) {
        }
        return $ret;
    }

    /**
     * @param string $class
     * @param array $data
     * @throws InvalidArgumentException
     */
    public function set_class_meta_data(string $class, array $data) : void
    {
        $this->set_meta_data($class, [], $data);
    }

    /**
     * @param string $key
     * @return array|null
     */
    public function get_meta_data(string $class, array $primary_index) : ?array
    {
        $key = self::get_key($class, $primary_index);
        //print 'get '.$key.PHP_EOL;
        $data = $this->SwooleTable->get($key);
        if (!$data) {
            $data = $this->FallbackMetaStore->get_meta_data($class, $primary_index);
        }
        return $data;
    }

    /**
     * @param ActiveRecord $ActiveRecord
     * @return array|null
     */
    public function get_meta_data_by_object(ActiveRecord $ActiveRecord) : ?array
    {
        $key = self::get_key_by_object($ActiveRecord);
        $data = $this->get_meta_data(get_class($ActiveRecord), $ActiveRecord->get_primary_index());
        return $data;
    }

    /**
     * @param string $key
     * @return float|null
     */
    public function get_last_update_time(string $class, array $primary_index) : ?int
    {
        $ret = NULL;
        $data = $this->get_meta_data($class, $primary_index);
        if (isset($data['meta_object_last_update_microtime'])) {
            $ret = $data['meta_object_last_update_microtime'];
        }
        return $ret;
    }

    /**
     * @param ActiveRecord $ActiveRecord
     * @return float|null
     */
    public function get_last_update_time_by_object(ActiveRecord $ActiveRecord) : ?int
    {
        return $this->get_last_update_time(get_class($ActiveRecord), $ActiveRecord->get_primary_index());
    }


    /**
     * To be invoked when a record is updated or when a record is not present in the SwooleTable and was accessed and the lock information needs to be updated.
     * @param string $key
     * @param array $data
     * @throws InvalidArgumentException
     */
    public function set_meta_data(string $class, array $primary_index, array $data) : void
    {
        $key = self::get_key($class, $primary_index);
        //print 'set '.$key.PHP_EOL;

        self::validate_data($data);
        $this->SwooleTable->set($key, $data);

        //print_r($this->SwooleTable->get($key));
        if (count($this->SwooleTable) > self::CONFIG_RUNTIME['max_rows'] * (self::CONFIG_RUNTIME['cleanup_at_percentage_usage'] / 100)) {
            //95% usage is reached - cleanup is needed
            //the cleanup cleans more records than just 1 or few... If just a few are cleaned then the cleanup will be invoked much more often
            $this->cleanup();
        }
        $this->FallbackMetaStore->set_meta_data($class, $primary_index, $data);
    }

    /**
     * @param ActiveRecord $activeRecord
     * @param array $data
     * @throws InvalidArgumentException
     */
    public function set_meta_data_by_object(ActiveRecord $ActiveRecord, array $data) : void
    {
        $this->set_meta_data(get_class($ActiveRecord), $ActiveRecord->get_primary_index(), $data);
    }

    /**
     * Used when deleting a record
     *
     * @param string $class
     * @param array $primary_index
     */
    public function remove_meta_data(string $class, array $primary_index): void
    {
        $this->FallbackMetaStore->remove_meta_data($class, $primary_index);

        $key = self::get_key($class, $primary_index);
        $this->SwooleTable->del($key);
    }

    /**
     * Used when deleting a record
     *
     * @param ActiveRecord $ActiveRecord
     */
    public function remove_meta_data_by_object(ActiveRecord $ActiveRecord): void
    {
        $this->remove_meta_data(get_class($ActiveRecord), $ActiveRecord->get_primary_index());
    }
}
