<?php
declare(strict_types=1);

namespace Guzaba2\Transaction;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Event\Event;
use Guzaba2\Event\Events;
use Guzaba2\Resources\Interfaces\ResourceInterface;
use Guzaba2\Resources\ScopeReference;
use Guzaba2\Swoole\Handlers\Http\Request;
use Guzaba2\Transaction\Interfaces\TransactionalResourceInterface;

//TODO - replace the callbacks and containers with Events - leave a method on the transaction -> add_callback() but this method will use the events
//add DebugTransaction that will register for the events if debug is enabled and print

/**
 * Class Transaction
 * @package Guzaba2\Transaction
 *
 * The Transaction contains a doubly linked tree - all child transactions have a reference to their parent and the parent contains references to its children.
 */
abstract class Transaction extends Base implements ResourceInterface
{

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'TransactionManager',
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    public const STATUS = [
        'CREATED'       => 'CREATED',
        'STARTED'       => 'STARTED',
        'SAVED'         => 'SAVED',
        'ROLLEDBACK'    => 'ROLLEDBACK',
        'COMMITTED'     => 'COMMITTED',
    ];

    public const END_STATUSES = [
        self::STATUS['COMMITTED'],
        self::STATUS['ROLLEDBACK'],
    ];

    public const ROLLBACK_REASON = [
        //'UNKNOWN'       => 'UNKNOWN',
        'EXCEPTION'     => 'EXCEPTION',//the transaction was rolled back because an exception was thrown and the scope reference was destroyed
        'PARENT'        => 'PARENT',//the parent transaction was rolled back while the child one was saved
        'IMPLICIT'      => 'IMPLICIT',//the scope was left (return statement) but there is no exception
        'EXPLICIT'      => 'EXPLICIT',//the transaction was rolled back with a rollback() call
    ];

    /**
     * @var string|null
     */
    private ?string $rollback_reason = NULL;//NULL means not rolled back or reason unknown

    /**
     * Was this transaction which initiated the rollback. If it was a parent one this should stay FALSE.
     * @var bool
     */
    private bool $rollback_initiator_flag = FALSE;

    /***
     * @var Transaction|null
     */
    private ?Transaction $ParentTransaction = NULL;

    /**
     * Child transactions
     * @var Transaction[]
     */
    private array $children = [];

    /**
     * @var CallbackContainer
     */
    private CallbackContainer $CallbackContainer;

    /**
     * Transaction status
     * @var string
     */
    private string $status = self::STATUS['CREATED'];

    private array $options = [];

//    public static function _initialize_class() : void
//    {
//        /** @var Events $Events */
//        $Events = self::get_service('Events');
//        $Events->add_class_callback(Request::class, '_after_handle', [self::dump_debug_info] );
//    }

    /**
     * Transaction constructor.
     * @param array $options Not used currently
     * @throws RunTimeException
     */
    public function __construct(array $options = [])
    {
        parent::__construct();

        $this->options = $options;

        $this->ParentTransaction = self::get_service('TransactionManager')->get_current_transaction($this->get_resource()->get_resource_id());
        if ($this->ParentTransaction) {
            $this->ParentTransaction->add_child($this);
        }

        $this->CallbackContainer = new CallbackContainer($this);

    }

    private function add_child(Transaction $Transaction) : void
    {
        if ($Transaction->get_status() !== self::STATUS['CREATED']) {
            //throw
        }
        $this->children[] = $Transaction;
    }

    public function add_callback(callable $callback, int $mode, bool $once = FALSE) : void
    {
        $this->CallbackContainer->add_callable($callback);
    }

    public function has_parent() : bool
    {
        return $this->ParentTransaction ? TRUE : FALSE ;
    }

    public function get_parent() : ?Transaction
    {
        return $this->ParentTransaction;
    }

    public function is_master() : bool
    {
        return !$this->has_parent();
    }

    public function get_master() : Transaction
    {
        $Transaction = $this;
        while ( $Transaction->has_parent() ) {
            $Transaction = $Transaction->get_parent();
        }
        return $Transaction;
    }

