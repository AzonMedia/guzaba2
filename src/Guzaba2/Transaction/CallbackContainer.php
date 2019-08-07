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
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Base\TraceInfoObject;
use Guzaba2\Helper\ControllerHelper;
use Guzaba2\Kernel\ExecutionContext;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Patterns\Callback;
use Guzaba2\Translator\Translator as t;

/**
 * A container class for callbacks.
 * When executed will execute all callbacks in the order they were pushed
 */
class CallbackContainer extends \Guzaba2\Patterns\CallbackContainer
{
    const MODE_BEFORE_COMMIT = 10;//this means really COMMIT not just save
    const MODE_AFTER_COMMIT = 20;//this means really COMMIT not just save (and it means after master as it cant be committed before that)
    const MODE_BEFORE_MASTER_COMMIT = 30;//pointless - check is it working
    const MODE_AFTER_MASTER_COMMIT = 40;//pointless - check is it working


    const MODE_BEFORE_ROLLBACK = 110;
    const MODE_AFTER_ROLLBACK = 120;
    const MODE_BEFORE_MASTER_ROLLBACK = 130;
    const MODE_AFTER_MASTER_ROLLBACK = 140;//on master transaction rollback (this means it was rolled back in the DB)

    const MODE_BEFORE = 210;//new//before the current transaction is committed, saved or rolled back, no matter what
    const MODE_AFTER = 220;//new//after the current transaction no matter how it completes - SAVE is also considered after
    const MODE_BEFORE_SAVE = 230;//new
    const MODE_AFTER_SAVE = 240;//new
    //
    //there is no master SAVE - it is either commit or rollback

    //these will be executed AFTER the master transaction is over - either commited or rolled back
    //the following codes are conditional ones - the execution will take place at the end of the master transaction but only if the current transaction has hit one of the following conditions
    //but then BEFORE & AFTER are pointless because the execution of the callback will take place only AFTER the master one is finished
    //it will matter only if the current transaction IS the master one
    //and this may be misleading and it should be avoided (we must not count of the fact is our current transaction the master one or NOT)
    //const MODE_BEFORE_ROLLBACK_EXECUTE_AFTER_MASTER = 210;
    //const MODE_AFTER_ROLLBACK_EXECUTE_AFTER_MASTER = 220;
    //const MODE_BEFORE_COMMIT_EXECUTE_AFTER_MASTER = 230;//perhaps will never be used
    //const MODE_AFTER_COMMIT_EXECUTE_AFTER_MASTER = 240;//perhaps will never be used

    const MODE_BEFORE_MASTER = 310;//new
    const MODE_AFTER_MASTER = 320;//execute the callback after the master transaction is over - no matter was it rolled back or commited
    //the status of the current transaction is irrelevant - it can be checked insite the callback - the transaction is provided as an argument and the status can be retreived with $transaction->get_status()
    const MODE_ON_ROLLBACK_BEFORE_MASTER = 330;//useless
    const MODE_ON_ROLLBACK_AFTER_MASTER = 340;//this is very useful - executes if the inner transaction is rolled back and on master completion (no matter how)
    const MODE_ON_COMMIT_BEFORE_MASTER = 350;//useless
    const MODE_ON_COMMIT_AFTER_MASTER = 360;//this is pointless - what would be the point of a calblack that is executed on a savepoint (commit of an inner transaction) after master rollback
    //no - the above makes sense - it will be invoked only on STATUS_COMMITED and not on STATUS_SAVED... this implies that the MASTER transaction was commited (in fact is AFTER_MASTER_COMMIT)
    const MODE_ON_SAVE_BEFORE_MASTER = 370;//useless
    const MODE_ON_SAVE_AFTER_MASTER = 380;//useless

    //no point here adding MODE_BEFORE_MASTER_IN_WORKER - why would that be needed?!

