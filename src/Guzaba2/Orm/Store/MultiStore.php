<?php
declare(strict_types=1);
namespace Guzaba2\Orm\Store;

use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Store\Store;

/**
 * Class MultiStore
 * This class writes to multiple backend stores simultaneously (in coroutines).
 * The first provided Store (to the constructor) is also used for reading the ActiveRecord instances.
 * @package Azonmedia\Glog\Store
 */
class MultiStore extends Store
{
    protected $stores = [];

    public function __construct(StoreInterface $Store)
    {
        parent::__construct();

        $this->add_store_backend($StoreBackend);
    }

    public function add_store(\StoreBackendInterface $Store) : void
    {
        $this->stores[] = $StoreBackend;
    }

    /**
     * Returns the first StoreBackend.
     * @return StoreInterface|null
     */
    public function get_fallback_store() : ?StoreInterface
    {
        return $this->stores[0];
    }

    public function get_storage_columns_data(string $class) : array
    {
        return $this->get_fallback_store()->get_storage_columns_data($class);
    }

    public function get_unified_columns_data(string $class) : array
    {
        return $this->get_fallback_store()->get_unified_columns_data($class);
    }

    /**
     * Updates the Activerecord in all of the registered stores.
     * The update is done in coroutines (non-blocking APIs should be used).
     * @param ActiveRecordInterface $ActiveRecord
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     */
    public function update_record(ActiveRecordInterface $ActiveRecord) : array
    {
        $callables = [];
        foreach ($this->stores as $Store) {
            $callables[] = static function () use ($Store, $ActiveRecord) {
                $Store->update_record($ActiveRecord);
            };
        }
        $co_ret = Coroutine::executeMulti($callables);
        //return $ActiveRecord->get_uuid();
        return $co_ret[0];
    }

    public function &get_data_pointer(string $class, array $index) : array
    {
        return $this->get_fallback_store()->get_data_pointer($class, $primary_index);
    }

    /**
     * Gets a new pointer for an object that is being updated.
     * Useful only for MemoryStore. The rest of the stores just return array (again by reference but internally in the method this reference points to a local var).
     * @param string $class
     * @param array $primary_index
     * @return array
     */
    public function &get_data_pointer_for_new_version(string $class, array $primary_index) : array
    {
        return $this->get_fallback_store()->get_data_pointer_for_new_version($class, $primary_index);
    }

    /**
     * Check whethere is already an existing pointer for an object being updated.
     * Useful only for MemoryStore.
     * @param string $class
     * @param array $primary_index
     * @return bool
     */
    public function there_is_pointer_for_new_version(string $class, array $primary_index) : bool
    {
        return $this->get_fallback_store()->there_is_pointer_for_new_version($class, $primary_index);
    }

    /**
     * To be called when an ActiveRecord is destroyed.
     * @param ActiveRecordInterface $ActiveRecord
     * @return void
     */
    public function free_pointer(ActiveRecordInterface $ActiveRecord) : void
    {
        $this->get_fallback_store()->free_pointer();
    }

    /**
     * Returns storage data used for debug purpose in a free format structure.
     * @return array
     */
    public function debug_get_data() : array
    {
        $this->get_fallback_store()->debug_get_data();
    }

    /**
     * Removes an active record data from the Store
     * @param ActiveRecordInterface $ActiveRecord
     */
    public function remove_record(ActiveRecordInterface $ActiveRecord): void
    {
        $this->get_fallback_store()->remove_record($ActiveRecord);
    }
}
