<?php
declare(strict_types=1);
/*
 * Guzaba Framework
 * http://framework.guzaba.org
 *
 * This source file is subject to the BSD license that is bundled with this
 * package in the file LICENSE.txt and available also at:
 * http://www.opensource.org/licenses/bsd-license.php
 *
 */

/**
 * @category    Guzaba Framework
 * @package     Database
 * @subpackage  Overloading
 * @copyright   Copyright (c) Guzaba Ltd - http://guzaba.com
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author      Vesselin Kenashkov <vesko@webstudiobulgaria.com>
 */

namespace Guzaba2\Transaction;

use Guzaba2\Database\Exceptions\TransactionException;
use Guzaba2\Translator\Translator as t;

/**
 * The distributedTransaction is like any other transaction except it has special commit rules.
 * The current implementation allows only for combinations of one transaction with less than 100 priority - the rest must all be 100 priority (this means they can commit 100% reliably). For example the DB transaction is not 100 as it can reach a deadlock and it may fail (even after multiple attempts - the rerunnable transactions (AKA with $code argument) are configured to retry 3 times)
 *
 * We may use the following:
 * 100 - for memory/object transactions
 * 75 - for DB transactions - they can use SAGAs and compensating transactions by the ORM layer so even committed they can be rolled back (the locks are not released until the very end of the transaction)
 * 50 - filesystem transactions - there is no rolblack there
 * 25 - for remote transaction
 *
 * First is commited the most unlikely to commit transaction - AKA the transaction with the least priority
 *
 * The above should be reversed - the highest priority for being committed first should have the transactions that is most probably to fail.
 * This means that the transactions that are to succeed 100% should have priority 0.
 */
class DistributedTransaction extends Transaction
{

    /**
     * Contains the transactions that are part of this distributed transaction
     * @var array
     */
    protected $transactions = [];

    /**
     * The priority of a distributed transaction is the highest priority of the transactions it contains.
     * This method will also work if a distributed transaction contains other distributed transactions.
     * @override
     *
     * @return int
     *
     * @author vesko@azonmedia.com
     * @since 0.7.1
     */
    public function get_priority(): int
    {
        //the higher the priority the higher is the chance of failure - means if atransaction has 0 priority it will always succeeed
        $highest_priority = 0;
        foreach ($this->get_transactions() as $transaction) {
            if ($transaction->get_priority() > $highest_priority) {
                $highest_priority = $transaction->get_priority();
            }
        }
        return $highest_priority;
    }

    /**
     * Only started master transactions can be added. A nested transaction can not be attached.
     * Other distributed transactions can be attached too.
     * @param transaction $transaction
     * @return $this Returns $this to allow for method chaining
     * @throws TransactionException
     */
    public function attach_transaction(transaction $transaction): self
    {
        if ($this->is_master() && !$transaction->is_master()) {
            //$p = $transaction->get_parent();
            //die(get_class($p));//NOVERIFY
            throw new TransactionException($transaction, sprintf(t::_('The transaction of type "%s" that is being attached to a Distributed transaction must be a Master one. Such transaction can not have parent transactions.'), get_class($transaction)));
        }
        if (!$transaction->is_started()) {
            throw new TransactionException($transaction, sprintf(t::_('The transaction that is being attached to a Distributed transaction must be one that is started (not commited or rolled back).')));
        }
        //check is it already attached
        foreach ($this->get_transactions() as $attached_transaction) {
            if ($attached_transaction === $transaction) {
                throw new TransactionException($transaction, sprintf(t::_('This transaction is already attached.')));
            }
        }

        $this->transactions[] = $transaction;

        return $this;//for method chaining
    }

    /**
     * Returns an array with all attached transactions in the order of being added.
     * @return array
     *
     */
    public function get_transactions(): array
    {
        return $this->transactions;
    }

    /**
     * Returns a two dimensional arra with the transactions and their priority.
     * The keys are sorted from highest to lowest priority.
     * @example $transactions[100] = [$t1, $t2]; $transactions[50] = [$t3];
     */
    public function get_transactions_grouped_by_priority(): array
    {
        $ret = [];
        foreach ($this->get_transactions() as $transaction) {
            if (!isset($ret[$transaction->get_priority()])) {
                $ret[$transaction->get_priority()] = [];
            }
            $ret[$transaction->get_priority()][] = $transaction;
        }

        uksort($ret, function ($a, $b) {
            return $a > $b ? 1 : -1;
        });

        return $ret;
    }

    protected function execute_begin(): bool
    {
        //the transactions are started by default when constructed
        //and for them to be attached to the distributed one are already constructed
        return TRUE;
    }

    /**
     *
     * If a distributed transaction consists of two transactions - one with priority 100 (this means that it can always commit) and one with 100 or less there is no need to use SAGAs (compensating transactions)
     * In this case we just
     */
    protected function execute_commit(): bool
    {
        foreach ($this->get_transactions_grouped_by_priority() as $priority => $transactions) {
            foreach ($transactions as $transaction) {
                $transaction->commit();
            }
        }
        return TRUE;
    }