    const MODE_BEFORE_MASTER_IN_WORKER = 410;//useless
    const MODE_AFTER_MASTER_IN_WORKER = 420;
    const MODE_ON_ROLLBACK_BEFORE_MASTER_IN_WORKER = 430;//useless
    const MODE_ON_ROLLBACK_AFTER_MASTER_IN_WORKER = 440;
    const MODE_ON_COMMIT_BEFORE_MASTER_IN_WORKER = 450;//useless
    const MODE_ON_COMMIT_AFTER_MASTER_IN_WORKER = 460;
    const MODE_ON_SAVE_BEFORE_MASTER_IN_WORKER = 470;//useless
    const MODE_ON_SAVE_AFTER_MASTER_IN_WORKER = 480;//useless

    //it cant be both before master and after controller
    //const MODE_BEFORE_MASTER_AFTER_CONTROLLER = 510;//useless
    const MODE_AFTER_MASTER_AFTER_CONTROLLER = 520;
    //const MODE_ON_ROLLBACK_BEFORE_MASTER_AFTER_CONTROLLER = 530;//useless
    const MODE_ON_ROLLBACK_AFTER_MASTER_AFTER_CONTROLLER = 540;
    //const MODE_ON_COMMIT_BEFORE_MASTER_AFTER_CONTROLLER = 550;//useless
    const MODE_ON_COMMIT_AFTER_MASTER_AFTER_CONTROLLER = 560;
    //const MODE_ON_SAVE_BEFORE_MASTER_AFTER_CONTROLLER = 570;//useless
    const MODE_ON_SAVE_AFTER_MASTER_AFTER_CONTROLLER = 580;//useless


    //const MODE_BEFORE_MASTER_IN_SHUTDOWN = 610;//useless
    const MODE_AFTER_MASTER_IN_SHUTDOWN = 620;
    //const MODE_ON_ROLLBACK_BEFORE_MASTER_IN_SHUTDOWN = 630;//useless
    const MODE_ON_ROLLBACK_AFTER_MASTER_IN_SHUTDOWN = 640;
    //const MODE_ON_COMMIT_BEFORE_MASTER_IN_SHUTDOWN = 650;//useless
    const MODE_ON_COMMIT_AFTER_MASTER_IN_SHUTDOWN = 660;
    //const MODE_ON_SAVE_BEFORE_MASTER_IN_SHUTDOWN = 670;//useless
    const MODE_ON_SAVE_AFTER_MASTER_IN_SHUTDOWN = 680;//useless

    //NI
    const MODE_AFTER_MASTER_CHAIN = 710;//this issues the master COMMIT or ROLLBACK as COMMIT CHAIN and ROLLBACK CHAIN. This also keeps all locks obtained during the master transaction.
    const MODE_ON_ROLLBACK_AFTER_MASTER_CHAIN = 720;//ROLLBACK CHAIN
    const MODE_ON_COMMIT_AFTER_MASTER_CHAIN = 730;//COMMIT CHAIN
    //using any of the above will actually modify the master transaction COMMIT or ROLLBACK statement
    //this also encompasses the whole block in a transaction automatically (no need for an explicit one within the block - it will be just a nested one)

    //NI
    const MODE_AFTER_MASTER_AFTER_CONTROLLER_CHAIN = 810;
    const MODE_ON_ROLLBACK_AFTER_MASTER_AFTER_CONTROLLER_CHAIN = 820;
    const MODE_ON_COMMIT_AFTER_MASTER_AFTER_CONTROLLER_CHAIN = 830;

    //NI
    const MODE_AFTER_MASTER_IN_SHUTDOWN_CHAIN = 910;
    const MODE_ON_ROLLBACK_AFTER_MASTER_IN_SHUTDOWN_CHAIN = 920;
    const MODE_ON_COMMIT_AFTER_MASTER_IN_SHUTDOWN_CHAIN = 930;

