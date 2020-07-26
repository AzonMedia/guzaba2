<?php

declare(strict_types=1);

namespace Guzaba2\Transaction;

use Azonmedia\Patterns\CallbackContainer;
use Guzaba2\Base\Base;
use Guzaba2\Transaction\Interfaces\TransactionalResourceInterface;

/**
 * Class TransactionManager
 * @package Guzaba2\MemoryTransaction
 * If multiple/parallel transactions of the same type are needed then a coroutine is to be used.
 * The TransactionManager is a coroutine dependency.
 */
class TransactionManager extends Base
{

    private array $current_transactions = [];

    public function set_current_transaction(?Transaction $Transaction, string $transaction_type = ''): void
    {

        if ($Transaction && $transaction_type) {
            //throw new
        }
        if (!$Transaction && !$transaction_type) {
            //throw
        }

        if ($Transaction) {
            //$transaction_type = $MemoryTransaction->get_type();
            $transaction_type = $Transaction->get_resource()->get_resource_id();
        }
        $this->current_transactions[$transaction_type] = $Transaction;
    }

    public function get_current_transaction(string $transaction_type): ?Transaction
    {
        return $this->current_transactions[$transaction_type] ?? null;
    }

    public function get_all_current_transactions(): array
    {
        return $this->current_transactions;
    }


//    public function begin_transaction(TransactionalResourceInterface $TransactionalResource, ?ScopeReference &$ScopeReference, array $options = []) : MemoryTransaction
//    {
//        $MemoryTransaction = $TransactionalResource->get_transaction($options);
//
//        if ($ScopeReference) {
//            $ScopeReference->set_release_reason($ScopeReference::RELEASE_REASON_OVERWRITING);
//            $ScopeReference = NULL;//trigger rollback (and actually destroy the transaction object - the object may or may not get destroyed - it may live if part of another transaction)
//        }
//        $ScopeReference = new ScopeReference($MemoryTransaction);
//        $MemoryTransaction->begin();
//        return $MemoryTransaction;
//    }

//    public function begin_transaction(TransactionalResourceInterface $TransactionalResource, array $options = []) : MemoryTransaction
//    {
//        $MemoryTransaction = $TransactionalResource->get_transaction($options);
//        $MemoryTransaction->begin();
//        return $MemoryTransaction;
//    }
//
//    public function execute_in_transaction(TransactionalResourceInterface $TransactionalResource, callable $callable, array $options = []) /* mixed */
//    {
//        //$MemoryTransaction = new $transaction_class($options);
//        $MemoryTransaction = $TransactionalResource->get_transaction($options);
//        return $MemoryTransaction->execute($callable);
//    }
//
//    //public function commit_transaction(ScopeReference &$ScopeReference) : void
//    public function commit_transaction() : void
//    {
//        //$MemoryTransaction = $ScopeReference->get_transaction();
//        //if ($MemoryTransaction !== $this->get_current_transaction( $MemoryTransaction->get_resource()->get_resource_id() )) {
//            //throw transaction out of order
//        //}
//        $MemoryTransaction = $this->get_current_transaction( $MemoryTransaction->get_resource()->get_resource_id() );
//        $MemoryTransaction->commit();
//    }
//
//    public function rollback_transaction() : void
//    {
//
//    }
}
