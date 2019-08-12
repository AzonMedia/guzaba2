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
 * @package     Database
 * @subpackage  Overloading
 * @copyright   Copyright (c) Guzaba Ltd - http://guzaba.com
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author      Vesselin Kenashkov <vesko@webstudiobulgaria.com>
 */

namespace Guzaba2\Transaction;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Database\Exceptions\TransactionException;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Translator\Translator as t;

/**
 *
 */
class TransactionManager extends Base
{

    /**
     * There are different type of transactions - these will be per class - this includes the distributedTransaction and globalDistributedTransaction
     * @var array
     */
    protected $currentTransactions = [];


    /**
     * Sets the current transaction for the provided transaction class.
     * There can be multiple current transactions (but of different types)
     *
     * @param Transaction $transaction
     * @param string $transaction_type This is needed because there can be NULL provided to $transaction and then we need to know which type has no master transaction.
     * @throws InvalidArgumentException
     */
    public function setCurrentTransaction(?Transaction $transaction, string $transaction_type = '')
    {
        //self::$currentTransactions[$transaction->getOptionValue('transaction_type')] =& $transaction;
        if ($transaction) {
            $transaction_type = get_class($transaction);
        } else {
            if (!$transaction_type) {
                throw new InvalidArgumentException(sprintf(t::_('When there is no current transaction set (===NULL) then the $transaction_type must be provided as second argument to %s().'), __METHOD__));
            }
        }

        $this->currentTransactions[$transaction_type] = $transaction;
    }

    /**
     * Retrieves the current transaction based on the provided class name
     *
     * @param string $transaction_type
     * @return Transaction|NULL
     */
    public function getCurrentTransaction(string $transaction_type): ?Transaction
    {
        $ret = NULL;
        if (!empty($this->currentTransactions[$transaction_type])) {
            $ret = $this->currentTransactions[$transaction_type];
        }
        return $ret;
    }

    /**
     * Returns all currently ongoing transactions (they are of different type).
     *
     * @return array Array of transaction objects
     */
    public function getAllCurrentTransactions(): array
    {
        return $this->currentTransactions;
    }


    /**
     * Begins transaction of the provided type.
     * The provided type must be the full class name of a transaction class that inherits \Guzaba2\Transaction\Transaction
     * @param string $transaction_type The class of the transaction of which a new transaction is to be started
     * @param ScopeReferenceTracker|null $scope_reference
     * @param CallbackContainer|null $callbackContainer
     * @param array $options
     * @param TransactionContext|null $transactionContext Not used currently. Reserved for future use.
     * @return Transaction
     * @throws InvalidArgumentException
     * @example
     * TXM::beginTransaction(ORMDBTransaction::class)
     */
    public static function beginTransaction(string $transaction_type, ?ScopeReferenceTracker &$scope_reference, ?CallbackContainer &$callbackContainer = NULL, array $options = [], ?transactionContext $transactionContext = null): Transaction
    {
        if (!$transaction_type) {
            throw new InvalidArgumentException(sprintf(t::_('No transaction type/class provided.')));
        } elseif (!class_exists($transaction_type)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided transaction type/class does not exist (no such class).')));
        } elseif (!is_subclass_of($transaction_type, Transaction::class)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided transaction type/class does not extend framework\transactions\classes\transaction.')));
        }
        $transaction = new $transaction_type($scope_reference, null, $callbackContainer, $callbackContainer, $options, $transactionContext);
        return $transaction;
    }