    public function get_nesting() : int
    {
        /** @var int $nesting */
        $nesting = 0;
        /** @var Transaction $Transaction */
        $Transaction = $this;
        while ($Transaction->has_parent() ) {
            $Transaction = $Transaction->get_parent();
            $nesting++;
        }
        return $nesting;
    }

    /**
     * Returns the nested transactions
     * @return array
     */
    public function get_children() : array
    {
        return $this->children;
    }

    /**
     * Alias of self::get_children()
     * @return array
     */
    public function get_nested() : array
    {
        return $this->get_children();
    }

    protected function get_savepoint_name() : string
    {
        return 'SP'.$this->get_object_internal_id();
    }

    public function begin() : void
    {

        $this->set_status(self::STATUS['STARTED']);

        $this->set_current_transaction($this);
        //new Event($this,'_before_any');
        if ($this->has_parent()) {
            new Event($this,'_before_create_savepoint');
            $savepoint_name = $this->get_savepoint_name();
            $this->get_parent()->create_savepoint($savepoint_name);
            new Event($this,'_after_create_savepoint');
        } else {
            new Event($this,'_before_begin');
            $this->execute_begin();
            new Event($this,'_after_begin');
        }
        //new Event($this,'_after_any');
    }

    protected function set_current_transaction(?Transaction $Transaction) : void
    {
        /** @var TransactionManager $TXM */
        $TXM = self::get_service('TransactionManager');
        if ($Transaction) {
            $TXM->set_current_transaction($Transaction);
        } else {
            $TXM->set_current_transaction(NULL, $this->get_resource()->get_resource_id());
        }

    }

    public function rollback() : void
    {
        //print debug_print_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
        //print 'ROLLBACK'.PHP_EOL;
        $initial_status = $this->get_status();
        $allowed_statuses = [ self::STATUS['STARTED'], self::STATUS['SAVED'] ];
        if (!in_array($initial_status, $allowed_statuses, TRUE )) {
            throw new RunTimeException(sprintf(t::_('The transaction is currently in status %1s and can not be rolled back. Only transactions in statuses %2s can be rolled back.'), $initial_status, implode(', ', $allowed_statuses) ));
        }

        //rollback all children (no matter what is their status - started on saved ... should be saved)
        foreach (array_reverse($this->get_children()) as $ChildTransaction) {
            if ($ChildTransaction->get_status() !== self::STATUS['ROLLEDBACK']) { //do not try to rollback again
                $ChildTransaction->rollback();
            }
        }

//        if ($this->has_parent()) {
//            $ParentTransaction = $this->get_parent();
//
//        }

        //$this->execute_before_callbacks();
        //new Event($this, '_before_any');
//        if ($initial_status !== $this->get_status()) {
//            return;//the status has been changed in the callbacks
//        }

        new Event($this, '_before_rollback');

        if ($this->has_parent()) {

            //$this->execute_before_rollback_callbacks();

//            if ($initial_status !== $this->get_status()) {
//                return;//the status has been changed in the callbacks
//            }

            $savepoint = $this->get_savepoint_name();
            $this->get_parent()->rollback_to_savepoint($savepoint);
            $this->set_current_transaction($this->get_parent());

            //$this->execute_after_save_callbacks();
            //new Event($this, '_a')
        } else {

            $this->execute_before_rollback_callbacks();
            if ($initial_status !== $this->get_status()) {
                return;//the status has been changed in the callbacks
            }

            $this->set_status(self::STATUS['ROLLEDBACK']);
            $this->execute_rollback();
            $this->set_current_transaction(NULL);

            //$this->execute_after_rollback_callbacks();
        }
        new Events($this, '_after_rollback');

        //new Events($this, '_after_any');

        //$this->execute_after_callbacks();
    }

    protected function rollback_to_savepoint(string $savepoint_name) : void
    {
        $this->execute_rollback_to_savepoint($savepoint_name);
    }

    public function is_rollback_initiator() : bool
    {
        return $this->rollback_initiator_flag;
    }

