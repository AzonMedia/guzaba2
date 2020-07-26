<?php

declare(strict_types=1);

namespace Guzaba2\Orm;

use Azonmedia\Patterns\ScopeReference;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Store\Interfaces\TransactionalStoreInterface;
use Guzaba2\Transaction\CompositeTransaction;
use Guzaba2\Transaction\Interfaces\TransactionalResourceInterface;

/**
 * Class Transaction
 * @package Guzaba2\Orm
 *
 * The ORM Transaction is a composite transaction consisting of transaction in the Memory Store and any of the backend stores that support transactions.
 * Usually this would be a Memory transaction and a MySQL transaction (even if Redis store is used it does not support transactions - neither is needed as it is only caching layer)
 */
class Transaction extends CompositeTransaction
{

//    /** @var ScopeReference[] */
//    private array $connection_references = [];

    public function __construct(array $options = [])
    {
        parent::__construct($options);

        /** @var StoreInterface $Store */
        $Store = ActiveRecord::get_store();
//        if ($Store instanceof TransactionalResourceInterface) {
//            $Store->new_transaction($TR, $options);
//            $TR->remove_callbacks();
//            $TR = NULL;
//
//
//        } else {
//
//        }

        /** @var \Guzaba2\Transaction\Transaction[] $transactions */
        $transactions = [];
        do {
            if ($Store instanceof TransactionalResourceInterface) {
                $Transaction = $Store->new_transaction($TR, $options);
                $TR->remove_callbacks();//it is safe to detach this ScopeReference as there will be a master ScopeReference for the CompositeTransaction itself
                $TR = null;
                //$this->attach_transaction($Transaction);//needs to be in reverse order - first commit in the outermost store
                $transactions[] = $Transaction;
            } elseif ($Store instanceof TransactionalStoreInterface) {
                //$Transaction = $Store->get_connection($CR)->new_transaction($TR);
                $Connection = $Store->get_connection($CR);
                //print 'COnn ID: '.$Connection->get_object_internal_id().PHP_EOL;
                $Transaction = $Connection->new_transaction($TR);
                $TR->remove_callbacks();
                $TR = null;
                $transactions[] = $Transaction;
                //$this->connection_references[] = $CR;//the connection reference must be preserved and keep this connection attached to this coroutine
                if ($this->is_master()) {
                    $this->add_resource_scope_reference($CR);
                }
                //if the connection if freed then the transaction will be unable to start (starting will throw an error that the connection is not attached to any coroutine)
            } else {
                //skip this store
            }
            $Store = $Store->get_fallback_store();
        } while ($Store);
        $transactions = array_reverse($transactions);
        foreach ($transactions as $Transaction) {
            $this->attach_transaction($Transaction);
        }
    }



    public function get_resource(): TransactionalResourceInterface
    {
        //it doesnt matter that every time a new instance is created
        //the only method on this instance that matters is get_resource_id() which does not depend on the instance (but on the coroutine id)
        return new OrmTransactionalResource();
    }
}
