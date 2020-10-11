<?php

declare(strict_types=1);

namespace Guzaba2\Database;

use Guzaba2\Database\Interfaces\TransactionalConnectionInterface;
use Guzaba2\Transaction\Interfaces\TransactionInterface;
use Guzaba2\Transaction\Interfaces\TransactionManagerInterface;
use Guzaba2\Transaction\ScopeReference;
use Guzaba2\Transaction\Transaction;
use Guzaba2\Transaction\TransactionManager;

abstract class TransactionalConnection extends Connection implements TransactionalConnectionInterface
{

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'TransactionManager',
        ]
    ];

    protected const CONFIG_RUNTIME = [];


    public function new_transaction(?ScopeReference &$ScopeReference, array $options = []): Transaction
    {

        if ($ScopeReference) {
            //$ScopeReference->set_release_reason($ScopeReference::RELEASE_REASON_OVERWRITING);
            $ScopeReference = null;//trigger rollback (and actually destroy the transaction object - the object may or may not get destroyed - it may live if part of another transaction)
        }

        $Transaction = new \Guzaba2\Database\Transaction($this, $options);

        $ScopeReference = new ScopeReference($Transaction);

        return $Transaction;
    }

    public function get_current_transaction(): ?TransactionInterface
    {
        /** @var TransactionManagerInterface $TransactionManager */
        $TransactionManager = self::get_service('TransactionManager');
        //we need to create one transaction in order to obtain the transactional resource
        //the transaction will not be started and will not have a scope reference (so it will not be rolled back either)
        $Transaction = new \Guzaba2\Database\Transaction();
        $transaction_resource_id = $Transaction->get_resource()->get_resource_id();

        $CurrentTransaction = $TransactionManager->get_current_transaction($transaction_resource_id);

        return $CurrentTransaction;
    }

    public function close(): void
    {
        $this->rollback_all_transactions();
    }

    public function reset_connection(): void
    {
        parent::reset_connection();
        $this->rollback_all_transactions();
    }

    public function rollback_all_transactions(): void
    {
        /** @var TransactionManager $TXM */
        $TXM = self::get_service('TransactionManager');
        $Transaction = $TXM->get_current_transaction($this->get_resource_id());
        if ($Transaction) {
            $MasterTransaction = $Transaction->get_master();
            $MasterTransaction->rollback();//will trigger rollback event on all nested transactions as well
        }
    }
}