    protected function execute_rollback(): bool
    {
        foreach ($this->get_transactions_grouped_by_priority() as $priority => $transactions) {
            foreach ($transactions as $transaction) {
                if ($transaction->get_status() != $transaction::STATUS_ROLLED_BACK) {
                    $transaction->rollback();
                }
            }
        }
        return TRUE;
    }

    protected function execute_create_savepoint(string $savepoint): bool
    {
        //the distributed transactions do not support savepoints
        //instead when a nested commit() is issued it is passed to the transactions and they handle the savepoint
        //$this->execute_commit();


        //no need to execute this as when the transactions are started there is an automatic savepoint created if they have a parent one
        //basically begin() is the only place where createSavepoint() may get invoked and the transactions are already started when attached to the distributed one
        //foreach ($this->get_transactions_grouped_by_priority() as $priority => $transactions) {
        //    foreach ($transactions as $transaction) {
        //        $transaction->createSavepoint($savepoint);
        //    }
        //}


        $transaction_id_for_which_to_create_savepoint = self::getTransactionIdFromSavepointName($savepoint);
        $must_save = FALSE;
        //as currnetly the code is using scope references this loop sbhould rollback at the most one nested transaction
        //but just in case in future it is allowed to rolblack to a specific savepoint the nested transactions should be rolled back in a reverse way
        /*
        foreach ($this->get_nested() as $transaction) {
            if ($transaction->get_object_internal_id() == $transaction_id_from_which_to_rollback) {
                $must_rollback = TRUE;
            }
            if ($must_rollback) {
                //the transaction may already be rolled back
                //but no matter that - execute the data rollback - this is not related to the transaction status
                $transaction->execute_rollback();
            }
        }
        */
        $transactions_to_be_saved = [];
        foreach ($this->get_nested() as $transaction) {
            if ($transaction->get_object_internal_id() == $transaction_id_for_which_to_create_savepoint) {
                $must_save = TRUE;
            }
            if ($must_save) {
                //the transaction may already be rolled back
                //but no matter that - execute the data rollback - this is not related to the transaction status
                //k::logtofile('DV_66', 'exec commit');
                $transaction->execute_commit();//executes the commit on the distributed transaction
            }
        }
        unset($transaction);

        return TRUE;
    }

    protected function execute_rollback_to_savepoint(string $savepoint): bool
    {
        //foreach ($this->get_transactions_grouped_by_priority() as $priority => $transactions) {
        //    foreach ($transactions as $transaction) {
        //        $transaction->rollbackToSavepoint($savepoint);
        //    }
        //}
        //the distributed transactions do not support themselves a rollback_to_savepoint so this has to be passed to the rollback()
        //$this->execute_rollback();//this is wrong - we are rolling back to a specific savepoint but not rolling back this whole transaction
        //we are rolling back (some of) the nested transactions of this one


        $transaction_id_from_which_to_rollback = self::getTransactionIdFromSavepointName($savepoint);
        $must_rollback = FALSE;
        //as currnetly the code is using scope references this loop sbhould rollback at the most one nested transaction
        //but just in case in future it is allowed to rolblack to a specific savepoint the nested transactions should be rolled back in a reverse way
        /*
        foreach ($this->get_nested() as $transaction) {
            //k::logtofile('DV_301', $savepoint.' '.$transaction_id_from_which_to_rollback);

            if ($transaction->get_object_internal_id)_ == $transaction_id_from_which_to_rollback) {
                $must_rollback = TRUE;
            }
            if ($must_rollback) {
                //the transaction may already be rolled back
                //but no matter that - execute the data rollback - this is not related to the transaction status
                $transaction->execute_rollback();
            }
        }
        */
        $transactions_to_be_rolled_back = [];
        foreach ($this->get_nested() as $transaction) {
            if ($transaction->get_object_internal_id() == $transaction_id_from_which_to_rollback) {
                $must_rollback = TRUE;
            }
            if ($must_rollback) {
                //the transaction may already be rolled back
                //but no matter that - execute the data rollback - this is not related to the transaction status
                //$transaction->execute_rollback();
                $transactions_to_be_rolled_back[] = $transaction;
            }
        }
        unset($transaction);

        $transactions_to_be_rolled_back = array_reverse($transactions_to_be_rolled_back);
        foreach ($transactions_to_be_rolled_back as $transaction) {
            if ($transaction->get_status() != $transaction::STATUS_ROLLED_BACK) {
                $transaction->execute_rollback();//this will call the rollbacks on the transactions and this rollback will set their status
                //$transaction->rollback();//this will trigger a recursion ... but needs to be executed to update the transaction status and the callbacks
            }
        }

        return TRUE;
    }

    protected function execute_release_savepoint(string $savepoint): bool
    {
        return TRUE;
    }

    //this is needed for the STATUS_SAVED
    //the rest ofthe statuses are handled by the other methods
    protected function execute_set_status(int $status): void
    {
        if ($status == self::STATUS_SAVED) {
            foreach ($this->get_transactions_grouped_by_priority() as $priority => $transactions) {
                foreach ($transactions as $transaction) {
                    $transaction->set_status($status);
                }
            }
        }
    }

    protected function _before_destroy(): void
    {
        $this->transactions = [];
        parent::_before_destroy();
    }
}