    //the status shows the status of the transaction to which this event/mode is related
    public const MODES_MAP = [
        self::MODE_BEFORE_COMMIT => ['name' => 'before commit', 'status' => Transaction::STATUS_COMMITTED],
        self::MODE_AFTER_COMMIT => ['name' => 'after commit', 'status' => Transaction::STATUS_COMMITTED],
        self::MODE_BEFORE_MASTER_COMMIT => ['name' => 'before master commit', 'status' => Transaction::STATUS_COMMITTED],
        self::MODE_AFTER_MASTER_COMMIT => ['name' => 'after master commit', 'status' => Transaction::STATUS_COMMITTED],

        self::MODE_BEFORE_ROLLBACK => ['name' => 'before rollback', 'status' => Transaction::STATUS_ROLLED_BACK],
        self::MODE_AFTER_ROLLBACK => ['name' => 'after rollback', 'status' => Transaction::STATUS_ROLLED_BACK],
        self::MODE_BEFORE_MASTER_ROLLBACK => ['name' => 'before master rollback', 'status' => Transaction::STATUS_ROLLED_BACK],
        self::MODE_AFTER_MASTER_ROLLBACK => ['name' => 'after master rollback', 'status' => Transaction::STATUS_ROLLED_BACK],

        self::MODE_AFTER => ['name' => 'after', 'status' => Transaction::STATUS_ANY],
        self::MODE_BEFORE => ['name' => 'before', 'status' => Transaction::STATUS_ANY],
        self::MODE_BEFORE_SAVE => ['name' => 'before save', 'status' => Transaction::STATUS_SAVED],
        self::MODE_AFTER_SAVE => ['name' => 'after save', 'status' => Transaction::STATUS_SAVED],

        self::MODE_BEFORE_MASTER => ['name' => 'before master', 'status' => Transaction::STATUS_ANY],
        self::MODE_AFTER_MASTER => ['name' => 'after master', 'status' => Transaction::STATUS_ANY],
        self::MODE_ON_ROLLBACK_BEFORE_MASTER => ['name' => 'on rollback before master', 'status' => Transaction::STATUS_ROLLED_BACK],
        self::MODE_ON_ROLLBACK_AFTER_MASTER => ['name' => 'on rollback after master', 'status' => Transaction::STATUS_ROLLED_BACK],
        self::MODE_ON_COMMIT_BEFORE_MASTER => ['name' => 'on commit before master', 'status' => Transaction::STATUS_COMMITTED],
        self::MODE_ON_COMMIT_AFTER_MASTER => ['name' => 'on commit after master', 'status' => Transaction::STATUS_COMMITTED],
        self::MODE_ON_SAVE_BEFORE_MASTER => ['name' => 'on save before master', 'status' => Transaction::STATUS_SAVED],
        self::MODE_ON_SAVE_AFTER_MASTER => ['name' => 'on save after master', 'status' => Transaction::STATUS_SAVED],

        self::MODE_BEFORE_MASTER_IN_WORKER => ['name' => 'before master in worker', 'status' => Transaction::STATUS_ANY],
        self::MODE_AFTER_MASTER_IN_WORKER => ['name' => 'after master in worker', 'status' => Transaction::STATUS_ANY],
        self::MODE_ON_ROLLBACK_BEFORE_MASTER_IN_WORKER => ['name' => 'on rollback before master in worker', 'status' => Transaction::STATUS_ROLLED_BACK],
        self::MODE_ON_ROLLBACK_AFTER_MASTER_IN_WORKER => ['name' => 'on rollback after master in worker', 'status' => Transaction::STATUS_ROLLED_BACK],
        self::MODE_ON_COMMIT_BEFORE_MASTER_IN_WORKER => ['name' => 'on commit before master in worker', 'status' => Transaction::STATUS_COMMITTED],
        self::MODE_ON_COMMIT_AFTER_MASTER_IN_WORKER => ['name' => 'on commit after master in worker', 'status' => Transaction::STATUS_COMMITTED],
        self::MODE_ON_SAVE_BEFORE_MASTER_IN_WORKER => ['name' => 'on save before master in worker', 'status' => Transaction::STATUS_SAVED],
        self::MODE_ON_SAVE_AFTER_MASTER_IN_WORKER => ['name' => 'on save after master in worker', 'status' => Transaction::STATUS_SAVED],

        //self::MODE_BEFORE_MASTER_AFTER_CONTROLLER => array('name' => 'before master after controller'),
        self::MODE_AFTER_MASTER_AFTER_CONTROLLER => ['name' => 'after master after controller', 'status' => Transaction::STATUS_ANY],
        //self::MODE_ON_ROLLBACK_BEFORE_MASTER_AFTER_CONTROLLER => array('name' => 'on rollback before master after controller'),
        self::MODE_ON_ROLLBACK_AFTER_MASTER_AFTER_CONTROLLER => ['name' => 'on rollback after master after controller', 'status' => Transaction::STATUS_ROLLED_BACK],
        //self::MODE_ON_COMMIT_BEFORE_MASTER_AFTER_CONTROLLER => array('name' => 'on commit before master after controller'),
        self::MODE_ON_COMMIT_AFTER_MASTER_AFTER_CONTROLLER => ['name' => 'on commit after master after controller', 'status' => Transaction::STATUS_COMMITTED],
        //self::MODE_ON_SAVE_BEFORE_MASTER_AFTER_CONTROLLER => array('name' => 'on save before master after controller'),
        self::MODE_ON_SAVE_AFTER_MASTER_AFTER_CONTROLLER => ['name' => 'on save after master after controller', 'status' => Transaction::STATUS_SAVED],

        //self::MODE_BEFORE_MASTER_IN_SHUTDOWN => array('name' => 'before master in shutdown'),
        self::MODE_AFTER_MASTER_IN_SHUTDOWN => ['name' => 'after master in shutdown', 'status' => Transaction::STATUS_ANY],
        //self::MODE_ON_ROLLBACK_BEFORE_MASTER_IN_SHUTDOWN => array('on rollback before master in shutdown'),
        self::MODE_ON_ROLLBACK_AFTER_MASTER_IN_SHUTDOWN => ['name' => 'on rollback after master in shutdown', 'status' => Transaction::STATUS_ROLLED_BACK],
        //self::MODE_ON_COMMIT_BEFORE_MASTER_IN_SHUTDOWN => array('name' => 'on commit before master in shutdown'),
        self::MODE_ON_COMMIT_AFTER_MASTER_IN_SHUTDOWN => ['name' => 'on commit after master in shutdown', 'status' => Transaction::STATUS_COMMITTED],
        //self::MODE_ON_SAVE_BEFORE_MASTER_IN_SHUTDOWN => array('name' => 'on save before master in shutdown'),
        self::MODE_ON_SAVE_AFTER_MASTER_IN_SHUTDOWN => ['name' => 'on save after master in shutdown', 'status' => Transaction::STATUS_SAVED],

    ];

