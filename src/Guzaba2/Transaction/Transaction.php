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
 * @package     Transactions
 * @copyright   Copyright (c) Guzaba Ltd - http://guzaba.com
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author      Vesselin Kenashkov <vesko@webstudiobulgaria.com>
 */

namespace Guzaba2\Transaction;

use Azonmedia\Utilities\AlphaNumUtil;
use Azonmedia\Utilities\StackTraceUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Base\Exceptions\TransientErrorException;
use Guzaba2\Base\TraceInfoObject;
use Guzaba2\Base\Traits\SupportsConfig;
use Guzaba2\Database\Exceptions\TransactionException;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Patterns\Callback;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Transaction\TransactionManager as TXM;

/**
 * A transaction that itself was marked as SAVED or commited (the master one) can not be rolled back by the scope reference
 *
 * The savepoints on the parent transaction are named after the nested transaction - this produces a trace of savepoints. This may be needed if we need to rollback to a specific savepoint (currently this functionality is not allowed/provided)
 * If the name of the savepoint is after the parent transaction this will overwrite the savepoint whenever a new nested one is started. This will not allow for a rollback to a specific savepoint (as the single savepoint is updated).
 * With having scope references and having the below example of a master transaction M with 3 sequential nested transactions:
 *
 *   ^
 *   |
 *   scopes
 *   ^
 *   |      +----A-----+   +----B-----+   +----C----+
 *   +--M---+----------+---+----------+---+---------+-----+   -> timeline ->
 *
 * It is not possible to have next transaction C started after B and then rollback to B (the savepoint at the start of B). Still this is internally supported by the objectTransaction and database\transaction2 implementations and by the fact the savepoint names are after the nested transaction, not the parent one
 * This is to say that at the end of transaction A a savepoint named SPA will be created on transaction M (instead of savepoint named SPM which is updated at the end of A, B and C)
 *
 * Rolling back to a specific savepoint would be a very durty trick as this may/will rollback saved transactions inadvertently (unknown to the developer).
 * Transactions should be never manually managed / rolled back/ but only by scope or a loop!!! Doing otherwise may produce unexpected results!
 * @method CallbackContainer callback_container($transaction, int $mode) Invokes the property callback_container
 * @method TransactionManager TransactionManager()
 */
abstract class Transaction extends Base
{
    use SupportsConfig;

    protected const CONFIG_DEFAULTS = [
        'transaction_type' => '',
        'enable_transactions_tracing' => false,
        'transactions_tracing_store' => 'transactions_log.txt',
        'enable_callbacks_tracing' => false,
        'callbacks_tracing_store' => 'transactions_callbacks_log.txt',
        'rerun_at_transient_error_attempts' => 3,
        'wait_in_seconds_between_attempts' => 3,
        'increment_wait_time_between_attempts' => true,
    ];

    protected static $CONFIG_RUNTIME = [];

    protected $status = self::STATUS_STARTED;

    const STATUS_ANY = 0;//this is used by the callbackContainer for events that are to occur on status change no matter what the status is
    const STATUS_STARTED = 1;
    const STATUS_ROLLED_BACK = 2;// once it is rolled back to a savepoint it no longer matters what happens with the main transaction - either commited or not the current transaction does not go in
    const STATUS_COMMITTED = 3;
    const STATUS_SAVED = 4;//this means that the transaction has reached its commit() statement but has an outer one
    //const STATUS_MASTER_ROLLED_BACK = 5;//to be raised (resursively to all nested) when the master transaction gets rolled-back

    public static $status_map = [
        self::STATUS_ANY => 'any',//this is no real transaction status but it is used by callbackContainer to indicate event/mode that occurs on any status
        self::STATUS_STARTED => 'started',
        self::STATUS_ROLLED_BACK => 'rolled back',
        self::STATUS_COMMITTED => 'commited',
        self::STATUS_SAVED => 'savepoint created',
        //self::STATUS_MASTER_ROLLED_BACK => 'master transaction rolled back',
    ];

    /**
     * Unkown reason why the transaction was rolled back or it is not rolled back (or not about to be rolled back)
     */
    const ROLLBACK_REASON_UNKNOWN = 0;

    /**
     * Rolled back due exception
     */
    const ROLLBACK_REASON_EXCEPTION = 1;

    /**
     * Rolled back due an explicit rollback()
     *
     */
    const ROLLBACK_REASON_EXPLICIT = 2;

    /**
     * Rolled back implicitly due to return, break or exception but the exception got lost/destroyed
     *
     */
    const ROLLBACK_REASON_IMPLICIT = 3;


    /**
     * The transaction was rolled back because the parent transaction was rolled back.
     *
     *
     */
    const ROLLBACK_REASON_PARENT = 4;


    const ROLLBACK_REASON_MAP = [
        self::ROLLBACK_REASON_UNKNOWN => ['name' => 'unknown'],
        self::ROLLBACK_REASON_EXCEPTION => ['name' => 'exception'],
        self::ROLLBACK_REASON_EXPLICIT => ['name' => 'explicit'],
        self::ROLLBACK_REASON_IMPLICIT => ['name' => 'implicit'],
        self::ROLLBACK_REASON_PARENT => ['name' => 'parent'],
    ];


    /**
     * Shows the possible status transitions for each status.
     *
     * @author vesko@azonmedia.com
     * @created 02.10.2018
     * @since 0.7.3
     */
    public const STATUS_TRANSITIONS = [
        //status_any in the transitions means that calblacks on any event can be added
        //a transaction cant really be in this status
        self::STATUS_ANY => [self::STATUS_ANY, self::STATUS_STARTED, self::STATUS_ROLLED_BACK, self::STATUS_COMMITTED, self::STATUS_SAVED, self::STATUS_ANY], //this is not really used but added for completeness
        self::STATUS_STARTED => [self::STATUS_ROLLED_BACK, self::STATUS_COMMITTED, self::STATUS_SAVED, self::STATUS_ANY], //status_any here means that calblacks on any event can be added
        self::STATUS_SAVED => [self::STATUS_ROLLED_BACK, self::STATUS_COMMITTED, self::STATUS_ANY],//status_any here means that calblacks on any event can be added
        self::STATUS_COMMITTED => [], //cant change to any status
        self::STATUS_ROLLED_BACK => [],//cant change to any status
    ];

    /**
     * Array of nested transactions
     * @var transaction[]
     */
    protected $nested_transactions = [];

    /**
     *
     * @var transaction
     */
    protected $parent_transaction = null;

    /**
     * This is the code that actually executes the transaction - a callable
     * If this is set then we can rerun the transaction.
     * The code is just provided here in case it needs to be rerun, but the code will be invoked outside this transaction
     */
    protected $code;

    /**
     *
     * @var callbackContainer
     */
    protected $callback_container;

    /**
     * To be set if the transaction got interrupted by an exception
     * @var \Exception
     */
    protected $interrupting_exception = NULL;

    /**
     * If a transaction was cloned we do not allow any actions on it
     * It can be used only for debug purpose
     * @var bool
     */
    protected $is_cloned_flag = FALSE;

    protected $is_in_rollback_callback_flag = FALSE;

    protected $is_in_commit_callback_flag = FALSE;

    protected $is_in_save_callback_flag = FALSE;

    /**
     * Is this transaction a rerun of another transaction
     * @var bool
     */
    protected $is_a_rerun_flag = FALSE;

    protected static $supported_options = [];

    /**
     * The return value of the code that was executed in order to run the transaction
     */
    protected $run_result;


    /**
     * Keep timing data for each statement and overall nested transaction execution.
     *
     */
    protected $profile_data = [];

    /**
     * @var TransactionContext
     */
    protected $transactionContext;

    /**
     * This flag is to be reaised if THIS transaction has been interrupted and rolled back. This means it is being rolled back not because a child transaction is being rolled, but because this bery transaction is interrupted
     * @var bool
     */
    protected $this_transaction_was_rolled_back_flag = FALSE;

    /**
     * Tobe raised if the transaction was interrupted (AKA rolled back by the scopeReference because an exception was thrown) not because of an ordinary rollback()
     * @var bool
     */
    protected $transaction_was_interrupted_flag = FALSE;

    /**
     * The reason why the transaction was rolled back.
     * @see self::ROLLBACK_REASON_MAP
     * @since 0.7.4
     * @var int
     */
    protected $transaction_rollback_reason = self::ROLLBACK_REASON_UNKNOWN;

    /**
     * An associative array of the savepoints and the transactions on which they were applied.
     * @example $savepoints['savepointname'] => $transaction
     * @var array
     */
    protected static $savepoints = [];

