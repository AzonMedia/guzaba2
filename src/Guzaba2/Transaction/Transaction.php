<?php

declare(strict_types=1);

namespace Guzaba2\Transaction;

use Azonmedia\Utilities\StackTraceUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\BaseException;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Exceptions\ContextDestroyedException;
use Guzaba2\Event\Event;
use Guzaba2\Event\Events;
use Guzaba2\Resources\Interfaces\ResourceInterface;
use Guzaba2\Transaction\Interfaces\TransactionalResourceInterface;
use Guzaba2\Transaction\Interfaces\TransactionInterface;
use Guzaba2\Translator\Translator as t;
use ReflectionException;
use Throwable;

//TODO - replace the callbacks and containers with Events - leave a method on the transaction -> add_callback() but this method will use the events
//add DebugTransaction that will register for the events if debug is enabled and print

/**
 * Class MemoryTransaction
 * @package Guzaba2\MemoryTransaction
 *
 * The Transaction contains a doubly linked tree - all child transactions have a reference to their parent and the parent contains references to its children.
 *
 * While the API supports setting names for the savepoints thus having multiple savepoints for a single transaction in fact as per the implementation
 * (scope based savepoints created when a new scope/nested transaction is started) there is only a single savepoint needed.
 * When the next nested transaction is started it reuses the last savepoint (the previous nested transaction is always in status SAVED or ROLLEDBACK)
 * Because of this the $savepoint parameter on the savepoint manipulation methods has a default value ("SAVEPOINT")
 */
abstract class Transaction extends Base implements TransactionInterface /* implements ResourceInterface */
{

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'TransactionManager',
            'Events',
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * @var string|null
     */
    private ?string $rollback_reason = null;//NULL means not rolled back or reason unknown

    /**
     * @var null
     */
    private ?\Exception $InterruptingException = null;

    /**
     * Was this transaction which initiated the rollback. If it was a parent one this should stay FALSE.
     * @var bool
     */
    private bool $rollback_initiator_flag = false;

    /***
     * @var TransactionInterface|null
     */
    private ?self $ParentTransaction = null;

    /**
     * Child transactions
     * @var TransactionInterface[]
     */
    private array $children = [];

    /**
     * MemoryTransaction status
     * @var string
     */
    private string $status = self::STATUS['CREATED'];

    /**
     * Options for the transaction
     * @var array
     */
    private array $options = [];

    /**
     * MemoryTransaction constructor.
     * @param array $options Not used currently
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws ReflectionException
     */
    public function __construct(array $options = [])
    {
        parent::__construct();

        $this->options = $options;

        $this->ParentTransaction = self::get_service('TransactionManager')->get_current_transaction($this->get_resource()->get_resource_id());
        if ($this->ParentTransaction) {
            //validate that the previous nested transaction (sibling to this one) has ended with either SAVED or ROLLEDBACK (it should not be in any other status)
            $sibling_transactions = $this->ParentTransaction->get_children();
            if ($sibling_transactions) { //if there were previous nested transactions - check the status of the last one
                $LastSiblingTransaction = $sibling_transactions[ count($sibling_transactions) - 1];
                if (!in_array($LastSiblingTransaction->get_status(), [self::STATUS['SAVED'], self::STATUS['ROLLEDBACK']], true)) {
                    throw new RunTimeException(sprintf(t::_('The previous nested transaction (sibling of this) of class %1$s is in status %2$s. Before the next nested transaction can be started the previous nested one must be in status %3$s or %4$s.'), get_class($this), $LastSiblingTransaction->get_status(), self::STATUS['SAVED'], self::STATUS['ROLLEDBACK']));
                }
            }
            $this->ParentTransaction->add_child($this);
        }
    }

    /**
     * If the transaction was rolled back because of an exception this will return the exception.
     * @return Throwable|null
     */
    public function get_interrupting_exception(): ?Throwable
    {
        return $this->InterruptingException;
    }

    /**
     * @return string|null
     */
    public function get_rollback_reason(): ?string
    {
        return $this->rollback_reason;
    }