    /**
     * Executes the provided $callable in the specified type transaction.
     * Returns the returned value from the callable.
     * Depending of the options set in transaction_config.xml.php the transaction may be retried if it fails (will try to begin a new transaction and execute the code again).
     *
     * @param callable $callable The callable to be executed
     * @param string $transaction_type The class of the transaction of which a new transaction is to be started
     * @param CallbackContainer|null $callbackContainer
     * @param array $options
     * @param TransactionContext|null $transactionContext Not used currently. Reserved for future use.
     * @return mixed The returned value from the execution of the $callable
     * @throws InvalidArgumentException
     */
    public static function executeInTransaction(callable $callable, string $transaction_type, ?CallbackContainer &$callbackContainer = NULL, array $options = [], ?TransactionContext $transactionContext = null) /* mixed */
    {
        if (!$transaction_type) {
            throw new InvalidArgumentException(sprintf(t::_('No transaction type/class provided.')));
        } elseif (!class_exists($transaction_type)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided transaction type/class does not exist (no such class).')));
        } elseif (!is_subclass_of($transaction_type, Transaction::class)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided transaction type/class does not extend framework\transactions\classes\transaction.')));
        }
        $transaction = new $transaction_type($TR, $callable, $callbackContainer, $callbackContainer, $options, $transactionContext);
        $ret = $transaction();

        $TR->detachTransaction();//must be done as otherwise it will roll it back
        return $ret;
    }

    /**
     * Commits (or saves if nested) the transaction that the scope reference points to.
     * This commit() is to be used for all type of transactions.
     *
     * @param scopeReferenceTracker $scope_reference
     * @return void
     * @throws TransactionException
     * @throws InvalidArgumentException
     */
    public static function commit(scopeReferenceTracker &$scope_reference): void
    {
        self::validateScopeReference($scope_reference);
        //do some crossh-check
        $transaction = $scope_reference->getTransaction();
        //$current_transaction = self::getCurrentTransaction($transaction->getOptionValue('transaction_type'));
        $current_transaction = self::getCurrentTransaction(get_class($transaction));
        //this should be the same object/instance
        //When using the identity operator (===), object variables are identical if and only if they refer to the same instance of the same class.

        if ($transaction !== $current_transaction) {

            //it can happen not to have at al $transaction or $current_transaction
            if ($transaction instanceof Transaction) {
                $transaction_info_object = $transaction->get_transaction_start_bt_info();
                if ($transaction_info_object) {
                    Kernel::logtofile('TRANSACTION_ERRORS', 'scope reference transaction started at ' . PHP_EOL . print_r($transaction_info_object->getTrace(), TRUE));//NOVERIFY
                } else {
                    Kernel::logtofile('TRANSACTION_ERRORS', 'no backtrace info available for the scope reference transaction');
                }
            } else {
                Kernel::logtofile('TRANSACTION_ERRORS', 'the transaction of the scope reference is not instance of transaction but is ' . (is_object($transaction) ? get_class($transaction) : gettype($transaction)));
            }

            if ($current_transaction instanceof Transaction) {
                $current_transaction_info_object = $current_transaction->get_transaction_start_bt_info();
                if ($current_transaction_info_object) {
                    Kernel::logtofile('TRANSACTION_ERRORS', 'current transaction started at ' . PHP_EOL . print_r($current_transaction_info_object->getTrace(), TRUE));//NOVERIFY
                } else {
                    Kernel::logtofile('TRANSACTION_ERRORS', 'no backtrace info available for the current transaction');
                }
            } else {
                Kernel::logtofile('TRANSACTION_ERRORS', 'the current transaction is not instance of transaction but is ' . (is_object($current_transaction) ? get_class($current_transaction) : gettype($current_transaction)));
            }

            if ($transaction->is_commited()) {
                $message = sprintf(t::_('Trying to commit again a transaction that is already committed. To see more details about this abnormal condition please see TRANSACTION_ERRORS.txt log.'));
            } else {
                $message = sprintf(t::_('The provided scope reference to %s() is not a reference to the current transaction according to %s::getCurrentTransaction(%s). To see more details about this abnormal condition please see TRANSACTION_ERRORS.txt log.'), __METHOD__, __CLASS__, get_class($transaction));
            }


            throw new TransactionException($transaction, $message);
        }

        $transaction->commit();

        $scope_reference->rollback_on_destroy = false;//everything is OK with the transaction... do not roll it back
        //$scope_reference = NULL;//lets leave the instance alive so that we can throw better errors if reused
    }