    /**
     * Some child classes may have default name, some will not have and they will not redefine this and have it as NULL
     * @var NULL|string
     */
    protected static $default_transaction_type = NULL;

    /**
     * Contains information where was this transaction started.
     * @see self::get_transaction_start_bt_info()
     * @var TraceInfoObject
     */
    protected $transaction_start_bt_info;

    /**
     * Contains information where was this transaction rolled back (if it was rolled back).
     * @see self::get_transaction_rollback_bt_info()
     * @var TraceInfoObject
     */
    protected $transaction_rollback_bt_info;

    protected $priority;

    /**
     * Transaction constructor.
     * @param-out ScopeReferenceTracker|NULL $scope_reference
     * @param callable|NULL $code
     * @param callable|NULL $commit_callback
     * @param callable|NULL $rollback_callback
     * @param array $options
     * @param TransactionContext|null $transactionContext
     * @param bool $do_not_begin
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws LogicException
     * @throws TransactionException
     */
    public function __construct(ScopeReferenceTracker &$scope_reference = NULL, callable $code = NULL, callable &$commit_callback = NULL, callable &$rollback_callback = NULL, array $options = [], ?transactionContext $transactionContext = null, bool $do_not_begin = FALSE)
    {
        //the scope reference will be provided only when used with the TXM
        //But actually the transactions will be never started by invoking directly their constructor - so we can have the scope
        //public final function __construct($code = null, &$commit_callback = '', &$rollback_callback = '', array $options = array(), transaction $parent_transaction = null, transactionContext $context = null) {
        parent::__construct();


        //if (!Kernel::is_batch_mode()) {
        if (true) {
            $this->transaction_start_bt_info = new TraceInfoObject('transaction STARTED');
        }

        if ($code) {
            $this->set_code($code);
        }

        if ($scope_reference instanceof ScopeReferenceTracker) {
            $scope_reference->set_destruction_reason($scope_reference::DESTRUCTION_REASON_OVERWRITING);
            $scope_reference = null;//trigger rollback (and actually destroy the transaction object - the object may or may not get destroyed - it may live if part of another transaction)
        }

        if ($scope_reference !== NULL) {
            if ($scope_reference instanceof ScopeReferenceTracker) {
                $scope_reference->set_destruction_reason($scope_reference::DESTRUCTION_REASON_OVERWRITING);
                $scope_reference = NULL;//trigger rollback (and actually destroy the transaction object)
            } else {
                throw new InvalidArgumentException(sprintf(t::_('The first argument provided to the transaction constructor should always be an empty variable (this is the ScopeReferenceTracker and it is passed by references).')));
            }
        }
        $scope_reference = new ScopeReferenceTracker($this);


        $parent_transaction = self::TransactionManager()->getCurrentTransaction(get_class($this));

        if ($parent_transaction) {
            //check the options of this and the parent transaction
            //if they are different this could mean there is something wrong - like two transactions on different connection
            if ($parent_transaction::get_runtime_configuration() != $this::get_runtime_configuration()) {
                // TODO check if this actually can happen in the swoole context using static methods
                Kernel::logtofile('DIFFERENCE_IN_TRANSACTION_OPTIONS', print_r($parent_transaction::get_runtime_configuration(), TRUE) . ' ' . print_r($this->getObjectOptions()->getOptionsValues(), TRUE));
                // $message = sprintf(t::_('It seems there is an attempt to start a nested transaction of type "%s" which has a different set of object options that its parent one. For exmaple starting a DB transaction on one connection while there is a DB transaction already started on another connection.'), get_class($this) );
                //throw new RunTimeException($message);//temporarily disabled
            }
        }

        $this->parent_transaction = $parent_transaction;

        if ($this->parent_transaction) {
            $this->parent_transaction->add_nested($this);//we are passing directly $this.. this creates another reference
        }

        if (!$transactionContext) {
            $transactionContext = new TransactionContext;
        }
        $this->transactionContext = $transactionContext;


        //there will be only one container for both callbacks
        if ($commit_callback instanceof CallbackContainer && $rollback_callback instanceof CallbackContainer) {

            //a preconfigured container is provided
            //but we only check the $commit_callback - providing a second callback container in $rollback_callback is not supported
            if ($rollback_callback !== $commit_callback) {
                throw new InvalidArgumentException(sprintf(t::_('The transaction constructor supports only one callbackContainer to be provided as second argument to $commit_callback. If a third argument is provided to $rollback_callback it has to be a callable. If it is a callbackContainer it has to be the very same instance that was provided to the second argument.')));
            }

            $this->callback_container = $commit_callback;
            //check does the container has transaction set
            //if it has this means it is a reused container (maybe from a loop) and this is a bug
            //if it doesnt have a transaction set this means it is a new container (it was created before the transaction was created) and the transaction must be set
            if ($this->callback_container->get_transaction()) {
                if ($this->callback_container->get_transaction() != $this) {
                    throw new LogicException(sprintf(t::_('A callback container is being reused between transactions. This means the same $callbackContainer argument of TXM::begintransaction() is being passed to more than one transaction. If you need to use a callback container argument in a loop make sure to unset it before starting the new transaction.')));
                } else {
                    //this case doesnt seem to be possible - how the newly created container can have a reference to this transaction if it is being provided to the constructor of this very transaction
                }
            } else {
                //this is a new container and has no transaction set
                $this->callback_container->set_transaction($this);
            }
        } else {
            //create the container
            $callback_container = new CallbackContainer([], 0, $this);
            //$callback_container = new CallbackContainer( [], 0);
            $this->callback_container =& $callback_container;
            //$this->callback_container->set_transaction($this);//no need - it is passed in the constructor
        }


        if ($commit_callback === NULL) {
            //this means a reference was supplied and we need to inject into this reference the container
            $commit_callback = $callback_container;
        } elseif ($commit_callback instanceof CallbackContainer) {
            //ignore it
        } elseif (is_callable($commit_callback)) {
            if (!($commit_callback instanceof Callback)) {
                $commit_callback = new Callback($commit_callback, TRUE);
            }
            $this->callback_container->add($commit_callback, callbackContainer::DEFAULT_COMMIT_MODE);
        //then do not replace $commit_callback with callback_container (but we do replace it with patterns\classes\callback) - this is OK because this is what in fact was added to the container
        } else {
            $type = is_object($commit_callback) ? get_class($commit_callback) : gettype($commit_callback);
            throw new InvalidArgumentException(sprintf(t::_('An unsupported type "%s" was provided as second argument (commit_callback) to the transaction constructor.'), $type));
        }

        if ($rollback_callback === NULL) {
            $rollback_callback = $this->callback_container;
        } elseif ($commit_callback instanceof CallbackContainer) {
            //ignore it
        } elseif (is_callable($rollback_callback)) {
            if (!($rollback_callback instanceof Callback)) {
                $rollback_callback = new Callback($rollback_callback, TRUE);
            }
            $this->callback_container->add($rollback_callback, callbackContainer::DEFAULT_ROLLBACK_MODE);
        //then do not replace $commit_callback with callback_container (but we do replace it with patterns\classes\callback) - this is OK because this is what in fact was added to the container
        } else {
            $type = is_object($rollback_callback) ? get_class($rollback_callback) : gettype($rollback_callback);
            throw new InvalidArgumentException(sprintf(t::_('An unsupported type "%s" was provided as third argument (rollback_callback) to the transaction constructor.'), $type));
        }


        //the after_construct hook should be invoked before the begin()
        if (method_exists($this, '_after_construct')) {
            $this->_after_construct();
        }

        if (!$do_not_begin) {
            $this->begin();
        }
    }

    /**
     * @return string
     */
    public function get_transaction_target(): TransactionTargetInterface
    {
    }

    /**
     * @param callable|NULL $commit_callback
     * @param callable|NULL $rollback_callback
     * @param array $options
     * @param TransactionContext|null $transactionContext
     * @return Transaction
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws RunTimeException
     * @throws TransactionException
     */
    public static function simple_construct(callable &$commit_callback = NULL, callable &$rollback_callback = NULL, array $options = [], TransactionContext $transactionContext = null): self
    {
        $transaction = new static($TR, NULL, $commit_callback, $rollback_callback, $options, $transactionContext);
        /** @var ScopeReferenceTracker $TR */
        $TR->detachTransaction();
        unset($TR);
        return $transaction;
    }

