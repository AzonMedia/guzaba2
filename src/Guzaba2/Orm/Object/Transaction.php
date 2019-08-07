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

namespace Guzaba2\Orm\Object;

use Azonmedia\Utilities\AlphaNumUtil;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\NotImplementedException;
use Guzaba2\Object\GenericObject;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Transaction\ScopeReferenceTracker;
use Guzaba2\Transaction\TransactionContext;
use Guzaba2\Kernel\Kernel as k;
use Guzaba2\Translator\Translator as t;

/**
 * This object actually stores the transaction data instead of using an external driver/storage (like the DB)
 *
 *
 */
class Transaction extends \Guzaba2\Transaction\Transaction
{

    protected $priority = 0;
    protected static $default_transaction_type = 'objects';

    /**
     * The tracked objects for this transaction.
     * @var array
     */
    protected $objects = [];

    /**
     * This stored the data for this transaction.
     * Preserves the properties of the objects at the beginning of the transaction (or more precisely - when they are add()ed)
     * There will be only one set of data for the transaction. When there is a sub transaction we use its set of data to rollback.
     */
    protected $transaction_data = [];

    //debug
    protected $dbg_data = [];

    //public function __construct(&$scope_reference = '&', $code = NULL, &$commit_callback = '', &$rollback_callback = '', array $options = [], ?transactionContext $context = NULL) {
    public function __construct(?ScopeReferenceTracker &$scope_reference = NULL, ?callable $code = NULL, ?callable &$commit_callback = NULL, ?callable &$rollback_callback = NULL, array $options = array(), TransactionContext $transactionContext = null)
    {

        if (!isset($options['transaction_type'])) {
            $options['transaction_type'] = self::class;
        }

        parent::__construct($scope_reference, $code, $commit_callback, $rollback_callback, $options, $transactionContext);

        //this transaction needs to inherit all tracked objects by the parent one if there are such
        if ($this->has_parent()) {
            foreach ($this->get_parent()->get_tracked_objects() as &$object) {
                if ($object instanceof GenericObject) {
                    $this->add_object($object);
                }
            }
        }
    }

    /**
     * Alias of $this->add_object()
     * @param GenericObject $object
     * @return bool
     */
    public function add(GenericObject &$object): bool
    {
        return $this->add_object($object);
    }

    public function add_object(genericObject &$object): bool
    {
        //check is the object already in the tracked list
        foreach ($this->get_objects() as $tracked_object) {
            if ($tracked_object === $object) {
                return FALSE;//it is already added
            }
        }

        if ($object instanceof ActiveRecord) {
            $this->dbg_data[] = ['class' => get_class($object), 'lookup_index' => $object->get_lookup_index(), 'internal_id' => $object->get_object_internal_id()];
        } else {
            $this->dbg_data[] = ['class' => get_class($object), 'lookup_index' => 'N/A', 'internal_id' => $object->get_object_internal_id()];
        }


        $this->objects[] =& $object;

        //when a new object is added its state must be preserved immediately
        $this->store_object_properties($object);

        return TRUE;
    }

    protected function dump_debug_data(): void
    {
        $str = 'ADDDED OBJECTS' . PHP_EOL;
        foreach ($this->dbg_data as $entry) {
            $str .= implode(' ', $entry) . PHP_EOL;
        }
        $str .= 'CURRENT OBJECTS' . PHP_EOL;
        foreach ($this->objects as $object) {
            if (is_object($object)) {
                //$str .= get_class($object).' '.( $object instanceof ActiveRecord ? $object->get_lookup_index() : 'N/A' ).' '.$object->get_object_internal_id().PHP_EOL;
                $str .= AlphaNumUtil::as_string($object);
            } else {
                $str .= 'NULL N/A N/A' . PHP_EOL;
            }
        }

        k::logtofile('MEMORY_TRANSACTIONS_DEBUG_DATA', $str);
    }


    /**
     * Alias of get_tracked_objects()
     *
     */
    public function get_objects(): array
    {
        return $this->get_tracked_objects();
    }

    public function get_tracked_objects(): array
    {
        return $this->objects;
    }

    protected function store_object_properties(genericObject $object)
    {
        //does not check are the properties already stored - this is done when the object is added (it cant be added twice)
        $this->transaction_data[$object->get_object_internal_id()] = $object->_get_all_properties();
    }

