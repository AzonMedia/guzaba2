<?php

declare(strict_types=1);

namespace Guzaba2\Orm;

use Guzaba2\Transaction\CompositeTransactionResource;
use Guzaba2\Transaction\Interfaces\TransactionInterface;
use Guzaba2\Transaction\Interfaces\TransactionManagerInterface;
use Guzaba2\Transaction\ScopeReference;
use Guzaba2\Transaction\Transaction;

class OrmTransactionalResource extends CompositeTransactionResource
{

    public function new_transaction(?ScopeReference &$ScopeReference, array $options = []): Transaction
    {

        if ($ScopeReference) {
            //$ScopeReference->set_release_reason($ScopeReference::RELEASE_REASON_OVERWRITING);
            $ScopeReference = null;//trigger rollback (and actually destroy the transaction object - the object may or may not get destroyed - it may live if part of another transaction)
        }

        $Transaction = new \Guzaba2\Orm\Transaction($options);

        $ScopeReference = new ScopeReference($Transaction);

        return $Transaction;
    }

    public function get_current_transaction(): ?TransactionInterface
    {
        /** @var TransactionManagerInterface $TransactionManager */
        $TransactionManager = self::get_service('TransactionManager');
        //we need to create one transaction in order to obtain the transactional resource
        //the transaction will not be started and will not have a scope reference (so it will not be rolled back either)
        $Transaction = new \Guzaba2\Orm\Transaction();
        $transaction_resource_id = $Transaction->get_resource()->get_resource_id();

        $CurrentTransaction = $TransactionManager->get_current_transaction($transaction_resource_id);

        return $CurrentTransaction;
    }
}