    /**
     * This is a simpler constructor.
     * Uses
     * @param array $options
     * @param TransactionContext|null $transactionContext
     * @return Transaction
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws RunTimeException
     * @throws TransactionException @see self::simple_construct()
     * Example use of this is when we need to create a transaction which will be part of a distributed transaction.
     * In this case we dont need the $TR scopeReference as the distributedTransaction will be handling the life of the attached transactions that are part of it.
     */
    public static function simple_construct_without_callbacks(array $options = [], TransactionContext $transactionContext = null): self
    {
        $transaction = static::simple_construct($commit_callback, $rollback_callback, $options, $transactionContext);
        return $transaction;
    }

    /**
     * Returns the callback container.
     *
     * @return CallbackContainer
     * @throws InvalidArgumentException
     * @throws RunTimeException
     */
    public function getCallbackContainer(): callbackContainer
    {
        if (!$this->callback_container) {
            $callback_container = new CallbackContainer([], 0, $this);
            $this->callback_container =& $callback_container;
            $this->callback_container->set_transaction($this);
        }
        return $this->callback_container;
    }

    /**
     * Returns the result (retunr value) of the execution of $this->code
     * @return mixed
     * @see run()
     * @see set_code()
     */
    public function get_run_result()
    {
        return $this->run_result;
    }

    /**
     * Executes the code and commits
     * In general a method that runs a transaction is not supposed to return anything but we will return anyway the result
     * Will rerun the transaction automatically if we have the options set as
     * rerun_at_deadlock = true
     * rerun_at_deadlock_attempts > 0
     * @return mixed Whatever is returned by the execution of the code.
     * @throws TransactionException
     * @throws InvalidArgumentException
     */
    public function run()
    {
        if (!$this->is_runnable()) {
            throw new TransactionException($this, sprintf(t::_('Trying to run a transaction that is not runnable (means there is no attached executable PHP code to it).')));
        }

        $rerun = false;//should the transaction be reran in case of a deadlock or timeout (db related issues)
        if (self::get_config_key('rerun_at_transient_error_attempts') > 0) {
            $rerun = true;
        }

        $run_successful = FALSE;
        $run_counter = 0;
        $wait_time = 0;
        $total_wait_time = 0;
        if ($rerun) {
            //catch the DB exceptions and rerun the transaction
            do {
                try {
                    $run_counter++;
                    $code = $this->get_code();
                    $this->run_result = $code($this);
                    $run_successful = TRUE;
                } catch (TransientErrorException $exception) {
                    //will be retried if this is enabled in the options
                    if (self::get_config_key('increment_wait_time_between_attempts')) {
                        //on the second attempt wait more time, on third even more etc...
                        $wait_time += self::get_config_key('wait_in_seconds_between_attempts');
                    } else {
                        $wait_time = self::get_config_key('wait_in_seconds_between_attempts');
                    }
                    $total_wait_time += $wait_time;

                    sleep($wait_time);//wait some time before retrying
                }
            } while (!$run_successful && $run_counter < self::get_config_key('rerun_at_transient_error_attempts'));
        } else {
            $code = $this->get_code();
            $this->run_result = $code($this);
        }

        if (!$run_successful && isset($exception)) {
            $message = sprintf(t::_('The transaction failed after %s attempts and waiting time of %s seconds. The error is: %s.'), $run_counter, $total_wait_time, $exception->getMessage());
            throw new TransactionException($this, $message, $exception);
        }

        $this->run_result = $code();

        $this->commit();

        return $this->run_result;
    }

    /**
     * Returns information about where the transaction was started.
     * It may return NULL as on production this may be disabled.
     * @return TraceInfoObject|NULL
     *
     * @since 0.7.2
     * @author vesko@azonmedia.com
     */
    public function get_transaction_start_bt_info(): ?TraceInfoObject
    {
        return $this->transaction_start_bt_info;
    }

    /**
     * Returns information about where the transaction was rolled back (if it was rolled back).
     * It may return NULL as on production this may be disabled or because the transaction hasnt been rolled back.
     * @return TraceInfoObject|NULL
     *
     * @since 0.7.2
     * @author vesko@azonmedia.com
     */
    public function get_transaction_rollback_bt_info(): ?TraceInfoObject
    {
        return $this->transaction_rollback_bt_info;
    }

    /**
     * The transaction is rerunnable is the code was provided
     * @return bool
     */
    public function is_runnable(): bool
    {
        return $this->code ? TRUE : FALSE;
    }

    /**
     * Returns the code
     * @return null|callable
     */
    public function get_code(): ?callable
    {
        return $this->code;
    }

    public function get_context(): ?TransactionContext
    {
        return $this->transactionContext;
    }

    /**
     * To be used when setting the code after the transaction was started
     * @param callable $code
     * @return void
     * @throws InvalidArgumentException
     * @see get_code()
     * @see is_runnable()
     */
    public function set_code($code)
    {
        if (!is_callable($code)) {
            throw new InvalidArgumentException(sprintf(t::_('transaction::get_code() expects one parameter that needs to be a callable.')));
        }
        $this->code = $code;
    }

    public function is_started(): bool
    {
        return $this->get_status() == self::STATUS_STARTED;
    }

    /**
     * Checks is this transaction committed
     * @return bool
     */
    public function is_commited(): bool
    {
        return $this->get_status() == self::STATUS_COMMITTED;
    }

    public function is_rolled_back(): bool
    {
        //return $this->get_status() == self::STATUS_ROLLED_BACK || $this->get_status() == self::STATUS_MASTER_ROLLED_BACK;
        return $this->get_status() == self::STATUS_ROLLED_BACK;
    }

    public function is_saved(): bool
    {
        return $this->get_status() == self::STATUS_SAVED;
    }

    /**
     * Returns TRUE is the transaction in its current status has no possible transitions
     * @return bool
     *
     * @see self::STATUS_TRANSITIONS
     *
     * @author vesko@azonmedia.com
     * @created 16.10.2018
     * @since 0.7.4
     */
    public function is_in_final_status(): bool
    {
        return count(self::STATUS_TRANSITIONS[$this->get_status()]) ? FALSE : TRUE;
    }

    public function get_id()
    {
        return $this->get_object_internal_id();
    }

    /*
    protected function set_as_a_rerun() {
        $this->is_arerun_flag = true;
    }

    public function is_a_rerun() : bool
    {
        return $this->is_a_rerun_flag;
    }
    */

    /**
     * To be called by scopeReference - sets this transaction as interrupted
     */
    public function set_transaction_as_interrupted(): void
    {
        if (!$this->transaction_rollback_reason) {
            $this->transaction_rollback_reason = self::ROLLBACK_REASON_IMPLICIT;
        }

        $this->transaction_was_interrupted_flag = TRUE;
    }

    /**
     * Was this transaction interrupted (versus a parent one)
     * Will be set to true only if this transaction was interrupted by scope reference not with explicit rollback()
     * Transaction being interrupted does not necessarily mean there will be interrupting exception - the transaction may get interrupted due by scopeReference also because of missing commit() or explicit rollback (due to cycle or return)
     */
    public function is_transaction_interrupted(): bool
    {
        return $this->transaction_was_interrupted_flag;
    }

    /**
     * Sets this transaction as the one that started the rollback of the nested tree.
     * If a nested transaction is rolled back because a parent one is rolled back it wont have this flag raised
     */
    public function set_this_transaction_as_rolled_back(): void
    {
        $this->this_transaction_was_rolled_back_flag = TRUE;
    }

    /**
     * Was this transaction rolled back or it was a parent one that was rolled back (thus rolling back this one too)
     */
    public function is_this_transaction_rolled_back(): bool
    {
        return $this->this_transaction_was_rolled_back_flag;
    }

    /**
     * To be called by scopeReferenceTransactionTracker destructor
     * We are interrupting all nested transactions but there may be a master or higher level one that is not interrupted and must not have their interrupting exception set
     * Of  course if the master one gets interrupted it will updated all nested ones
     * @param \Throwable $exception
     */
    public function set_interrupting_exception(\Throwable $exception = null)
    {
        if (!$this->transaction_rollback_reason) {
            $this->transaction_rollback_reason = self::ROLLBACK_REASON_EXCEPTION;
        }

        $this->interrupting_exception = $exception;
        //$this->this_transaction_was_interrupted_flag = true;
        //it shouldnt be set here but in the scopeReference because a transaction can get interrupted by return or missing commit() too, not just an exception
        foreach ($this->get_nested() as $transaction) {
            $transaction->set_interrupting_exception($exception);
            //$transaction->parent_transaction_was_interrupted_flag = true;
        }
    }

    /**
     * If the transaction was interrupted by an exception this will be returned here. Null otherwise
     */
    //public function get_interrupting_exception() : ?BaseException {
    public function get_interrupting_exception(): ?\Throwable
    {
        return $this->interrupting_exception;
    }