    const DEFAULT_ROLLBACK_MODE = self::MODE_ON_ROLLBACK_AFTER_MASTER;

    const DEFAULT_COMMIT_MODE = self::MODE_ON_COMMIT_AFTER_MASTER;

    protected $transaction;

    /**
     * Two dimensional array - first dimention is the mode and the second
     * @var array
     */
    protected $callables = [];

    /**
     * Twodimensional array of modes and callable hashes
     * It is used to prevent adding the same callable for the same mode
     * @var array
     */
    protected $callable_hashes = [];

    /**
     * CallbackContainer constructor.
     * @param array $callables
     * @param int $mode
     * @param transaction|null $transaction
     * @throws InvalidArgumentException
     * @throws RunTimeException
     */
    public function __construct(array $callables = [], int $mode = 0, ?transaction &$transaction = NULL)
    {
        if ($callables) {
            $this->validate_mode($mode);
        }

        $this->transaction =& $transaction;

        parent::__construct([]);//call the parent constructor with an empty array - we will load the callables after that

        foreach ($callables as $callable) {
            $this->add_callable($callable, $mode);
        }
    }

    public function __destruct()
    {
        $this->destroy();
    }

    public function destroy()
    {
        $this->callables = [];
        //no need to remove the reference to the transaction
    }

    /**
     * To be used when the callbackContainer was instantiated before the transaction.
     * For example when used in TXM::executeInObjectTransaction - if we want to have callbacks there we need to provide an existing preconfigured container.
     * @param Transaction $transaction
     * @throws RunTimeException
     * @throws InvalidArgumentException
     */
    public function set_transaction(Transaction $transaction): void
    {

        //needs to check all callbacks already added to this transaction are they in appropriate state
        $callables = $this->get_callables();//without providing $mode will return all callables
        foreach ($callables as $mode => $callable) {
            $this->validate_callable_mode($mode);
        }

        $this->transaction = $transaction;
    }

