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

namespace Guzaba2\Database;

use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Transaction\ScopeReferenceTracker;
use Guzaba2\Transaction\TransactionContext;
use Guzaba2\Kernel\Kernel as k;
use Guzaba2\Translator\Translator as t;


/**
 * Currnetly if a second database transaction is needed a new class needs to be created - transation3, transaction4... that extends this class
 *
 */
class Transaction extends \Guzaba2\Transaction\Transaction
{
    protected const DB_TRANSACTION_LOGGING_ENABLED = false;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var array
     */
    protected $statements = [];

    /**
     * @todo check if need to be refactored
     * @var array
     */
    protected static $supportedOptions = [
        'connection' => '',
    ];

    /**
     * @var int
     */
    protected $priority = 50;

    protected static $services = [
        'logger' => '',
    ];

    /**
     * Transaction constructor.
     * @param ScopeReferenceTracker|null $scope_reference
     * @param callable|null $code
     * @param callable|null $commit_callback
     * @param callable|null $rollback_callback
     * @param array $options
     * @param TransactionContext|null $transactionContext
     * @throws Exceptions\TransactionException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\LogicException
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     */
    public function __construct(?ScopeReferenceTracker &$scope_reference = NULL, ?callable $code = NULL, ?callable &$commit_callback = NULL, ?callable &$rollback_callback = NULL, array $options = [], ?TransactionContext $transactionContext = null)
    {

        //if a transaction is started then the execution data should be stored
        //this must happen before the transaction is started in DB as otherwise then the execution data may get rolled back
        k::get_execution()->perform_save_in_db();

        if (!isset($options['connection'])) { //we expect here a reference
            $this->connection = Connection::get_instance();
        } else {
            if (!($options['connection'] instanceof Connection)) {
                throw new framework\objects\exceptions\objectOptionException(sprintf(t::_('The provided value to the "connection" option to the %s class must be of class %s.'), __CLASS__, Connection::class));
            }
            $this->connection = $options['connection'];
        }
        $options['connection'] = $this->connection;

        if (!isset($options['transaction_type'])) {
            $options['transaction_type'] = get_class($this->connection);
        }

        parent::__construct($scope_reference, $code, $commit_callback, $rollback_callback, $options, $transactionContext);

        //on the master transaction add two callbacks for clearing the cache on master commit
        if (\Guzaba2\Database\PdoStatement::ENABLE_SELECT_CACHING && \Guzaba2\Database\PdoStatement::INVALIDATE_SELECT_CACHE_ON_COMMIT && $this->is_master()) {
            //the callbacks need to be added only on the master transaction
            //the tables to cleared are filled by all other transactions in pdOstatement::execute()
            $master_callback_container = $commit_callback;
            $master_callback_container->add(
                function () {
                    if (isset($this->get_context()->invalidate_tables_for_cache)) {
                        $invalidate_tables_for_cache = $this->get_context()->invalidate_tables_for_cache;
                        foreach ($invalidate_tables_for_cache as $table) {
                            queryCache::get_instance()->update_table_modification_microtime($table, PdoStatement::UPDATE_QUERY_CACHE_LOCK_TIMEOUT);
                        }
                    }
                },
                $master_callback_container::MODE_BEFORE_COMMIT,
                FALSE//do not preserve the context
            );
            $master_callback_container->add(
                function () {
                    if (isset($this->get_context()->invalidate_tables_for_cache)) {
                        $invalidate_tables_for_cache = $this->get_context()->invalidate_tables_for_cache;
                        foreach ($invalidate_tables_for_cache as $table) {
                            queryCache::get_instance()->update_table_modification_microtime($table);
                        }
                    }
                },
                $master_callback_container::MODE_AFTER_COMMIT,
                FALSE//do not preserve the context
            );
        }

    }

    /**
     * @param PdoStatement $statement
     */
    public function addStatement(PdoStatement &$statement)
    {
        $this->statements[] =& $statement;
    }

    /**
     * @return array
     */
    public function getStatements(): array
    {
        return $this->statements;
    }

    /**
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * @return bool
     * @throws RunTimeException
     */
    protected function execute_begin(): bool
    {
        if (!$this->is_master()) {
            //check the used connections on this nested transaction and the master transaction
            $master_transaction = $this->get_master_transaction();
            if ($this->getConnection() != $master_transaction->getConnection()) {
                $message = sprintf(t::_('Attempting to start a transaction on another connection "%s" while there is already a transaction running one one connection "%s".'), get_class($this->getConnection()), get_class($master_transaction->getConnection()));
                //throw new framework\transactions\exceptions\transactionException($this, $message);
                throw new RunTimeException($message);
            }
        }

        $ret = $this->connection->get_driver()->beginTransaction();

        if (self::DB_TRANSACTION_LOGGING_ENABLED && $this->is_master()) {
            //the logger service is not affected by the transactions as it uses a second connection
            $message = sprintf(t::_('Started a MASTER DB transaction.'));
            $label = 'transactions';
            self::logger()->info($message, $label);
        }

        return $ret;
    }

    /**
     * @return bool
     */
    protected function execute_commit(): bool
    {
        $ret = $this->connection->get_driver()->commit();

        if (self::DB_TRANSACTION_LOGGING_ENABLED && $this->is_master()) {
            //the logger service is not affected by the transactions as it uses a second connection
            $message = sprintf(t::_('Commited a MASTER DB transaction.'));
            $label = 'transactions';
            self::logger()->info($message, $label);
        }

        return $ret;
    }

    /**
     * @return bool
     */
    protected function execute_rollback(): bool
    {
        $ret = $this->connection->get_driver()->rollback();

        if (self::DB_TRANSACTION_LOGGING_ENABLED && $this->is_master()) {
            //the logger service is not affected by the transactions as it uses a second connection
            $message = sprintf(t::_('Rolled back a MASTER DB transaction.'));
            $label = 'transactions';
            self::logger()->info($message, $label);
        }

        return $ret;
    }

    /**
     * @param string $savepoint
     * @return bool
     */
    protected function execute_create_savepoint(string $savepoint): bool
    {
        return $this->connection->get_driver()->createSavepoint($savepoint);
    }

    /**
     * @param string $savepoint
     * @return bool
     */
    protected function execute_rollback_to_savepoint(string $savepoint): bool
    {
        return $this->connection->get_driver()->rollbackToSavepoint($savepoint);
    }

    /**
     * @param string $savepoint
     * @return bool
     */
    protected function execute_release_savepoint(string $savepoint): bool
    {
        return $this->connection->get_driver()->releaseSavepoint($savepoint);
    }

    protected function _before_destroy(): void
    {
        //$this->statements = [];//this will destroy the references
        //while this will actually destroy the objects if they have been passed with &
        foreach ($this->statements as &$statement) {
            $statement = null;
        }
        //and then reset the array it self as it is now full of NULLs
        $this->statements = [];

        parent::_before_destroy();
    }

    public function add_statement(\Guzaba2\Database\PdoStatement &$statement)
    {
        $this->statements[] = $statement;
    }
}