    /**
     * Returns where the transaction was explicitly rolled back
     * Will return NULL is it is not rolled back or was rolled back due to other reasons (scope reference destroyed due to return, break or exception)
     *
     * @return NULL|array If there is no explicit rollback NUll will be returned. Otherwise a singledimensional array with file, filne, function, class, type indexes.
     * @created 19.10.2018
     * @since 0.7.4
     */
    public function get_explicit_rollback_frame(): ?array
    {
        $ret = NULL;
        $bt = $this->get_transaction_rollback_bt_info();
        if ($bt) {
            $frame = StackTraceUtil::get_stack_frame_by(TransactionManager::class, 'rollback', debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

            if ($frame) {
                $ret = $frame;
            }
        }

        return $ret;
    }

    /**
     * Returns where the transaction was implicitly rolled back (due to scope reference goind out of scope)
     * Will return NULL is it is not rolled back or was rolled back due to other reasons (explicit rollback)
     *
     * @return null|array If there is no explicit rollback NUll will be returned. Otherwise a singledimensional array with file, filne, function, class, type indexes.
     * @created 19.10.2018
     * @since 0.7.4
     */
    public function get_implicit_rollback_frame(): ?array
    {
        $ret = NULL;
        $bt = $this->get_transaction_rollback_bt_info();
        if ($bt) {

            //due to legacy code it may be done through pdo... so lets first look for this
            $frame = StackTraceUtil::get_stack_frame_by(ScopeReferenceTracker::class, '__destruct', debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

            if ($frame) {
                $ret = $frame;
            }
        }
        return $ret;
    }

    /**
     * @return int
     * @throws LogicException
     */
    public function get_transaction_rollback_reason(): int
    {
        if (!isset(self::ROLLBACK_REASON_MAP[$this->transaction_rollback_reason])) {
            throw new LogicException(sprintf(t::_('An invalid value "%s" is set to $transction->transaction_rollback_reason.'), AlphaNumUtil::as_string($this->transaction_rollback_reason)));
        }

        return $this->transaction_rollback_reason;
    }

    /**
     * Returns additional info why the transaction was rolled back.
     * @return NULL|\Throwable|array|transaction
     *
     * @throws LogicException
     * @author vesko@azonmedia.com
     * @created 19.10.2018
     * @since 0.7.4
     *
     * @see self::
     * Returns NULL for self::ROLLBACK_REASON_UNKNOWN
     * Returns exception for self::ROLLBACK_REASON_EXCEPTION
     * Returns array (backtrace frame) for self::ROLLBACK_REASON_EXPLICIT
     * Returns array (backtrace frame) for self::ROLLBACK_REASON_IMPLICIT
     *
     */
    public function get_transaction_rollback_reason_info() /* mixed */
    {
        $reason = $this->get_transaction_rollback_reason();
        switch ($reason) {
            case self::ROLLBACK_REASON_UNKNOWN:
                $ret = NULL;
                break;
            case self::ROLLBACK_REASON_EXCEPTION:
                $ret = $this->get_interrupting_exception();
                break;
            case self::ROLLBACK_REASON_EXPLICIT:
                $ret = $this->get_explicit_rollback_frame();
                break;
            case self::ROLLBACK_REASON_IMPLICIT:
                $ret = $this->get_implicit_rollback_frame();
                break;
            case self::ROLLBACK_REASON_PARENT:
                $ret = $this->get_parent();
                break;
            default:
                throw new LogicException(sprintf(t::_('An unsupported reason type "%s" returned by transaction::get_transaction_rollback_reason().'), AlphaNumUtil::as_string($reason)));
        }
        return $ret;
    }

    /**
     * Returns the rollback reason for the transaction as string. To be used in transaction callbacks.
     * To be used only on rolled back transactions (or transactions that are about to be rolled back - in a callback on MODE_BEFORE_ROLLBACK).
     * Becasue it should be possible to be used on transactions that are not yet rolled back but are about to be rolled back there is no check in the method is_rolled_back().
     * If used improperly or the reason can not be found it will return 'Unknown reason'.
     *
     * @return string
     *
     * @throws LogicException
     * @since 0.7.4
     * @author vesko@azonmedia.com
     * @created 19.10.2018
     */
    public function get_transaction_rollback_reason_info_as_string(): string
    {
        //$reason = $this->get_rollback_reason_info();
        $reason = $this->get_transaction_rollback_reason();
        $reason_info = $this->get_transaction_rollback_reason_info();

        switch ($reason) {
            case self::ROLLBACK_REASON_UNKNOWN:
                $ret = sprintf(t::_('Transaction not rolled back or reason unknown.'));
                break;
            case self::ROLLBACK_REASON_EXCEPTION:
                $ex = $reason_info;
                $ret = sprintf(t::_('The transaction failed due to exception %s thrown from %s#%s : "%s"'), get_class($ex), $ex->getFile(), $ex->getLine(), $ex->getMessage());
                break;
            case self::ROLLBACK_REASON_EXPLICIT:
                $frame = $reason_info;
                $ret = sprintf(t::_('The transaction failed due to explicit rollback at %s#%s.'), $frame['file'], $frame['line']);
                break;
            case self::ROLLBACK_REASON_IMPLICIT:
                $frame = $reason_info;
                $ret = sprintf(t::_('The transaction failed due to implicit rollback (end of scope without commit() or rollback() ) at %s#%s.'), $frame['file'], $frame['line']);
                break;
            case self::ROLLBACK_REASON_PARENT:
                $parent_transaction = $reason_info;
                $ret = sprintf(t::_('The transaction failed due to the parent transaction rollback. The parent transaction was rolled back due to: %s') . $parent_transaction->get_transaction_rollback_reason_info_as_string());
                break;
            default:
                throw new LogicException(sprintf(t::_('An unsupported reason type "%s" returned by transaction::get_transaction_rollback_reason().'), $reason['reason']));
        }
        return $ret;
    }

    /**
     * Checks is the rollback info all valid:
     * - if it is set to exception - is there an exception
     * - if set to explicit - is there backtrace frame
     * - if set to implicit - is there backtrace frame
     * - if set to parent - is there parent transaction
     *
     * On error will just log the problem but will not throw an exception
     * @throws LogicException
     */
    private function validate_rollback_reason(): void
    {
        $reason = $this->get_transaction_rollback_reason();

        switch ($reason) {
            case self::ROLLBACK_REASON_UNKNOWN:
                $message = sprintf(t::_('No transaction rollback reason is set for transaction "%s" with rollback backtrace %s'), $this->get_object_internal_id(), $this->get_transaction_rollback_bt_info()->getTraceAsString());
                break;
            case self::ROLLBACK_REASON_EXCEPTION:
                if (!$this->get_interrupting_exception()) {
                    $message = sprintf(t::_('The transaction "%s" rollback reason is set to exception but there is no interrupting exception set. The backtrace is %s'), $this->get_object_internal_id(), $this->get_transaction_rollback_bt_info()->getTraceAsString());
                }
                break;
            case self::ROLLBACK_REASON_EXPLICIT:
                if (!$this->get_explicit_rollback_frame()) {
                    $message = sprintf(t::_('The transaction "%s" rollback reason is set to explicit but there is no backtrace frame containing explicit rollback found in the backtrace %s'), $this->get_object_internal_id(), $this->get_transaction_rollback_bt_info()->getTraceAsString());
                }
                break;
            case self::ROLLBACK_REASON_IMPLICIT:
                if (!$this->get_implicit_rollback_frame()) {
                    $message = sprintf(t::_('The transaction "%s" rollback reason is set to implicit but there is no backtrace frame containing explicit rollback found in the backtrace %s'), $this->get_object_internal_id(), $this->get_transaction_rollback_bt_info()->getTraceAsString());
                }
                break;
            case self::ROLLBACK_REASON_PARENT:
                if (!$this->has_parent()) {
                    $message = sprintf(t::_('The transaction "%s" rollback reason is set to parent but there is no parent transaction. The backtrace is %s'), $this->get_object_internal_id(), $this->get_transaction_rollback_bt_info()->getTraceAsString());
                }
                break;
        }
        if (isset($message)) {
            // TODO check what to do with logger
            self::logger()->debug($message);
        }
    }

    /**
     * This internally either rollbacks the whole transaction or just to the last savepoint
     * We rollback to the savepoint on the parent transaction
     * @param bool $explicit_rollback
     * @throws InvalidArgumentException
     * @throws TransactionException
     * @throws LogicException
     */
    public function rollback($explicit_rollback = FALSE): void
    {
        if ($this->is_cloned()) {
            throw new TransactionException($this, sprintf(t::_('This transaction is cloned. %s() can not be executed.'), __METHOD__));
        }

        $current_status = $this->get_status();

        //this should not happen
        //if ($current_status == self::STATUS_COMMITTED) {
        //    return;// a committed transaction can not be rolled back
        //}

        if ($current_status != self::STATUS_STARTED && $current_status != self::STATUS_SAVED) {
            throw new TransactionException($this, sprintf(t::_('A transaction "%s" of type "%s" can be rolled back only if it is in status started or saved. This transaction is in status "%s".'), $this->get_object_internal_id(), get_class($this), $this->get_status_as_string()));
        }

        //if (!Kernel::is_batch_mode()) {
        if (true) {
            $this->transaction_rollback_bt_info = new TraceInfoObject('transaction ROLLED BACK');
        }

        //the rollback reason may already be set (parent, exception, implicit)
        // if (!$this->transaction_rollback_reason) {
        //     //if not we need to check the backtrace... - probably it is an explicit interrupt
        //     //the only remaining reason remains to be explicit
        //     $this->transaction_rollback_reason = self::ROLLBACK_REASON_EXPLICIT;
        // }
        //the above assumption is probably wrong - setting to explicit is moved to the TXM
        if ($explicit_rollback) {
            $this->transaction_rollback_reason = self::ROLLBACK_REASON_EXPLICIT;
        }

        //some validation checks
        $this->validate_rollback_reason();


        //no matter is there or not a parent transaction we need to rollback all nested ones
        //this needs to be controlled from here and not rely on the scopereference to get broken for all nested ones - this may not invoke the callbacks
        //and makes sense the rollback to get first executed on the nested transactions and then on this one
        //here it should walk in the nested transactions from the same level in reverse order
        foreach (array_reverse($this->get_nested()) as $nested_transaction) {
            /** @var transaction $nested_transaction */
            //try to rollback all nested unless they are already rolled back
            if ($nested_transaction->get_status() != self::STATUS_ROLLED_BACK) {
                $nested_transaction->transaction_rollback_reason = self::ROLLBACK_REASON_PARENT;
                $nested_transaction->rollback();
            }
        }

        //then check/set from which transaction the rollback started
        if ($this->has_parent()) {
            $parent_transaction = $this->get_parent();
            if (!$parent_transaction->is_this_transaction_rolled_back()) {
                //then this is the first transaction in the tree that is rolled back
                $this->set_this_transaction_as_rolled_back();
            }
        } else {
            //this is the master transaction
            $this->set_this_transaction_as_rolled_back();
        }

        $p = $this->get_parent();
        if ($p) {
            // TODO check logtofile arguments
            Kernel::logtofile('CCC', get_class($this) . ' ' . $this->get_object_internal_id() . ' has parent ' . get_class($p) . ' ' . $p->get_object_internal_id());
        } else {
            Kernel::logtofile('CCC', get_class($this) . ' ' . $this->get_object_internal_id() . ' has NO parent');
        }

        if ($this->has_parent()) {
            $this->execute_pre_callbacks();

            if ($this->get_status() == $current_status) {
                $this->execute_pre_rollback_callbacks();
            }

            if (self::get_config_key('enable_callbacks_tracing')) {
                // TODO check if this needs to be changed
                Kernel::logtofile_indent(self::get_config_key('callbacks_tracing_store'), get_class($this) . ' ' . $this->get_object_internal_id() . ' pre rollback callbacks executed', 0);
            }

            if ($this->get_status() == $current_status) { //this check is needed because we execute callbacks before this and these may change the status
                //$savepoint = $this->get_parent()->getSavepointName();
                $savepoint = $this->getSavepointName();//the savepoint name is now named after the current transaction but is executed on the parent one

                //$this->set_status(self::STATUS_ROLLED_BACK);//??? this also calls the callbacks
                //$this->set_nested_status(self::STATUS_ROLLED_BACK);//TODO CHECK - vesko - this also need to be here
                //the above is wrong - we need to execute the rollback recursively down.
                //$this->rollbackToSavepoint($savepoint);//this actually should be called on the parent transaction as the savepoint is on the parent transaction
                //but the method will internally retrieve the correct transaction based on the savepoint name provided (which name is retrieved from the parent transaction above)
                $this->get_parent()->rollbackToSavepoint($savepoint);

                $this->set_status(self::STATUS_ROLLED_BACK);//this transaction is rolled back but the savepoint is on the parent transaction

                //here - BEFORE the callbacks are executed - we need to update the current_transaction in PDO
                self::TransactionManager()->setCurrentTransaction($this->get_parent());//we know there is a parent transaction - in this case we  dont need to provide the transaction type

                if (self::get_config_key('enable_transactions_tracing')) {
                    Kernel::logtofile_indent(self::get_config_key('transactions_tracing_store'), get_class($this) . ' ' . $this->get_object_internal_id() . ' rollback to save point executed', 0);
                }

                //$this->callback_mode = callbackContainer::MODE_AFTER_ROLLBACK;
                $this->execute_after_rollback_callbacks();
                if (self::get_config_key('enable_callbacks_tracing')) {
                    Kernel::logtofile_indent(self::get_config_key('callbacks_tracing_store'), get_class($this) . ' ' . $this->get_object_internal_id() . ' after rollback callbacks executed', 0);
                }

                $this->execute_after_callbacks();
            }
        } else {
            $this->execute_pre_callbacks();

            if ($this->get_status() == $current_status) {
                $this->execute_pre_rollback_callbacks();
            }

            if ($this->get_status() == $current_status) {
                $this->execute_before_master_callbacks();
            }

            if (self::get_config_key('enable_callbacks_tracing')) {
                Kernel::logtofile_indent(self::get_config_key('callbacks_tracing_store'), get_class($this) . ' ' . $this->get_object_internal_id() . ' pre rollback callbacks executed', 0);
            }

            if ($this->get_status() == $current_status) {
                $this->execute_pre_master_rollback_callbacks();
            }

            if ($this->get_status() == $current_status) {//if the status hasnt changed
                $this->set_status(self::STATUS_ROLLED_BACK);

                if (self::get_config_key('enable_transactions_tracing')) {
                    if ($this->is_in_commit_callback_flag) {
                        Kernel::logtofile_indent(self::get_config_key('transactions_tracing_store'), get_class($this) . ' ' . $this->get_object_internal_id() . ' rollback forced in commit callback', -1);
                    } else {
                        Kernel::logtofile_indent(self::get_config_key('transactions_tracing_store'), get_class($this) . ' ' . $this->get_object_internal_id() . ' rollback', -1);
                    }
                }

                $this->execute_rollback();

                //here - BEFORE the callbacks are executed - we need to update the current_transaction in PDO
                $parent_transaction = NULL;//there is no parent transaction - we already checked in the IF
                //TXM::setCurrentTransaction($parent_transaction , self::get_config_key('transaction_type'));
                self::TransactionManager()->setCurrentTransaction($parent_transaction, get_class($this));

                if (self::get_config_key('enable_transactions_tracing')) {
                    Kernel::logtofile_indent(self::get_config_key('transactions_tracing_store'), get_class($this) . ' ' . $this->get_object_internal_id() . ' rollback executed', 0);
                }

                //$this->callback_mode = callbackContainer::MODE_AFTER_ROLLBACK;
                $this->execute_after_rollback_callbacks();

                //$this->callback_mode = callbackContainer::MODE_AFTER_MASTER_ROLLBACK;
                $this->execute_after_master_rollback_callbacks();

                //$this->callback_mode = callbackContainer::MODE_AFTER_MASTER;
                $this->execute_after_master_callbacks();

                $this->execute_after_callbacks();

                if (self::get_config_key('enable_callbacks_tracing')) {
                    Kernel::logtofile_indent(self::get_config_key('callbacks_tracing_store'), get_class($this) . ' ' . $this->get_object_internal_id() . ' after rollback callbacks executed', 0);
                }
            }

            $destroy_current_transaction = true;
        } // end if has_parent


        if (!empty($destroy_current_transaction)) {
            $this->destroy();
        }
    } //end rollback()

    /**
     * @throws InvalidArgumentException
     * @throws TransactionException
     */
    public function commit(): void
    {
        if ($this->is_cloned()) {
            throw new TransactionException($this, sprintf(t::_('This transaction is cloned. %s() can not be executed.'), __METHOD__));
        }

        $current_status = $this->get_status();

        if ($current_status != self::STATUS_STARTED && $current_status != self::STATUS_SAVED) {
            throw new TransactionException($this, sprintf(t::_('The transaction "%s" of type "%s" can be committed only if it is in status started or saved. This transaction is in status "%s".'), $this->get_object_internal_id(), get_class($this), $this->get_status_as_string()));
        }


        if (self::get_config_key('enable_transactions_tracing')) {
            if ($this->is_in_rollback_callback_flag) {
                Kernel::logtofile_indent(self::get_config_key('transactions_tracing_store'), get_class($this) . ' ' . $this->get_object_internal_id() . ' commit forced in rollback callback', -1);
            } else {
                Kernel::logtofile_indent(self::get_config_key('transactions_tracing_store'), get_class($this) . ' ' . $this->get_object_internal_id() . ' commit', -1);
            }
        }


        if ($this->has_parent()) {
            $this->execute_pre_callbacks();

            if ($this->get_status() == $current_status) {
                $this->execute_pre_save_callbacks();
            }

            if (self::get_config_key('enable_callbacks_tracing')) {
                Kernel::logtofile_indent(self::get_config_key('callbacks_tracing_store'), get_class($this) . ' ' . $this->get_object_internal_id() . ' pre save callbacks executed', 0);
            }

            if ($this->get_status() == self::STATUS_STARTED) {
                $this->set_status(self::STATUS_SAVED);
                //we need to mark this one as saved but there is no really need to create a savepoint
                //there doesnt seem to be a case when we will be reverting to this savepoint - the parent transaction will not revert to this one
                //if there is a revert there will be a revert to the savepoint when this transaction started or to the savepoint when the parent transaction started

                //old comments here...
                //$this->createSavepoint();//this in it self contains set_status(STATUS_SAVED)
                //the above is not really needed - we will never rollback to the savepoint created at the end of successfully saved transaction
                //we may rollback to the savepoint of the parent transaction if the nexted one rollsback


                //here - BEFORE the callbacks are executed - we need to update the current_transaction in PDO
                self::TransactionManager()->setCurrentTransaction($this->get_parent());//we know there is a parent transaction - in this case we  dont need to provide the transaction type

                $this->execute_after_save_callbacks();

                $this->execute_after_callbacks();

                if (self::get_config_key('enable_callbacks_tracing')) {
                    Kernel::logtofile_indent(self::get_config_key('callbacks_tracing_store'), get_class($this) . ' ' . $this->get_object_internal_id() . ' after save callbacks executed', 0);
                }
            } else {
                //Kernel::logtofile('DV_107', get_class($this).' '.$this->get_object_internal_id().' '.$this->get_status_as_string() );
            }
        } else { //this is the master transaction


            $this->execute_pre_callbacks();

            if ($this->get_status() == $current_status) {
                $this->execute_pre_commit_callbacks();//the precommit callback on this transaction
            }

            if ($this->get_status() == $current_status) {
                $this->execute_before_master_callbacks();
            }

            //if the status hasnt changed
            if ($this->get_status() == $current_status) {
                //$this->callback_mode = callbackContainer::MODE_BEFORE_MASTER_COMMIT;
                $this->execute_pre_master_commit_callbacks();//all the other
            }
            if (self::get_config_key('enable_callbacks_tracing')) {
                Kernel::logtofile_indent(self::get_config_key('callbacks_tracing_store'), get_class($this) . ' ' . $this->get_object_internal_id() . ' pre commit callbacks executed', 0);
            }

            //if the status hasnt changed
            if ($this->get_status() == $current_status) {
                //$this->set_status(self::STATUS_SAVED);

                //$this->set_status(self::STATUS_COMMITTED);//there is no parent - it should be COMMITED
                //$this->set_nested_status(self::STATUS_COMMITTED);
                //we first call the pre_commit callbacks - on transactions that have status SAVED
                $this->call_nested_before_commit_callbacks();
                //on master transaction commit we need to update all saved transactions to committed
                //then we change the status on all SAVED transactions to COMMITED (these may be less that the previous number of transactions on which the pre_commit callback was executed in case the callback changed the status from SAVED to ROLLEDBACK)

                $this->set_status(self::STATUS_COMMITTED);//unlike the rest committed status is being propagated to all


                $this->execute_commit();


                //here - BEFORE the callbacks are executed - we need to update the current_transaction in PDO
                $parent_transaction = NULL;//there is no parent transaction - we already checked in the IF
                //TXM::setCurrentTransaction($parent_transaction , self::get_config_key('transaction_type'));
                self::TransactionManager()->setCurrentTransaction($parent_transaction, get_class($this));

                if (self::get_config_key('enable_transactions_tracing')) {
                    Kernel::logtofile_indent(self::get_config_key('transactions_tracing_store'), get_class($this) . ' ' . $this->get_object_internal_id() . ' commit executed', -1);
                }

                $this->call_nested_after_commit_callbacks();

                //$this->callback_mode = callbackContainer::MODE_AFTER_COMMIT;
                $this->execute_after_commit_callbacks();

                //$this->callback_mode = callbackContainer::MODE_AFTER_MASTER_COMMIT;
                $this->execute_after_master_commit_callbacks();

                //$this->callback_mode = callbackContainer::MODE_AFTER_MASTER;

                $this->execute_after_master_callbacks();

                $this->execute_after_callbacks();

                if (self::get_config_key('enable_callbacks_tracing')) {
                    Kernel::logtofile_indent(self::get_config_key('callbacks_tracing_store'), get_class($this) . ' ' . $this->get_object_internal_id() . ' after commit callbacks executed', 0);
                }
            }

            $destroy_current_transaction = true;
        } //end if-else has_parent

        if (!empty($destroy_current_transaction)) {
            $this->destroy();
        }
    }

    private function call_nested_before_commit_callbacks(): void
    {
        if ($this->get_status() == self::STATUS_SAVED && $this->has_parent()) { //we need to skip the master transaction as it executes the callbacks in the commit() and to avoid double execution
            $this->execute_pre_commit_callbacks();
        }
        foreach ($this->get_nested() as $transaction) {
            $transaction->call_nested_before_commit_callbacks();
        }
    }

    private function call_nested_after_commit_callbacks(): void
    {
        if ($this->get_status() == self::STATUS_COMMITTED && $this->has_parent()) { //we need to skip the master transaction as it executes the callbacks in the commit() and to avoid double execution

            $this->execute_after_commit_callbacks();
        }
        foreach ($this->get_nested() as $transaction) {
            $transaction->call_nested_after_commit_callbacks();
        }
    }

    /**
     * Executes the code (if the transaction is runnable) and commits the transaction
     * @return mixed returns the value returned by the code
     * @throws TransactionException
     * @throws InvalidArgumentException
     */
    public function __invoke()
    {
        $ret = $this->run();
        return $ret;
    }

    /**
     * Rollback the transaction if by the time the destructor is called is in status
     */
    public function __destruct()
    {
        //Kernel::logtofile_backtrace('dbg_destruct');
        //Kernel::logtofile_append(self::get_config_key('transactions_tracing_store'),'destr',0);
        if ($this->is_cloned()) {
            return;
        }

        $this->destroy();

        parent::__destruct();
    }

    protected function _before_destroy(): void
    {
        if ($this->get_status() == self::STATUS_STARTED || $this->get_status() == self::STATUS_SAVED) {
            if (self::get_config_key('enable_transactions_tracing')) {
                Kernel::logtofile_indent(self::get_config_key('transactions_tracing_store'), get_class($this) . ' ' . $this->get_object_internal_id() . ' rollback-by-destructor', 0);
            }
            $this->rollback();
        }
        //aslo make sure all the commit callbacks are cleared if this was the master transaction
        //they should have been either executed by now (and cleared) or just cleeared
        if (!$this->has_parent()) {
            //self::$commit_callbacks = array();
        }

        //also destroy any nested transactions
        foreach ($this->nested_transactions as &$nested_transaction) {
            if (is_object($nested_transaction)) {
                $nested_transaction->destroy();
                $nested_transaction = null;
            }
        }

        if ($this->callback_container) {
            $this->callback_container->destroy();
            $this->callback_container = NULL;
        }

        //foreach ($this->statements as &$statement) {
        //    $statement = null;
        //}
        //parent::destroy();//there is no parent::destroy()

        //if (method_exists($this,'_after_destroy')) {
        //    call_user_func_array( [$this, '_after_destroy'], [] );
        //}
    }

    /**
     * The transactions with higher priority will be committed first when part of a distributed transaction.
     * The priority should be set based on the chances the transaction to be successful.
     * For example a database transaction has less changes than an object transaction to be committed successfully (the object transaction has 100% chance) but has higher than a remote transaction (there are a lot more things that can go wrong in a remote transaction)
     */
    public function get_priority(): int
    {
        return $this->priority;
    }

    /**
     *
     * @see self::$status_map
     *
     */
    public function get_status(): int
    {
        return $this->status;
    }

    public function get_status_as_string(): string
    {
        return self::$status_map[$this->status];
    }

    public function get_parent(): ?transaction
    {
        return $this->parent_transaction;
    }

    public function has_parent(): bool
    {
        return $this->parent_transaction ? TRUE : FALSE;
    }

    public function is_master(): bool
    {
        return $this->parent_transaction ? FALSE : TRUE;
    }

    public function add_nested(transaction &$transaction): void
    {
        $this->nested_transactions[] =& $transaction;
    }

    /**
     * @return transaction[]
     */
    public function get_nested(): array
    {
        return $this->nested_transactions;
    }

    public function is_in_rollback_callback(): bool
    {
        return $this->is_in_rollback_callback_flag;
    }

    public function is_in_commit_callback(): bool
    {
        return $this->is_in_commit_callback_flag;
    }

    public function is_in_save_callback(): bool
    {
        return $this->is_in_save_callback_flag;
    }

    public function is_in_callback(): bool
    {
        return $this->is_in_rollback_callback() || $this->is_in_commit_callback() || $this->is_in_save_callback();
    }

    public function is_cloned(): bool
    {
        return $this->is_cloned_flag;
    }

    public function __clone()
    {
        $this->is_cloned_flag = TRUE;
    }
    
    /**
     * Returns the outermost transaction - the parent of all transactions
     * MUST NOT be passed by reference (because there is reassignment inside
     * @return Transaction|null
     */
    public function get_master_transaction()
    {
        $transaction = $this;
        $master_transaction = $transaction;
        while ($transaction) {
            $transaction = $transaction->get_parent();
            if ($transaction) {
                $master_transaction = $transaction;
            }
        }
        
        return $master_transaction;
    }

    /**
     * Returns the levels of transaction nesting;
     * MUST NOT be passed by reference (because there is reassignment inside
     * @param transaction $transaction
     * @return int
     */
    public static function get_transactions_nesting(transaction $transaction)
    {
        $nesting = 0;
        while ($transaction) {
            $transaction = $transaction->get_parent();
            $nesting++;
        };
        return $nesting;
    }

    /* protected methods */

    /**
     * This is supposed to be called only from the constructor thats why is protected
     * @throws InvalidArgumentException
     * @throws TransactionException
     * @return void
     */
    protected function begin(): void
    {
        //then update the current transaction in the transactionManager
        self::TransactionManager()->setCurrentTransaction($this);


        if ($this->is_cloned()) {
            throw new TransactionException($this, sprintf(t::_('This transaction is cloned. %s() can not be executed.'), __METHOD__));
        }

        if (self::get_config_key('enable_transactions_tracing')) {
            Kernel::logtofile_indent(self::get_config_key('transactions_tracing_store'), get_class($this) . ' ' . $this->get_object_internal_id() . ' begin', +1);
        }
        if ($this->has_parent()) {
            $savepoint_name = $this->getSavepointName();
            $this->get_parent()->createSavepoint($savepoint_name);//when a nested transaction is started a savepoint is created on the parent transaction
            //a transaction can have more than one nested - the second one will overwrite the savepoint of the first one (as it has the same name - and the first one completed successfully)
        } else {

            //begin logic
            $this->set_status(self::STATUS_STARTED);
            $this->execute_begin();
        }
    }

    abstract protected function execute_begin(): bool;

    abstract protected function execute_commit(): bool;

    abstract protected function execute_rollback(): bool;

    abstract protected function execute_create_savepoint(string $savepoint): bool;

    abstract protected function execute_rollback_to_savepoint(string $savepoint): bool;

    abstract protected function execute_release_savepoint(string $savepoint): bool;

    /**
     * This is called when BEGIN is called but there is a parent transaction
     * We do savepoint on the parent transaction (which means the savepoint uses the name of the parent transaction)
     * And we leave the current one with status STARTED
     * @param string $savepoint_name
     * @return bool
     * @throws TransactionException
     */
    final protected function createSavepoint(string $savepoint_name): bool
    {
        if ($this->is_cloned()) {
            throw new TransactionException($this, sprintf(t::_('This transaction is cloned. %s() can not be executed.'), __METHOD__));
        }

        if (self::get_config_key('enable_transactions_tracing')) {
            Kernel::logtofile_indent(self::get_config_key('transactions_tracing_store'), get_class($this) . ' ' . $this->get_object_internal_id() . ' create savepoint', 0);
        }

        $ret = $this->execute_create_savepoint($savepoint_name);
        self::$savepoints[$savepoint_name] =& $this;

        return $ret;
    }

    /**
     * It doesnt matter on which transaction this method is invoked.
     * It will internally retrieve the transaction based on the provided savepoint name
     * @param string $savepoint
     * @return bool
     * @throws TransactionException
     */
    final protected function rollbackToSavepoint(string $savepoint): bool
    {
        if ($this->is_cloned()) {
            throw new TransactionException($this, sprintf(t::_('This transaction is cloned. %s() can not be executed.'), __METHOD__));
        }

        if (!isset(self::$savepoints[$savepoint])) {
            $message = sprintf(t::_('Trying to rollback to a non-existant savepoint "%s".'), $savepoint);
            throw new TransactionException($this, $message);
        }

        $savepoint_transaction = self::$savepoints[$savepoint];

        if (self::get_config_key('enable_transactions_tracing')) {
            Kernel::logtofile_indent(self::get_config_key('transactions_tracing_store'), get_class($this) . ' ' . $this->get_object_internal_id() . ' rollback', 0);
            Kernel::logtofile_indent(self::get_config_key('transactions_tracing_store'), get_class($this) . ' ' . $savepoint_transaction->get_object_internal_id() . ' rollback to savepoint', -1);
        }

        $ret = $savepoint_transaction->execute_rollback_to_savepoint($savepoint);
        //$this->set_status(self::STATUS_ROLLED_BACK);//do not set it here - see createSavepoint why
        //the above is wrong
        //$this->set_status(self::STATUS_ROLLED_BACK);//wrong - this on the parent transaction not on the one that is actually rolled back

        return $ret;
    }

    /* protected static methods */


    /* private methods */

    private function getSavepointName(): string
    {
        $savepoint = 'SP' . $this->get_object_internal_id();
        return $savepoint;
    }

    public static function getTransactionIdFromSavepointName(string $savepoint): string
    {
        return substr($savepoint, 2);
    }

    /**
     * Executes the MODE_BEFORE event on the current transaction.
     * It executes on transaction end no matter the status.
     * This is the very first callback that gets executed.
     * @return void
     * @author vesko@azonmedia.com
     * @created 28.12.2017
     * @since 0.7.1
     */
    private function execute_pre_callbacks(): void
    {
        if ($this->callback_container) {
            $this->callback_container($this, callbackContainer::MODE_BEFORE);
        }
    }

    /**
     * Executes the MODE_AFTER event on the current transaction.
     * It executes on transaction end no matter the status.
     * This is the very first callback that gets executed.
     * @return void
     * @author vesko@azonmedia.com
     * @created 28.12.2017
     * @since 0.7.1
     */
    private function execute_after_callbacks(): void
    {
        if ($this->callback_container) {
            $this->callback_container($this, callbackContainer::MODE_AFTER);
        }
    }

    private function execute_pre_rollback_callbacks(): void
    {
        $this->is_in_rollback_callback_flag = true;
        if ($this->callback_container) {
            $this->callback_container($this, callbackContainer::MODE_BEFORE_ROLLBACK);
        }
        $this->is_in_rollback_callback_flag = false;
    }

    private function execute_after_rollback_callbacks(): void
    {
        $this->is_in_rollback_callback_flag = true;
        if ($this->callback_container) {
            $this->callback_container($this, callbackContainer::MODE_AFTER_ROLLBACK);
        }
        $this->is_in_rollback_callback_flag = false;
    }

    private function execute_pre_master_rollback_callbacks(): void
    {
        foreach ($this->get_nested() as $transaction) {
            $transaction->execute_pre_master_rollback_callbacks();
        }
        $this->is_in_rollback_callback_flag = true;
        if ($this->callback_container) {
            $this->callback_container($this, callbackContainer::MODE_BEFORE_MASTER_ROLLBACK);
        }
        $this->is_in_rollback_callback_flag = false;
    }

    private function execute_after_master_rollback_callbacks(): void
    {
        foreach ($this->get_nested() as $transaction) {
            $transaction->execute_after_master_rollback_callbacks();
        }
        $this->is_in_rollback_callback_flag = true;
        if ($this->callback_container) {
            $this->callback_container($this, callbackContainer::MODE_AFTER_MASTER_ROLLBACK);
        }
        $this->is_in_rollback_callback_flag = false;
    }

    private function execute_pre_save_callbacks(): void
    {
        $this->is_in_save_callback_flag = true;
        if ($this->callback_container) {
            $this->callback_container($this, callbackContainer::MODE_BEFORE_SAVE);
        }
        $this->is_in_save_callback_flag = false;
    }

    private function execute_after_save_callbacks(): void
    {
        $this->is_in_save_callback_flag = true;
        if ($this->callback_container) {
            $this->callback_container($this, callbackContainer::MODE_AFTER_SAVE);
        }
        $this->is_in_save_callback_flag = false;
    }

    private function execute_pre_commit_callbacks(): void
    {
        $this->is_in_commit_callback_flag = true;
        if ($this->callback_container) {
            $this->callback_container($this, callbackContainer::MODE_BEFORE_COMMIT);
        }
        $this->is_in_commit_callback_flag = false;
    }

    private function execute_after_commit_callbacks(): void
    {
        $this->is_in_commit_callback_flag = true;
        if ($this->callback_container) {
            $this->callback_container($this, callbackContainer::MODE_AFTER_COMMIT);
        }
        $this->is_in_commit_callback_flag = false;
    }

    private function execute_pre_master_commit_callbacks(): void
    {
        foreach ($this->get_nested() as $transaction) {
            $transaction->execute_pre_master_commit_callbacks();
        }
        $this->is_in_commit_callback_flag = true;
        if ($this->callback_container) {
            $this->callback_container($this, callbackContainer::MODE_BEFORE_MASTER_COMMIT);
        }
        $this->is_in_commit_callback_flag = false;
    }

    private function execute_after_master_commit_callbacks(): void
    {
        foreach ($this->get_nested() as $transaction) {
            $transaction->execute_after_master_commit_callbacks();
        }
        $this->is_in_commit_callback_flag = true;
        if ($this->callback_container) {
            $this->callback_container($this, callbackContainer::MODE_AFTER_MASTER_COMMIT);
        }
        $this->is_in_commit_callback_flag = false;
    }

    private function execute_before_master_callbacks(): void
    {
        //first invoke the callbacks on the nested transactions
        foreach ($this->get_nested() as $transaction) {
            $transaction->execute_before_master_callbacks();
        }
        if ($this->callback_container) {
            $this->callback_container($this, callbackContainer::MODE_BEFORE_MASTER);
            $this->callback_container($this, callbackContainer::MODE_BEFORE_MASTER_IN_WORKER);


            $this->is_in_rollback_callback_flag = true;
            if ($this->is_rolled_back()) {
                $this->callback_container($this, callbackContainer::MODE_ON_ROLLBACK_BEFORE_MASTER);
                $this->callback_container($this, callbackContainer::MODE_ON_ROLLBACK_BEFORE_MASTER_IN_WORKER);
            }
            $this->is_in_rollback_callback_flag = false;


            $this->is_in_save_callback_flag = true;
            if ($this->is_saved()) {
                $this->callback_container($this, callbackContainer::MODE_ON_SAVE_BEFORE_MASTER);
                $this->callback_container($this, callbackContainer::MODE_ON_SAVE_BEFORE_MASTER_IN_WORKER);
            }
            $this->is_in_save_callback_flag = false;


            $this->is_in_commit_callback_flag = true;
            //if ($this->is_commited()) { //a nested transaction can not be committed but saved only
            if ($this->is_saved()) {
                $this->callback_container($this, callbackContainer::MODE_ON_COMMIT_BEFORE_MASTER);
                $this->callback_container($this, callbackContainer::MODE_ON_COMMIT_BEFORE_MASTER_IN_WORKER);
            }
        }
        $this->is_in_commit_callback_flag = false;
    }


    private function execute_after_master_callbacks(): void
    {

        //first invoke the callbacks on the nested transactions
        foreach ($this->get_nested() as $transaction) {
            $transaction->execute_after_master_callbacks();
        }

        if ($this->callback_container) {
            $this->is_in_save_callback_flag = true;
            //if ($this->is_saved()) { // this cant happen - at this stage if it was saved it is now committed
            if ($this->is_commited()) {
                $this->callback_container($this, callbackContainer::MODE_ON_SAVE_AFTER_MASTER);
                $this->callback_container($this, callbackContainer::MODE_ON_SAVE_AFTER_MASTER_IN_WORKER);
                $this->callback_container($this, callbackContainer::MODE_ON_SAVE_AFTER_MASTER_AFTER_CONTROLLER);
                $this->callback_container($this, callbackContainer::MODE_ON_SAVE_AFTER_MASTER_IN_SHUTDOWN);
            }
            $this->is_in_save_callback_flag = false;

            $this->is_in_commit_callback_flag = true;
            if ($this->is_commited()) {
                $this->callback_container($this, callbackContainer::MODE_ON_COMMIT_AFTER_MASTER);
                $this->callback_container($this, callbackContainer::MODE_ON_COMMIT_AFTER_MASTER_IN_WORKER);
                $this->callback_container($this, callbackContainer::MODE_ON_COMMIT_AFTER_MASTER_AFTER_CONTROLLER);
                $this->callback_container($this, callbackContainer::MODE_ON_COMMIT_AFTER_MASTER_IN_SHUTDOWN);
            }
            $this->is_in_commit_callback_flag = false;

            $this->is_in_rollback_callback_flag = true;
            if ($this->is_rolled_back()) {
                $this->callback_container($this, callbackContainer::MODE_ON_ROLLBACK_AFTER_MASTER);
                $this->callback_container($this, callbackContainer::MODE_ON_ROLLBACK_AFTER_MASTER_IN_WORKER);
                $this->callback_container($this, callbackContainer::MODE_ON_ROLLBACK_AFTER_MASTER_AFTER_CONTROLLER);
                $this->callback_container($this, callbackContainer::MODE_ON_ROLLBACK_AFTER_MASTER_IN_SHUTDOWN);
            }
            $this->is_in_rollback_callback_flag = false;

            $this->callback_container($this, callbackContainer::MODE_AFTER_MASTER);
            $this->callback_container($this, callbackContainer::MODE_AFTER_MASTER_IN_WORKER);
            $this->callback_container($this, callbackContainer::MODE_AFTER_MASTER_AFTER_CONTROLLER);
            $this->callback_container($this, callbackContainer::MODE_AFTER_MASTER_IN_SHUTDOWN);
        }
    }

    /**
     *
     * @param int $status
     * @return void
     * @throws TransactionException
     */
    private function set_status(int $status): void
    {
        if ($status == $this->get_status()) {
            return;//do nothing...
            //it may happen a transaction to be set twice to rolled back
        }

        if ($this->get_status() == self::STATUS_COMMITTED && $this->get_status() == self::STATUS_ROLLED_BACK) {
            throw new TransactionException($this, sprintf(t::_('The status of a committed or rolled back transaction can not be changed. The transaction "%s" of type "%s" has status "%s".'), $this->get_object_internal_id(), get_class($this), $this->get_status_as_string()));
        }
        if ($status == self::STATUS_COMMITTED) {
            if ($this->get_status() != self::STATUS_SAVED && $this->get_status() != self::STATUS_STARTED) {
                throw new TransactionException($this, sprintf(t::_('The transaction status can not be set to committed if its current statis is not started or saved. The transaction "%s" is of type "%s" has status "%s".'), $this->get_object_internal_id(), get_class($this), $this->get_status_as_string()));
            }
        }

        $this->status = $status;
        if (method_exists($this, '_execute_set_status')) {
            $this->execute_set_status($status);//some transactions may need this... like the distributedTransaction - it needs to update the status to its own transactions
        }

        if ($status == self::STATUS_COMMITTED) {
            //we need to update the status on all nested transactions
            foreach ($this->get_nested() as $transaction) {
                if ($transaction->get_status() == self::STATUS_SAVED) {
                    $transaction->set_status($status);
                }
            }
        }
    }

    /**
     * Returns the exisitng callback or if there is no such creates and sets a new one
     * @return callbackContainer
     * @throws InvalidArgumentException
     * @throws RunTimeException
     */
    public function get_or_set_rollback_callback()
    {
        return $this->getCallbackContainer();
    }
}
