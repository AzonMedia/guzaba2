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

use Azonmedia\Utilities\AlphaNumUtil;
use Guzaba2\Database\Connection;
use Guzaba2\Database\Transaction;
use Guzaba2\Transaction\DistributedTransaction;
use Guzaba2\Transaction\ScopeReferenceTracker;
use Guzaba2\Transaction\TransactionContext;
use Guzaba2\Translator\Translator as t;

/**
 * This object actually stores the transaction data instead of using an external driver/storage (like the DB)
 * This transaction behaves exactly like the framework\objects\classes\transaction with the addition that the constructor stores all the activeRecordInstances at the time of the transaction start.
 * Also if during an already started transaction a new activeRecord instance is obtained it will be automatically registered in the transaction (there is code for this in activeRecord)
 * This transaction type also supports the add() method to track nonORM objects.
 */
class ORMDBTransaction extends DistributedTransaction
{

    //protected static $default_transaction_type = 'ORMDB';

    //public function __construct(&$scope_reference = '&', $code = NULL, &$commit_callback = '', &$rollback_callback = '', array $options = [], ?transactionContext $context = NULL) {
    public function __construct(?ScopeReferenceTracker &$scope_reference = NULL, ?callable $code = NULL, ?callable &$commit_callback = NULL, ?callable &$rollback_callback = NULL, array $options = [], ?TransactionContext $transactionContext = null)
    {
        if (!isset($options['connection'])) { //we expect here a reference
            $options['connection'] = Connection::get_instance();
        } else {
            if (!($options['connection'] instanceof Connection)) {
                throw new framework\objects\exceptions\objectOptionException(sprintf(t::_('The provided value to the "connection" option to the %s class must be of class %s. Instead "%s" was provided.'), __CLASS__, Connection::class, AlphaNumUtil::as_string($options['connection'])));
            }
        }

        //if (!isset($options['transaction_type'])) {
        //	//$options['transaction_type'] = self::get_default_transaction_type().'_'.$DBTransaction->getOptionValue('transaction_type');//todo - add support for multiple transactions of this type
        //	$options['transaction_type'] = self::class;
        //}

        //it is very important the transactions to be attached first and only then to invoke the parent constructor
        //$this->attach_transaction($ORMObjectTransaction);
        //$this->attach_transaction($DBTransaction);
        //no - this creates other issues... instead we need to use _after_construct() hook
        //but if this hook gets overriden by a child transaction that does not invoke the paret _after_construct() this will be a problem
        //this is needed as it is good the transaction to be initialized by the constructor
        //the _after_construct() hook is invoked at the very end of the constructor but before the begin()
        //because of the above and the transaction constructor arguments it is best to have a separate argumetn to suppress the automatic begin
        parent::__construct($scope_reference, $code, $commit_callback, $rollback_callback, $options, $transactionContext, $do_not_begin = TRUE);

        //the DB transaction should be started first...
        //as it may throw exceptions - for example when the connection doesnt support transactions
        //and then if the ORMObjectTransaction is started but not yet attached it will be just left hanging...
        //also once it is started should be attached instead of first starting them both and then attaching them
        $DBTransaction = Transaction::simple_construct_without_callbacks($options, $transactionContext);
        $this->attach_transaction($DBTransaction);

        $ORMObjectTransaction = ORMObjectTransaction::simple_construct_without_callbacks($options, $transactionContext);
        $this->attach_transaction($ORMObjectTransaction);

        $this->begin();
    }

    public function get_database_transaction(): ?Transaction
    {
        $ret = NULL;
        $transactions = $this->get_transactions();
        foreach ($transactions as $transaction) {
            if ($transaction instanceof Transaction) {
                $ret = $transaction;
            }
        }
        return $ret;
    }
}