    /**
     * Rollbacks the transaction that the scope reference points to.
     * This rollback() is to be used for all type of transactions.
     *
     * @param ScopeReferenceTracker $scope_reference
     * @return void
     * @throws InvalidArgumentException
     * @throws TransactionException
     * @throws \Guzaba2\Base\Exceptions\LogicException
     */
    public static function rollback(ScopeReferenceTracker &$scope_reference): void
    {
        self::validateScopeReference($scope_reference);
        $transaction = $scope_reference->getTransaction();
        //$current_transaction = self::getCurrentTransaction($transaction->getOptionValue('transaction_type'));
        $current_transaction = self::getCurrentTransaction(get_class($transaction));

        if ($transaction !== $current_transaction) {

            //it can happen not to have at al $transaction or $current_transaction
            if ($transaction instanceof Transaction) {
                $transaction_info_object = $transaction->get_transaction_start_bt_info();
                if ($transaction_info_object) {
                    Kernel::logtofile('TRANSACTION_ERRORS', 'scope reference transaction started at ' . PHP_EOL . print_r($transaction_info_object->getTrace(), TRUE));//NOVERIFY
                } else {
                    Kernel::logtofile('TRANSACTION_ERRORS', 'no backtrace info available for the scope reference transaction');
                }
            } else {
                Kernel::logtofile('TRANSACTION_ERRORS', 'the transaction of the scope reference is not instance of transaction but is ' . (is_object($transaction) ? get_class($transaction) : gettype($transaction)));
            }

            if ($current_transaction instanceof Transaction) {
                $current_transaction_info_object = $current_transaction->get_transaction_start_bt_info();
                if ($current_transaction_info_object) {
                    Kernel::logtofile('TRANSACTION_ERRORS', 'current transaction started at ' . PHP_EOL . print_r($current_transaction_info_object->getTrace(), TRUE));//NOVERIFY
                } else {
                    Kernel::logtofile('TRANSACTION_ERRORS', 'no backtrace info available for the current transaction');
                }
            } else {
                Kernel::logtofile('TRANSACTION_ERRORS', 'the current transaction is not instance of transaction but is ' . (is_object($current_transaction) ? get_class($current_transaction) : gettype($current_transaction)));
            }

            if ($transaction->is_rolled_back()) {
                $message = sprintf(t::_('Trying to rollback again a transaction that is already rolled back. To see more details about this abnormal condition please see TRANSACTION_ERRORS.txt log.'));
            } else {
                $message = sprintf(t::_('The provided scope reference to %s() is not a reference to the current transaction according to %s::getCurrentTransaction(%s). To see more details about this abnormal condition please see TRANSACTION_ERRORS.txt log.'), __METHOD__, __CLASS__, get_class($transaction));
            }

            throw new TransactionException($transaction, $message);
        }


        $transaction->rollback(TRUE);//TRUE means an explicit rollback

        $scope_reference->rollback_on_destroy = false;//everything is OK with the transaction... do not roll it back (again...)
        //$scope_reference = NULL;//lets leave the instance alive so that we can throw better errors if reused
    }

    /**
     * Validates the scope reference as provided to self::commit() and self::rollback() methods.
     * Throws an exception if it is wrong.
     * @param scopeReferenceTracker $scope_reference
     * @return void
     * @throws TransactionException
     * @author vesko@azonmedia.com
     * @created 28.12.2017
     * @since 0.7.1
     */
    private static function validateScopeReference(scopeReferenceTracker &$scope_reference): void
    {
        if ($scope_reference->transactionIsDetached()) {
            throw new TransactionException($scope_reference->getTransaction(), sprintf(t::_('The provided scope reference has its transaction detached. Such references can not be used with TXM::commit() and TXM::rollback().')));
        }
        if (!$scope_reference->getTransaction()) {
            throw new TransactionException($scope_reference->getTransaction(), sprintf(t::_('The attached transaction to the scope reference seems to have been destroyed.')));
        }
    }
}