    public function get_transaction(): ?Transaction
    {
        return $this->transaction;
    }


    /**
     * Adds a callable for a specific transaction condition/mode.
     * The executionContext object is not returned by reference because this will allow it to be replaced with another object
     * Returns the added callable
     * @param callable $callable
     * @param int $mode When the callable should be executed
     * @param bool $preserve_context Should the current execution context be preserved
     * @param bool $only_once If set to true the callback will be not be added again if it is already added for the specified $mode
     * @return ExecutionContext | callable | NULL Retruns NULL if the callable wasnt added because it has already been added and $only_once was set to TRUE
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @see self::MODES_MAP for the valid modes
     */
    public function add_callable($callable, ?int $mode = NULL, bool $preserve_context = TRUE, bool $only_once = FALSE): ?Callback
    {

        //NULL is not really supported - it is put there only to make the signature compatible
        if ($mode === NULL) {
            throw new InvalidArgumentException(sprintf(t::_('%s expects the second argument to be an INT.'), __METHOD__));
        }
        $this->validate_mode($mode);
        $this->validate_callable_mode($mode);

        //$this->callables[$mode][] = array(&$callable, &$current_transaction);//having the & may have the reference pointing to NULL - detroyed transaction
        //the above is wrong because the transaction is removed from the internal pointer pdo::current_transaction and the reference to the transaction in the array will become NULL
        //we hsould also hold the transaction until the callback is executed

        $callable_hash = ControllerHelper::get_callable_hash($callable);

        //we must preserve the context of the callables
        if ($callable instanceof Callback) {
            //do nothing - it already has the context preserved (if it was chosen to be at the time of the creation of the callback)
        } else {
            $callable = new Callback($callable, $preserve_context);
        }

        if ($only_once && array_key_exists($mode, $this->callable_hashes) && in_array($callable_hash, $this->callable_hashes[$mode])) {
            //this callable has been already added for this mode
            return NULL;
        }

        $trace_exception = new TraceInfoObject(sprintf(t::_('WHERE THE CALLBACK WAS CREATED AND QUEUED.')));//this is not a real exception but is creted only for the purpose if a real one is thrown - this to be thrown a s a previous one

        $this->callables[$mode][] = [&$callable, $this->transaction, FALSE, $trace_exception];//the third argument is is the callback executed or not - when executed this is set to TRUE - this is done so we avoid double execution in case of error

        $this->callable_hashes[$mode][] = $callable_hash;

        return $callable;
    }

    /**
     * Alias of @param string $callable
     * @param int $mode
     * @param bool $preserve_context Should the current execution context be preserved
     * @param bool $only_once
     * @return Callback|null
     * @see add_callable()
     */
    public function add($callable, ?int $mode = NULL, bool $preserve_context = TRUE, bool $only_once = FALSE): ?Callback
    {
        try {
            return $this->add_callable($callable, $mode, $preserve_context, $only_once);
        } catch (InvalidArgumentException $e) {
        } catch (RunTimeException $e) {
        }
        // TODO return something
    }

    /**
     * Returns an array of callables for the provided $mode.
     * If no mode is provided it will return a twodimensional array with first index being modes and second index the callables
     * @param int $mode
     * @return array Array of callables if mode is provided or a twodimensional array if no mode is provided
     * @throws InvalidArgumentException
     * @see self::MODES_MAP
     */
    public function get_callables(int $mode = 0): array
    {
        if ($mode) {
            $this->validate_mode($mode);
            $ret = $this->callables[$mode] ?? [];
        } else {
            $ret = $this->callables;
        }
        return $ret;
    }

    public static function get_mode_name($mode)
    {
        return self::MODES_MAP[$mode]['name'];
    }


