<?php
declare(strict_types=1);

namespace Guzaba2\Transaction;


use Azonmedia\Patterns\ScopeReference;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Transaction\Interfaces\TransactionalResourceInterface;
use Guzaba2\Translator\Translator as t;

/**
 * Class CompositeTransaction
 * @package Guzaba2\Transaction
 *
 * Only the master transaction needs to keep resource scope references (in order to prevent them from being destroyed)
 *
 */
abstract class CompositeTransaction extends Transaction
{

    /** @var Transaction[] */
    private array $transactions = [];

    /** @var ScopeReference[] */
    private array $resource_scope_references = [];

    protected function _before_destruct() : void
    {
        //this is just in case here
        //in fact the destructor will not be invoked until the GC so this can not be relied upon
        //$this->connection_references = [];//free all connections
        $this->clear_resource_scope_references();
    }

    /**
     * @param Transaction $Transaction
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function attach_transaction(Transaction $Transaction): void
    {
        if ($Transaction->get_status() !== self::STATUS['CREATED']) {
            throw new InvalidArgumentException(sprintf(t::_('The transaction being attached is in status %1s. It is not allowed to attach transactions with status other than %2s.'), $Transaction->get_status(), self::STATUS['CREATED'] ));
        }
        if ($this->get_status() !== self::STATUS['CREATED']) {
            throw new RunTimeException(sprintf(t::_('The distributed transaction is in status %1s. It is not allowed to attach transactions to a distributed transaction that is in status different from %2s.'), $this->get_status(), self::STATUS['CREATED'] ));
        }

        $this->transactions[] = $Transaction;
    }


    protected function add_resource_scope_reference(ScopeReference &$ScopeReference): void
    {
        $this->resource_scope_references[] =& $ScopeReference;
    }

    protected function clear_resource_scope_references(): void
    {
        $this->resource_scope_references = [];//no need of looping and explicitly set to NULL
    }

    protected function execute_begin(): void
    {
        foreach ($this->transactions as $Transaction) {
            $Transaction->begin();
        }
    }

    /**
     * @param string $savepoint
     */
    public function execute_create_savepoint(string $savepoint) : void
    {
        foreach ($this->transactions as $Transaction) {
            //$Transaction->begin();
            //$Transaction->create_savepoint($savepoint);
            $child_transactions = $Transaction->get_children();
            /** @var Transaction $LastChildTransaction */
            $LastChildTransaction = $child_transactions[ count($child_transactions) - 1];
            $LastChildTransaction->begin();
        }
    }

    /**
     * @throws RunTimeException
     * @throws LogicException
     */
    protected function execute_rollback(): void
    {
        foreach ($this->transactions as $Transaction) {
            $Transaction->rollback();
        }
        if ($this->is_master()) {
            $this->clear_resource_scope_references();
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function execute_save(): void
    {
        foreach ($this->transactions as $Transaction) {
            $Transaction->save();
        }
    }

    /**
     * @throws RunTimeException
     */
    protected function execute_commit(): void
    {
        foreach ($this->transactions as $Transaction) {
            $Transaction->commit();
        }
        if ($this->is_master()) {
            $this->clear_resource_scope_references();
        }

    }

    /**
     * @param string $savepoint
     * @throws LogicException
     * @throws RunTimeException
     */
    protected function execute_rollback_to_savepoint(string $savepoint): void
    {
        foreach ($this->transactions as $Transaction) {
            $child_transactions = $Transaction->get_children();
            /** @var Transaction $LastChildTransaction */
            $LastChildTransaction = $child_transactions[ count($child_transactions) - 1];
            $LastChildTransaction->rollback();
        }
    }

    /**
     * @param string $savepoint
     */
    protected function execute_release_savepoint(string $savepoint): void
    {
        foreach ($this->transactions as $Transaction) {
            $Transaction->release_savepoint($savepoint);
        }
    }


}