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

namespace Guzaba2\Orm;

use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Object\GenericObject;
use Guzaba2\Orm\Object\Transaction;
use Guzaba2\Transaction\ScopeReferenceTracker;
use Guzaba2\Kernel\Kernel as k;
use Guzaba2\Transaction\TransactionContext;
use Guzaba2\Translator\Translator as t;

/**
 * This object actually stores the transaction data instead of using an external driver/storage (like the DB)
 * This transaction behaves exactly like the framework\objects\classes\transaction with the addition that the constructor stores all the activeRecordInstances at the time of the transaction start.
 * Also if during an already started transaction a new ActiveRecord instance is obtained it will be automatically registered in the transaction (there is code for this in activeRecord)
 * This transaction type also supports the add() method to track nonORM objects.
 */
class ORMObjectTransaction extends Transaction
{

    /**
     * An array with objet_internal_id of the objects that were added at the transaction start
     * If an object is added at a later time it will not be in this array
     */
    protected $object_ids_added_at_transaction_start = [];

    public function __construct(?ScopeReferenceTracker &$scope_reference = NULL, ?callable $code = NULL, ?callable &$commit_callback = NULL, ?callable &$rollback_callback = NULL, array $options = [], ?TransactionContext $transactionContext = null)
    {
        if (!isset($options['transaction_type'])) {
            $options['transaction_type'] = self::class;
        }

        parent::__construct($scope_reference, $code, $commit_callback, $rollback_callback, $options, $transactionContext);

        $objects =& ActiveRecord::get_instances_as_single_array();
        foreach ($objects as &$object) {
            $this->add($object, TRUE);
        }
        unset($object);

        $new_objects =& ActiveRecord::get_new_instances_as_single_array();
        foreach ($new_objects as &$new_object) {
            $this->add($new_object, TRUE);
        }
        unset($new_object);
    }

    /**
     *
     * @override
     * @param GenericObject $object
     * @param bool $added_at_transaction_start
     * @return bool
     */
    public function add(GenericObject &$object, bool $added_at_transaction_start = FALSE): bool
    {
        return $this->add_object($object, $added_at_transaction_start);
    }

    /**
     *
     * @override
     * @param GenericObject $object
     * @param bool $added_at_transaction_start
     * @return bool
     */
    public function add_object(GenericObject &$object, bool $added_at_transaction_start = FALSE): bool
    {
        if ($added_at_transaction_start) {
            $this->object_ids_added_at_transaction_start[] = $object->get_object_internal_id();
        }
        return parent::add_object($object);
    }

    /**
     * @override
     * Does the same as the parent method but does not call it.
     * There is some special handling added for ActiveRecord instances
     *
     */
    protected function restore_all_objects()
    {
        foreach ($this->get_objects() as $object) {

            // if (!($object instanceof framework\orm\classes\ActiveRecord)) {
            // 	//this object has been destroyed in the memory in the mean time
            // 	//so this means there is nothing to be rolled back - the object doesnt exist as of the current stack
            // 	continue;
            // }
            if ($object instanceof framework\orm\interfaces\instancesMember && !($object instanceof ActiveRecord)) {
                continue;
            }
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

                //throw new LogicException($message);
                $ex = new LogicException($message);
                k::log_exception($ex, FALSE);

                $this->dump_debug_data();

            //this means that the instance that was tracked no longer exists??
            } else {
                //for ORM objects that were created during the transaction instead of returning them to empty object better convert them to rolledbackInstance
                //if ($object instanceof ActiveRecord && $object->is_or_was_new()) {//this check is not correct as the object may still be new but to have been created before the transaction - in this case it should not be destroyed - only its properties should be rolled back
                $object_internal_id = $object->get_object_internal_id();
                if (
                    $object instanceof ActiveRecord
                    &&
                    $object->is_or_was_new()
                    &&
                    !in_array($object_internal_id, $this->object_ids_added_at_transaction_start)
                ) {
                    $object->_set_all_properties($this->transaction_data[$object_internal_id]);
                    //still restore the properties (see the _set_all_properties() above) as if there is a reference leak the replacement with rolledbackInstance will not work - so better have an empty object in this case
                    ActiveRecord::replace_instance_with_rolledbackInstance($object);
                } else {
                    $object->_set_all_properties($this->transaction_data[$object_internal_id]);
                }
            }
        }
    }
}
