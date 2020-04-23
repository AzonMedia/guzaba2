<?php
declare(strict_types=1);

namespace Guzaba2\Orm\Store;


use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Store\Interfaces\StoreTransactionInterface;
use Guzaba2\Transaction\Interfaces\TransactionalResourceInterface;
use Guzaba2\Transaction\Transaction;

/**
 * Class MemoryTransaction
 * @package Guzaba2\Orm\Store
 *
 * Represents a transaction in the Memory store.
 * This allows the state of an ActiveRecord object to be rolled back on per scope basis
 * It is stored in the coroutine - by the TransactionManager
 * If when a new AR object is created and there is active
 * When a new object is created (reference obtained) in Memory must store the corotuine that is using.
 * Basically not just a refcount but also keep IDs of which coroutines are using it.
 * Then when a Scope transaction is started the transaction is tied to specific coroutine and these objects will be put in transaction.
 * This is not entirely correct either as some objects may not be part of that scope even part of a call in that coroutine
 * So whnever there is a __set() invoked it must mark the objects in the coroutine
 * __set() will be used for tracking instead of obtaining reference. It is important to track the changes not the references
 */
class MemoryTransaction extends Transaction implements StoreTransactionInterface
{

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'OrmStore',
        ]
    ];

    protected const CONFIG_RUNTIME = [];

    private Memory $MemoryStore;

    /**
     * @var ActiveRecordInterface[]
     */
    private array $objects = [];

    /**
     * @var array
     */
    private array $objects_data = [];

    public function __construct(Memory $MemoryStore, array $options = [])
    {
        $this->MemoryStore = $MemoryStore;
        parent::__construct($options);
    }

    public function get_resource(): TransactionalResourceInterface
    {
        return $this->MemoryStore;
    }

    public function get_resource_id(): string
    {
        return $this->MemoryStore->get_resource_id();
    }


    public function attach_object(ActiveRecordInterface $ActiveRecord) : void
    {
        if (in_array($this->get_status(), [self::STATUS['STARTED'], self::STATUS['SAVED'] ] )) {
            $this->objects[] = $ActiveRecord;
            $this->objects_data['TRANSACTION_BEGIN'][$ActiveRecord->get_object_internal_id()] = $ActiveRecord->get_record_data();
        } else {
            //when the current transaction is committed or rolled back it is removed from the TransactionManager
            //when it is in status Created it is not yet added to the TransactionManager
            throw new LogicException(sprintf(t::_('There seems to be a current transaction set while there should not be as its status is %1$s.'), $this->get_status() ));
        }
    }

    /**
     * Returns all attached objects.
     * This can be used to obtain all objects that have been modified in this transaction (as an object is attached only when it is modified).
     * @return ActiveRecordInterface[]
     */
    public function get_attached_objects() : array
    {
        return $this->objects;
    }

    protected function execute_begin(): void
    {
        //does nothing
    }

    protected function execute_commit(): void
    {
        //the changes to the objects remain as the are
        //remove all objects data
        //but do not release the objects as the self::get_attached_objects() may be used - to check which objects were modified during this transaction
        $this->data_cleanup();
    }

    protected function execute_save(): void
    {
        //does nothing
    }

    protected function execute_rollback(): void
    {
        //for each of the attached objects restores their data as it was at the time when they were attached
        foreach ($this->objects as $ActiveRecord) {
            $ActiveRecord->set_record_data($this->objects_data['TRANSACTION_BEGIN'][$ActiveRecord->get_object_internal_id()]);
        }
        $this->data_cleanup();
    }

    protected function execute_create_savepoint(string $savepoint): void
    {
        foreach ($this->objects as $ActiveRecord) {
            $this->objects_data[$savepoint][$ActiveRecord->get_object_internal_id()] = $ActiveRecord->get_record_data();
        }
    }

    protected function execute_rollback_to_savepoint(string $savepoint): void
    {
        foreach ($this->objects as $ActiveRecord) {
            $object_internal_id = $ActiveRecord->get_object_internal_id();
            if (array_key_exists($object_internal_id, $this->objects_data[$savepoint])) {
                $ActiveRecord->set_record_data($this->objects_data[$savepoint][$ActiveRecord->get_object_internal_id()]);
            } else {
                //this is an object from another scope
            }

        }
    }

    protected function execute_release_savepoint(string $savepoint): void
    {
        unset($this->objects_data[$savepoint]);
    }

    /**
     * Clears all objects data
     * To be called on commit() or rollback()
     */
    private function data_cleanup() : void
    {
        $this->objects_data = [];
    }
}