    private function add_child(Transaction $Transaction): void
    {
        if ($Transaction->get_status() !== self::STATUS['CREATED']) {
            throw new InvalidArgumentException(sprintf(t::_('Trying to add a child/nested transaction that is in status %1$s. Only transactions in status %2$s can be added as child/nested.'), $Transaction->get_status(), self::STATUS['CREATED']));
        }
        $this->children[] = $Transaction;
    }

    /**
     * Adds callback on the specified transaction event
     * @param string $event_name
     * @param callable $callback
     * @return bool
     * @throws InvalidArgumentException
     */
    public function add_callback(string $event_name, callable $callback): bool
    {
        self::validate_event($event_name);
        return parent::add_callback($event_name, $callback);
    }

    public static function validate_event(string $event_name): void
    {
        if (!isset(self::EVENT[$event_name])) {
            throw new InvalidArgumentException(sprintf(t::_('Invalid event name %s1 is provided. The %2$s class supports %3$s events.'), $event_name, self::class, implode(', ', self::EVENT)));
        }
    }

    public function has_parent(): bool
    {
        return $this->ParentTransaction ? true : false ;
    }

    public function get_parent(): ?self
    {
        return $this->ParentTransaction;
    }

    public function is_master(): bool
    {
        return !$this->has_parent();
    }

    public function get_master(): self
    {
        $Transaction = $this;
        while ($Transaction->has_parent()) {
            $Transaction = $Transaction->get_parent();
        }
        return $Transaction;
    }

    public function get_nesting(): int
    {
        /** @var int $nesting */
        $nesting = 0;
        /** @var Transaction $Transaction */
        $Transaction = $this;
        while ($Transaction->has_parent()) {
            $Transaction = $Transaction->get_parent();
            $nesting++;
        }
        return $nesting;
    }

    /**
     * Returns the nested transactions
     * @return Transaction[]
     */
    public function get_children(): array
    {
        return $this->children;
    }

    /**
     * Alias of self::get_children()
     * @return array
     */
    public function get_nested(): array
    {
        return $this->get_children();
    }

    protected function get_savepoint_name(): string
    {
        //return 'SP'.$this->get_object_internal_id();
        //the savepoint name reflects the nested level.
        //if on the same level a new transaction is started the same savepoint name will be reused overwriting the previous savepoint
        //having the savepoints named this way allows for a CompositeTransaction::rollback_to_savepoint() to work - the savepoints across all transactions are named the same.
        //there is no need to the savepoint names across the transactions to have different names - the are not using the same TransactionalResource
        return 'SP_' . $this->get_nesting();
    }

    public function begin(): void
    {

        $this->set_status(self::STATUS['STARTED']);

        $this->set_current_transaction($this);

        if ($this->has_parent()) {
            new Event($this, '_before_create_savepoint');
            $savepoint_name = $this->get_savepoint_name();
            $this->get_parent()->create_savepoint($savepoint_name);
            new Event($this, '_after_create_savepoint');
        } else {
            new Event($this, '_before_begin');
            $this->execute_begin();
            new Event($this, '_after_begin');
        }
    }

    protected function set_current_transaction(?Transaction $Transaction): void
    {
        /** @var TransactionManager $TXM */
        $TransactionManager = self::get_service('TransactionManager');
        if ($Transaction) {
            $TransactionManager->set_current_transaction($Transaction);
        } else {
            $TransactionManager->set_current_transaction(null, $this->get_resource()->get_resource_id());
        }
    }