    //public function __invoke($transaction, $mode) {
    //we need the signatures to be compatible with the parent class
    //can not return the result of the execution as the execution/mode may be a delayed one
    /**
     * @param transaction|null $transaction
     * @param int|null $mode
     * @return bool
     * @throws \Throwable
     * @throws InvalidArgumentException
     */
    public function __invoke(?transaction $transaction = NULL, ?int $mode = NULL): bool
    {
        if (!($transaction instanceof transaction)) {
            throw new InvalidArgumentException(sprintf(t::_('%s::%s expects the first argument to be a %s.'), __CLASS__, __METHOD__, Transaction::class));
        }
        if (!is_int($mode)) {
            throw new InvalidArgumentException(sprintf(t::_('%s::%s expects the first argument to be an int containing the mode which triggers the callback.'), __CLASS__, __METHOD__));
        }

        $this->validate_mode($mode);//this will throw an exception if the provided mode is not valid

        if (isset($this->callables[$mode])) {
            foreach ($this->callables[$mode] as &$callable_data) {
                if ($callable_data) { //it may be null if the object got destroyed in the mean time (this is a way to remove a callable from the array)
                    //list ($callback, $callback_transaction, $callback_executed) = $callable_data;
                    //in php 7.3 we could have done
                    //@see https://wiki.php.net/rfc/list_reference_assignment
                    //[$callback, $callback_transaction, &$callback_executed = $callable_data;
                    $callback = $callable_data[0];
                    $callback_transaction = $callable_data[1];
                    $callback_executed =& $callable_data[2];//by reference because we will raise this flag when the callback is executed
                    $trace_info = $callable_data[3];
                    if (!$transaction) { //just in case the transaction wasnt provided
                        $transaction = $callback_transaction;
                    }
                    if ($callback_executed) {
                        continue;
                    }
                    if (in_array($mode, [self::MODE_AFTER_MASTER_IN_WORKER, self::MODE_ON_ROLLBACK_AFTER_MASTER_IN_WORKER, self::MODE_ON_COMMIT_AFTER_MASTER_IN_WORKER])) {
                        //Kernel::execute_in_worker($callback, $transaction, $mode);
                        Kernel::execute_in_worker($callback);//currently passing arguments is not supported
                    } elseif (in_array($mode, [self::MODE_AFTER_MASTER_AFTER_CONTROLLER, self::MODE_ON_ROLLBACK_AFTER_MASTER_AFTER_CONTROLLER, self::MODE_ON_COMMIT_AFTER_MASTER_AFTER_CONTROLLER])) {
                        Kernel::execute_delayed($callback, $trace_info, $transaction, $mode);
                    } elseif (in_array($mode, [self::MODE_AFTER_MASTER_IN_SHUTDOWN, self::MODE_ON_ROLLBACK_AFTER_MASTER_IN_SHUTDOWN, self::MODE_ON_COMMIT_AFTER_MASTER_IN_SHUTDOWN])) {
                        Kernel::execute_in_shutdown($callback, $trace_info, $transaction, $mode);
                    } else {
                        try {
                            call_user_func($callback, $transaction, $mode);
                        } catch (\Throwable $exception) {
                            BaseException::prependAsFirstExceptionStatic($exception, $trace_info->getAsException());
                            throw $exception;
                        }
                    }
                    $callback_executed = TRUE;
                }
            }
            unset($callable_data);
        }

        return TRUE;
    }

    /**
     * Validates does the provide $mode is a valid mode
     * @param int $mode
     * @throws InvalidArgumentException If an unsupported $mode is passed (@see self::MODES_MAP
     * @see self::MODES_MAP)
     */
    private function validate_mode(int $mode): void
    {
        if (!$mode) {
            throw new InvalidArgumentException(sprintf(t::_('No callback mode was provided to the callbackContainer.'), $mode));
        }
        if (!isset(self::MODES_MAP[$mode])) {
            throw new InvalidArgumentException(sprintf(t::_('An unsupported callback mode "%s" was provided to the callbackContainer.'), $mode));
        }
    }

