<?php
declare(strict_types=1);
/*
 * Guzaba Framework 2
 * http://framework2.guzaba.org
 *
 * This source file is subject to the BSD license that is bundled with this
 * package in the file LICENSE.txt and available also at:
 * http://www.opensource.org/licenses/bsd-license.php
 *
 */

/**
 * @category    Guzaba Framework 2
 * @copyright   Copyright (c) Guzaba Ltd - http://guzaba.com
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author      Vesselin Kenashkov <vesko@webstudiobulgaria.com>
 */
namespace Guzaba2\Transaction;

use Guzaba2\Base\Exceptions\BaseException;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Patterns\ScopeReference;
use Guzaba2\Translator\Translator as t;

/**
 * Used for tracking the transactions (for tracking nesting and missing commits())
 */
final class ScopeReferenceTracker extends ScopeReference
{
    /**
     * @var Transaction
     */
    protected $transaction;

    /**
     * @var bool
     */
    protected $transaction_is_detached_flag = FALSE;

    /**
     * @var bool
     */
    public $rollback_on_destroy = TRUE;

    /**
     * ScopeReferenceTracker constructor.
     * @param Transaction $transaction
     */
    public function __construct(Transaction $transaction)
    {
        parent::__construct();
        $this->transaction = $transaction;
    }

    /**
     * There are rare cases when we do not want to use the reference (we want to avoid the triggered rollback when the reference is destroyed).
     * This is done by removing the attached transaction form this reference.
     * This is an alias to detachTransaction()
     */
    public function disableReference(): void
    {
        $this->detachTransaction();
    }

    public function detachTransaction(): void
    {
        $this->transaction_is_detached_flag = TRUE;
        $this->transaction = NULL;
    }

    /**
     * To be used by the transactionManager::commit() & rollback() to validate the transaction reference
     *
     */
    public function transactionIsDetached(): bool
    {
        return $this->transaction_is_detached_flag;
    }

    /**
     * @throws TransactionException
     */
    public function __destruct()
    {
        parent::__destruct();

        // TODO add this logic in construct

        //TODO log this

        //$cloned_transaction = clone $this->get_transaction();
        if ($this->transaction) { //in the normal execution the transaction may be destroyed by the time this reference is destroyed
            $cloned_transaction = $this->transaction;
            //the scope reference can rollback only the current transaction to which it refers and this transaction can be rolled back only if it is STARTED
            //if it is in status SAVED then cant be rolled back by the scope reference as this means there was a commit() reached
            //BUT a transaction in status SAVED can be rolled back but only by a parent transaction that gets rolled back

            //if ($this->transaction->get_status() == transaction::STATUS_STARTED) {
            if (($this->transaction->get_status() == transaction::STATUS_STARTED || $this->transaction->get_status() == transaction::STATUS_SAVED) && $this->rollback_on_destroy) {
                Kernel::logtofile_backtrace('DB_bt');

                if (Database\Pdo::DBG_USE_STACK_BASED_ROLLBACK) {
                    //if we are throwing an exception this is not even needed
                    //this must be enabled is we want to silently rollback the current transaction and not trhow the exception
                    //$connection = $this->transaction->get_connection();
                    //$connection->setCurrentTransaction($this->transaction->get_parent());//this must be done AFTER the transaction is rolled back (because in the rolblack callback we still need to to have the current transaction in case we want to commit it)
                    if ($this->transaction->get_status() == transaction::STATUS_STARTED || $this->transaction->get_status() == transaction::STATUS_SAVED) {
                        if ($this->transaction->getOptionValue('enable_transactions_tracing')) {
                            Kernel::logtofile_indent($this->transaction->getOptionValue('transactions_tracing_store'), $this->transaction->get_id() . ' rollback-by-reference', 0);
                        }
                    }

                    if ($this->get_destruction_reason() == self::DESTRUCTION_REASON_UNKNOWN && BaseException::get_current_exception()) {
                        $this->set_destruction_reason(self::DESTRUCTION_REASON_EXCEPTION);
                        $this->transaction->set_interrupting_exception(BaseException::get_current_exception());
                        //then we better clear the current exception... it shouldnt stay...
                        //but if the exception was thrown from a scope without a beginTransaction block then it will stay :( (and it can be manually cleared - this will be done by the very next $scopeReferenceTransactionTracker that is created)
                        //so for the purpose at least of the transactions the exceptions should be clreaed out correctly
                        //BaseException::clear_current_exception();
                        //the exception must not be cleared out because we may break several scopes and the first scope will have an exception ut the next one will not have it
                    }

                    $parent_transaction = $this->transaction->get_parent();

                    //here in fact we can also clear the current exception kept in base because after this reference is destroyed surely we will drop in a catch block and we are no longer in exception
                    $this->transaction->set_this_transaction_as_rolled_back();
                    $this->transaction->set_transaction_as_interrupted();
                    //Kernel::logtofile('D02', 'SRT '.$this->transaction->unique_id.' '.$this->transaction->get_status());
                    $this->transaction->rollback();//this will also update the current transaction


                    //and only then destroy the current one
                    $this->transaction = null;//this is very important !!! Because we handle correctly the references this is the same reference as pdo::$current_transaction and this will destroy this object

                    //$connection->setCurrentTransaction($parent_transaction);

                    //this may no longer be the case because the reference is gone...
                } else {
                    $message = sprintf(t::_('Transaction was not commited or rolled back by the end of the scope. It seems the scope was left without executing commit() or rollback() statement.'));
                    throw new TransactionException($cloned_transaction, $message);
                }
            } else {
                //just ignore this... everything is OK with the transaction
            }
        }
    }

    /**
     * To be used for debug purpose.
     * Otherwise this scopeReference shouldnt be used for anything else (no methods called, nostatements whatsoever refering to the $TR reference) except for debug purposes
     */
    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function &__invoke(): ?Transaction
    {
        return $this->getTransaction();
    }

    private function __clone()
    {
    }
}