    /**
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws ContextDestroyedException
     * @throws ReflectionException
     */
    final public function rollback(): void
    {

        $initial_status = $this->get_status();
        $allowed_statuses = [ self::STATUS['STARTED'], self::STATUS['SAVED'] ];
        if (!in_array($initial_status, $allowed_statuses, true)) {
            throw new RunTimeException(sprintf(t::_('The transaction of class %1$s is currently in status %1$s and can not be rolled back. Only transactions in statuses %2$s can be rolled back.'), get_class($this), $initial_status, implode(', ', $allowed_statuses)));
        }

        //rollback all children (no matter what is their status - started on saved ... should be saved)
        foreach (array_reverse($this->get_children()) as $ChildTransaction) {
            if ($ChildTransaction->get_status() !== self::STATUS['ROLLEDBACK']) { //do not try to rollback again
                $ChildTransaction->rollback();
            }
        }

        $caller = StackTraceUtil::get_caller();

        //it may happen the scope reference for the transactional resource (db connection) to be destroyed before the scope reference for the transaction
        if ($caller[0] === ScopeReference::class || is_a($caller[0], TransactionalResourceInterface::class, true)) {
            //debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            //print_r($caller);
            $this->rollback_initiator_flag = true;
            $CurrentException = BaseException::getCurrentException();
            if ($CurrentException) {
                $this->rollback_reason = self::ROLLBACK_REASON['EXCEPTION'];
                $this->InterruptingException = $CurrentException;
            } elseif (StackTraceUtil::check_stack_for_scope_ref_destruct_due_throw()) {
                //if an exception not extending BaseException and is thrown the CurrentException will not be set
                //thus the stack needs to be checked - was the destruction of the scope reference (connection one or transaction one) triggered by a throw
                //this is done by checking the first scope reference destructor and the calling line - is there a throw statement
                $this->rollback_reason = self::ROLLBACK_REASON['EXCEPTION'];//reason = exception but there will not be available exception
            } else {
                $this->rollback_reason = self::ROLLBACK_REASON['IMPLICIT'];//return
            }
        } elseif ($caller[0] === Transaction::class && $caller[1] === 'rollback') {
            $this->rollback_reason = self::ROLLBACK_REASON['PARENT'];
            $ParentTransaction = $this->get_parent();
            if (!$ParentTransaction) {
                throw new LogicException(sprintf(t::_('The transaction is rolled back by a parent transaction (see caller) but get_parent() returned no parent transaction.')));
            }
            $this->InterruptingException = $ParentTransaction->get_interrupting_exception();
        } else {
            $this->rollback_initiator_flag = true;
            $this->rollback_reason = self::ROLLBACK_REASON['EXPLICIT'];//rollback() method explicitly invoked
        }

        new Event($this, '_before_rollback');

        if ($this->has_parent()) {
            $savepoint = $this->get_savepoint_name();
            $this->get_parent()->rollback_to_savepoint($savepoint);
            $this->set_current_transaction($this->get_parent());
        } else {
            $this->execute_rollback();
            $this->set_current_transaction(null);
        }
        $this->set_status(self::STATUS['ROLLEDBACK']);

        new Event($this, '_after_rollback');

        //try destroying the tree of transactions after all events are fired...
        if ($this->is_master()) {
            $this->destroy_transactions();
        }
    }

    protected function rollback_to_savepoint(string $savepoint_name): void
    {
        $this->execute_rollback_to_savepoint($savepoint_name);
    }

    protected function release_savepoint(string $savepoint): void
    {
        $this->execute_release_savepoint($savepoint);
    }

    /**
     * Is this the outermost transaction that was rolled back (that initiates the rollback of the child ones)
     * or it is a child transaction that is rolled back because of the parent one.
     * @return bool
     */
    public function is_rollback_initiator(): bool
    {
        return $this->rollback_initiator_flag;
    }

    /**
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function commit(): void
    {
        $initial_status = $this->get_status();
        $allowed_statuses = [ self::STATUS['STARTED'], self::STATUS['SAVED'] ];
        if (!in_array($initial_status, $allowed_statuses, true)) {
            throw new RunTimeException(sprintf(t::_('The transaction is currently in status %1$s and can not be committed. Only transactions in statuses %2$s can be committed.'), $initial_status, implode(', ', $allowed_statuses)));
        }

        if ($this->has_parent()) {
            $this->save();
        } else {
            new Event($this, '_before_commit');
            $this->execute_commit();
            //update the status of the nested transactions to committed only after the master one was committed
            $this->set_status(self::STATUS['COMMITTED']);
            $this->set_current_transaction(null);
            new Event($this, '_after_commit');
        }

        //try destroying the tree of transactions after all events are fired...
        if ($this->is_master()) {
            $this->destroy_transactions();
        }
    }

    /**
     * When the master transaction is in an self::END_STATUSES it must explicitly destroy its child transactions and their references to their parents.
     * As otherwise the transaction object will not be destroyed until the GC is called as the transactions are doubly linked tree which means circular references.
     */
    private function destroy_transactions(): void
    {
        //try to destroy all child transactions
//        foreach ($this->children as $Transaction) {
//            $Transaction->ParentTransaction = NULL;
//            $Transaction = NULL;
//        }
//        $this->children = [];
        $Function = static function ($Transaction) use (&$Function): void {
            foreach ($Transaction->children as $ChildTransaction) {
                $Function($ChildTransaction);
                $ChildTransaction->ParentTransaction = null;//remove a reference
                $ChildTransaction = null;
            }
            $Transaction->children = [];
        };
        $Function($this);
    }