    protected function restore_all_objects()
    {
        foreach ($this->get_objects() as $object) {
            // if (!($object instanceof ActiveRecord)) {
            // 	//this object has been destroyed in the memory in the mean time
            // 	//so this means there is nothing to be rolled back - the object doesnt exist as of the current stack
            // 	continue;
            // }
            if (!is_object($object)) {
                continue;
            }

            if (!isset($this->transaction_data[$object->get_object_internal_id()])) {

                k::logtoemail('', 'MEMORY TRANSACTION OBJECT ERROR', null, true);

                //die(print_r($this->transaction_data));//NOVERIFY
                if ($object instanceof ActiveRecord) {
                    $message = sprintf(t::_('There is no transaction_data for tracked object "%s" (internal ID: %s) with ID: %s for transaction ID: %s.'), get_class($object), $object->get_object_internal_id(), $object->get_lookup_index(), $this->get_object_internal_id());
                } else { // shouldnt be happening
                    $message = sprintf(t::_('There is no transaction_data for tracked object "%s" (internal ID: %s) for transaction ID: %s.'), get_class($object), $object->get_object_internal_id(), $this->get_object_internal_id());
                }

                //throw new framework\base\exceptions\logicException($message);
                $ex = new LogicException($message);
                k::log_exception($ex, FALSE);

                $this->dump_debug_data();

                //this means that the instance that was tracked no longer exists??
            } else {
                $object->_set_all_properties($this->transaction_data[$object->get_object_internal_id()]);
            }

        }
    }

    protected function execute_begin(): bool
    {
        //must save a copy of the properties of the tracked objects at the beginning of the transaction
        //$this->execute_create_savepoint();
        //the begin() is executed by the constructor which means that at this stage there are no objects added
        //the current implementation does not allow objects to be tracked to be passed to the constructor
        //instead they should be added with add()
        foreach ($this->get_objects() as $object) {
            $this->store_object_properties($object);
        }
        return TRUE;
    }

    protected function execute_commit(): bool
    {
        //does nothing - there is no commit() - the object just stay in the status they are
        return TRUE;
    }

    protected function execute_rollback(): bool
    {
        //only the rollback matters for the object transactions - it restored to the state in $transaction_data
        $this->restore_all_objects();
        return TRUE;
    }

    protected function execute_create_savepoint(string $savepoint): bool
    {
        //this is like commit (of a nested transaction) - does nothing
        return TRUE;
    }

    /**
     * This method is executed on the parent transaction that contains the savepoint.
     * This means that this method must go through its nested transactions and rollback all transactions that are after the transaction for which the provided savepoint was created (including this transaction).
     * This only executes the rollback on the data (drvier) - it is not supposed to call the rollback() method on any of the transactions - this method is called by the scope references or explicitely by the parent transaction if the parent transaction is rolled back.
     * This method needs to ensure that the data is rolled back to the provided savepoint
     */
    protected function execute_rollback_to_savepoint(string $savepoint): bool
    {
        //the transactions here are ordered by the time they were started
        //but they must be rolled back in the reverse way
        //the end of the transactions stack will be probably already rolled back automatically by the pointers
        //also in practice it wont happen out of three nested transactions a rollback to occur to the second one (if the third started)
        //As per the graph below to have a rollback to B - this would happen only if manually someone does this
        //
        //   +----A-----+   +----B-----+   +----C----+
        //+----------------------------------------------+


        $transaction_id_from_which_to_rollback = self::getTransactionIdFromSavepointName($savepoint);
        $must_rollback = FALSE;
        //as currnetly the code is using scope references this loop sbhould rollback at the most one nested transaction
        //but just in case in future it is allowed to rolblack to a specific savepoint the nested transactions should be rolled back in a reverse way
        /*
        foreach ($this->get_nested() as $transaction) {
            //k::logtofile('DV_301', $savepoint.' '.$transaction_id_from_which_to_rollback);

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
        unset ($transaction);

        $transactions_to_be_rolled_back = array_reverse($transactions_to_be_rolled_back);
        foreach ($transactions_to_be_rolled_back as $transaction) {
            $transaction->execute_rollback();
            //$transaction->rollback();//this will trigger a recursion ... but needs to be executed to update the transaction status and the callbacks
        }

        return TRUE;
    }

    protected function _before_destroy(): void
    {
        $this->objects = [];
        $this->transaction_data = [];
        parent::_before_destroy();
    }

    /**
     * @param string $savepoint
     * @return bool
     * @throws NotImplementedException
     */
    protected function execute_release_savepoint(string $savepoint): bool
    {
        throw new NotImplementedException();
    }
}