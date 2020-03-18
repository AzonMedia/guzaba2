<?php
declare(strict_types=1);

namespace Guzaba2\Transaction;

use Guzaba2\Base\Base;
use Guzaba2\Resources\Interfaces\ResourceInterface;
use Guzaba2\Resources\ScopeReference;
use Guzaba2\Transaction\Interfaces\TransactionalResourceInterface;

abstract class Transaction extends Base implements ResourceInterface
{

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'TransactionManager',
        ],

    ];

    protected const CONFIG_RUNTIME = [];

    private string $status = self::STATUS_CREATED;

    public const STATUS_CREATED = 'created';
    public const STATUS_STARTED = 'started';
    public const STATUS_ROLLEDBACK = 'rolledback';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_SAVED = 'saved';

    public const ROLLBACK_REASON_UNKNOWN = 'unknown';
    /////

    private /* ?callable */ $callable = NULL;

    private ?Transaction $ParentTransaction = NULL;

    private CallbackContainer $CallbackContainer;

    //public function __construct(?ScopeReference &$ScopeReference, array $options = [])
    public function __construct(array $options = [])
    {
        parent::__construct();

        $this->ParentTransaction = self::get_service('TransactionManager')->get_current_transaction(get_class($this));

        $this->CallbackContainer = new CallbackContainer($this);

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
        while( $Transaction->has_parent() ) {
            $Transaction = $Transaction->get_parent();
        }
        return $Transaction;
    }

    protected function get_savepoint_name() : string
    {
        return 'SP'.$this->get_object_internal_id();
    }

    public function begin() : void
    {

        $this->set_status(self::STATUS_STARTED);
        self::get_service('TransactionManager')->set_current_transaction($this);
        if ($this->has_parent()) {
            $savepoint_name = $this->get_savepoint_name();
            $this->get_parent()->create_savepoint($savepoint_name);
        } else {
            $this->execute_begin();
        }
    }

    public function rollback() : void
    {
        print 'ROLLBACK'.PHP_EOL;
    }

    public function commit() : void
    {
        $initial_status = $this->get_status();
        if (!in_array($initial_status, [ self::STATUS_STARTED, self::STATUS_SAVED ], TRUE )) {
            //throw
        }

        $this->execute_before_callbacks();
        if ($initial_status !== $this->get_status()) {
            return;//the status has been changed in the callbacks
        }

        if ($this->has_parent()) {
            $this->save();
        } else {

            $this->call_before_commit_callbacks();

            if ($initial_status !== $this->get_status()) {
                return;//the status has been changed in the callbacks
            }

            $this->execute_commit();
            $this->set_status(self::STATUS_COMMITTED);

            self::get_service('TransactionManager')->set_current_transaction(NULL, get_class($this));

            $this->call_after_commit_callbacks();
        }

        $this->execute_after_callback();
    }

    protected function save() : void
    {
        $initial_status = $this->get_status();
        $this->execute_before_save_callbacks();
        if ($initial_status !== $this->get_status()) {
            return;//the status has been changed in the callbacks
        }
//no need to create savepoint here for the
        $this->execute_save();

        $this->set_status(self::STATUS_SAVED);

        self::get_service('TransactionManager')->set_current_transaction($this->get_parent());

        $this->execute_after_save_callbacks();
    }

    public function execute(callable $code) /* mixed */
    {
        if ($this->get_status() !== self::STATUS_CREATED) {
            //throw
        }
        $this->begin();
        $ret = $code();
        $this->commit();
        return $ret;
    }

    public function create_savepoint(string $savepoint_name) : void
    {
        $this->execute_create_savepoint($savepoint_name);
    }

    public function get_status() : string
    {

    }

    public function set_status(string $status) : void
    {

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

}