    public function commit() : void
    {
        $initial_status = $this->get_status();
        $allowed_statuses = [ self::STATUS['STARTED'], self::STATUS['SAVED'] ];
        if (!in_array($initial_status, $allowed_statuses, TRUE )) {
           throw new RunTimeException(sprintf(t::_('The transaction is currently in status %1s and can not be committed. Only transactions in statuses %2s can be committed.'), $initial_status, implode(', ', $allowed_statuses) ));
        }

//        $this->execute_before_callbacks();
//        if ($initial_status !== $this->get_status()) {
//            return;//the status has been changed in the callbacks
//        }

        //new Event($this, '_before_any');

        if ($this->has_parent()) {
            $this->save();
        } else {

//            $this->execute_before_commit_callbacks();
//            if ($initial_status !== $this->get_status()) {
//                return;//the status has been changed in the callbacks
//            }

            new Event($this, '_before_commit');
            $this->execute_commit();
            $this->set_status(self::STATUS['COMMITTED']);
            $this->set_current_transaction(NULL);
            new Event($this, '_after_commit');

            //$this->execute_after_commit_callbacks();
        }
        //new Event($this, '_after_any');

        //$this->execute_after_callbacks();
    }

    /**
     * To be called by commit()
     * @throws InvalidArgumentException
     * @throws RunTimeException
     */
    protected function save() : void
    {
        $initial_status = $this->get_status();
        if ($initial_status !== self::STATUS['STARTED']) {
            //throw
        }


//        $this->execute_before_save_callbacks();
//        if ($initial_status !== $this->get_status()) {
//            return;//the status has been changed in the callbacks
//        }

        //no need to create savepoint here for the

        new Event($this, '_before_save');
        $this->execute_save();
        $this->set_status(self::STATUS['SAVED']);
        $this->set_current_transaction($this->get_parent());
        new Event($this, '_after_save');

        //$this->execute_after_save_callbacks();

    }

    public function execute(callable $callable) /* mixed */
    {
        if ($this->get_status() !== self::STATUS['CREATED']) {
            //throw
        }
        $this->begin();
        $ret = $callable();
        $this->commit();
        return $ret;
    }

    public function create_savepoint(string $savepoint_name) : void
    {
        $this->execute_create_savepoint($savepoint_name);
    }

    public function get_status() : string
    {
        return $this->status;
    }

    public function set_status(string $status) : void
    {
        $current_status = $this->get_status();
        if (in_array($current_status, self::END_STATUSES, TRUE)) {
            throw new InvalidArgumentException(sprintf(t::_('The current status of the transaction is %1s. Transactions in statuses %s2 can not be changed. The provided status is %3s.'), $current_status, implode(', ', self::END_STATUSES), $status ));
        }
        if ($status === self::STATUS['COMMITTED']) {
            $allowed_statuses = [self::STATUS['SAVED'], self::STATUS['STARTED'] ];
            if (!in_array($current_status, $allowed_statuses )) {
                throw new InvalidArgumentException(sprintf(t::_('The provided $status %1s can not be set as the current status of the transaction %s2 does not allow to transition to the provided status. Only %3s statuses can transition to the provided status.'), $status, $current_status, implode(', ', $allowed_statuses)));
            }
        }

        if ($status === self::STATUS['COMMITTED']) {
            //we need to update the status on all nested transactions
            foreach ($this->get_nested() as $transaction) {
                if ($transaction->get_status() === self::STATUS['SAVED']) {
                    $transaction->set_status($status);
                } else {
                    //child transactions with status ROLLEDBACK (and any others) are left as they are... rolledback transaction can not be committed
                }
            }
        }

        $this->status = $status;
    }

    abstract public function get_resource() : TransactionalResourceInterface ;

    abstract protected function execute_begin() : void;

    abstract protected function execute_commit() : void;

    abstract protected function execute_rollback() : void;

    abstract protected function execute_create_savepoint(string $savepoint) : void;

    abstract protected function execute_rollback_to_savepoint(string $savepoint) : void;

    abstract protected function execute_release_savepoint(string $savepoint) : void;

    private function execute_before_callbacks() : void
    {

    }

    private function execute_after_callbacks() : void
    {

    }

    private function execute_before_rollback_callbacks() : void
    {

    }

    private function execute_after_rollback_callbacks() : void
    {

    }

    private function execute_before_save_callbacks() : void
    {

    }

    private function execute_after_save_callbacks() : void
    {

    }

    private function execute_before_commit_callbacks() : void
    {

    }

    private function execute_after_commit_callbacks() : void
    {

    }

}