    /**
     * Validates the targeted status of the callable against the transactions current status.
     * This is done to ensure that no callable is added to a transaction that is in a status that this callable is impossible to be called.
     *
     * @param int $mode
     * @throws RunTimeException If a callback is being
     */
    private function validate_callable_mode(int $mode): void
    {
        $transaction = $this->get_transaction();
        if ($transaction) { //the container may not yet be attached to any transaction

            $mode_related_to_master = FALSE;
            if (!empty(self::MODES_MAP[$mode]['status'] == Transaction::STATUS_ANY)) {
                //we need to validate the mode on the master transaction
                //it doesnt matter the mode on the current transaction
                //$transaction = $Transaction::get_master_transaction($transaction);
                $transaction = $transaction->get_master_transaction();
                $mode_related_to_master = TRUE;
            } else {
                //we validate the current transaction
            }

            //check is the transaction in a state that allows this type of callback to be added
            $callback_transaction_status = self::MODES_MAP[$mode]['status'];
            $callback_mode_name = self::MODES_MAP[$mode]['name'];
            $callback_transaction_status_name = Transaction::$status_map[$callback_transaction_status];

            $transaction_status = $transaction->get_status();

            $transaction_status_name = Transaction::$status_map[$transaction_status];

            //if the transaction is already in the status of the callback then this is pointless
            if ($callback_transaction_status == $transaction_status) {
                if ($mode_related_to_master) {
                    $message = sprintf(t::_('Trying to add a callback for mode "%s" which triggers on status "%s" to a transaction which has its master transaction already in status "%s".'), $callback_mode_name, $callback_transaction_status_name, $transaction_status_name);
                } else {
                    $message = sprintf(t::_('Trying to add a callback for mode "%s" which triggers on status "%s" to a transaction that is already in status "%s".'), $callback_mode_name, $callback_transaction_status_name, $transaction_status_name);
                }
                throw new RunTimeException($message);
            }


            //if they are in different modes lets see is the transaction status set to a status that cant change
            //like a committed transaction cant become rolled back
            if (count($transaction::STATUS_TRANSITIONS[$transaction_status])) {
                //the current transaction status allows transitions
                //lets check is the provided callback targeted status a possible status for transition
                foreach ($transaction::STATUS_TRANSITIONS[$transaction_status] as $possible_transition_status) {
                    if ($possible_transition_status == $callback_transaction_status) {
                        //this is OK
                        $callback_transaction_status_is_OK = TRUE;
                    }
                }
                if (empty($callback_transaction_status_is_OK)) {
                    if ($mode_related_to_master) {
                        $message = sprintf(t::_('Trying to add a callback for mode "%s" which triggers on status "%s" to a transaction that has its master transaction already in status "%s" which does not allow a transition to the status expected by the callback. For the possible transitions please see framework\transactions\classes\transaction::STATUS_TRANSITIONS.'), $callback_mode_name, $callback_transaction_status_name, $transaction_status_name);
                    } else {
                        $message = sprintf(t::_('Trying to add a callback for mode "%s" which triggers on status "%s" to a transaction that is already in status "%s" which does not allow a transition to the status expected by the callback. For the possible transitions please see framework\transactions\classes\transaction::STATUS_TRANSITIONS.'), $callback_mode_name, $callback_transaction_status_name, $transaction_status_name);
                    }
                    throw new RunTimeException($message);
                }
            } else {
                if ($mode_related_to_master) {
                    $message = sprintf(t::_('Trying to add a callback for mode "%s" which triggers on status "%s" to a transaction that has its master transaction already in status "%s" which does not allow a transition to any other status (including the status of the callback). For the possible transitions please see framework\transactions\classes\transaction::STATUS_TRANSITIONS.'), $callback_mode_name, $callback_transaction_status_name, $transaction_status_name);
                } else {
                    $message = sprintf(t::_('Trying to add a callback for mode "%s" which triggers on status "%s" to a transaction that is already in status "%s" which does not allow a transition to any other status (including the status of the callback). For the possible transitions please see framework\transactions\classes\transaction::STATUS_TRANSITIONS.'), $callback_mode_name, $callback_transaction_status_name, $transaction_status_name);
                }
                throw new RunTimeException($message);
            }
        } else {
            //do nothing - there is no current transaction
        }
    }
}