    /**
     * To be called by commit()
     * @throws InvalidArgumentException
     */
    protected function save(): void
    {

        //no need to create savepoint here for the

        new Event($this, '_before_save');
        $this->execute_save();
        $this->set_status(self::STATUS['SAVED']);
        $this->set_current_transaction($this->get_parent());
        new Event($this, '_after_save');
    }

    public function execute(callable $callable) /* mixed */
    {
        if ($this->get_status() !== self::STATUS['CREATED']) {
            throw new RunTimeException(sprintf(t::_('The code can not be executed (%s($callable)) in the given transaction  as the transaction currently is in status %1$s. Only transactions in status %2$s can execute code.'), __METHOD__, $this->get_status(), self::STATUS['CREATED']));
        }
        $this->begin();
        $ret = $callable();
        $this->commit();
        return $ret;
    }

    public function create_savepoint(string $savepoint_name): void
    {
        $this->execute_create_savepoint($savepoint_name);
    }

    public function get_status(): string
    {
        return $this->status;
    }

    public function set_status(string $status): void
    {
        $current_status = $this->get_status();
        if (in_array($current_status, self::END_STATUSES, true)) {
            throw new InvalidArgumentException(sprintf(t::_('The current status of the transaction is %1$s. Transactions in statuses %s2 can not be changed. The provided status is %3$s.'), $current_status, implode(', ', self::END_STATUSES), $status));
        }
        if ($status === self::STATUS['COMMITTED']) {
            $allowed_statuses = [self::STATUS['SAVED'], self::STATUS['STARTED'] ];
            if (!in_array($current_status, $allowed_statuses)) {
                throw new InvalidArgumentException(sprintf(t::_('The provided $status %1$s can not be set as the current status of the transaction %s2 does not allow to transition to the provided status. Only %3$s statuses can transition to the provided status.'), $status, $current_status, implode(', ', $allowed_statuses)));
            }
        }

        if ($status === self::STATUS['COMMITTED']) {
            //we need to update the status on all nested transactions
            foreach ($this->get_nested() as $Transaction) {
                if ($Transaction->get_status() === self::STATUS['SAVED']) {
                    new Event($Transaction, '_before_commit');
                    $Transaction->set_status($status);
                    new Event($Transaction, '_after_commit');
                } else {
                    //child transactions with status ROLLEDBACK (and any others) are left as they are... rolledback transaction can not be committed
                }
            }
        }

        $this->status = $status;
    }

    final public function __destruct()
    {
        new Event($this, '_before_destruct');

        //this is just in ca se here
        //the rollback should be triggered by the ScopeReference
        //the transaction object will be destroyed by the GC as there are cyclic references
        //the transaction tree is doubly linked
        if (in_array($this->get_status(), [self::STATUS['STARTED'], self::STATUS['SAVED']])) {
            $this->rollback();
        }

        parent::__destruct();
    }
    

    abstract protected function execute_begin(): void;

    abstract protected function execute_commit(): void;

    abstract protected function execute_save(): void;

    abstract protected function execute_rollback(): void;

    abstract protected function execute_create_savepoint(string $savepoint): void;

    abstract protected function execute_rollback_to_savepoint(string $savepoint): void;

    abstract protected function execute_release_savepoint(string $savepoint): void;
}
