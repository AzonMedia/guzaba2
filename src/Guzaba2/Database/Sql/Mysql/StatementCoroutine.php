<?php

declare(strict_types=1);

namespace Guzaba2\Database\Sql\Mysql;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Database\Exceptions\ParameterException;
use Guzaba2\Database\Exceptions\QueryException;
use Guzaba2\Database\Exceptions\DeadlockException;
use Guzaba2\Database\Exceptions\DuplicateKeyException;
use Guzaba2\Database\Exceptions\ForeignKeyConstraintException;
use Guzaba2\Database\Exceptions\ResultException;
use Guzaba2\Database\Interfaces\StatementInterface;
use Guzaba2\Database\Sql\QueryCache;
use Guzaba2\Database\Sql\StatementTypes;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Psr\Log\LogLevel;

class StatementCoroutine extends Statement implements StatementInterface
{

    /**
     * Used when fetch_mode = FALSE (this is the default).
     * When fetch_mode = FALSE the data if fetched immediately on execution and stored instead of being fetched with fetchAll().
     * @var array|bool
     */
    private $rows = [];

    /**
     * @param array $parameters
     * @param bool $disable_sql_cache
     * @return $this
     * @throws DeadlockException
     * @throws DuplicateKeyException
     * @throws ForeignKeyConstraintException
     * @throws InvalidArgumentException
     * @throws ParameterException
     * @throws QueryException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function execute(array $parameters = [], bool $disable_sql_cache = false): self
    {

        if ($parameters && $this->params) {
            throw new InvalidArgumentException(sprintf(t::_('It is not allowed to set parameters as properties and provide parameters as an argument to %s.'), __METHOD__));
        }
        if ($parameters) {
            $this->params = $parameters;
        }

        $this->params = $this->get_connection()::prepare_params($this->params);

        $this->disable_sql_cache_flag = $disable_sql_cache;

        $sql = $this->get_query();

        $statement_group_str = $this->get_statement_group_as_string();
        if ($statement_group_str === null) {
            throw new RunTimeException(sprintf(t::_('The statement for query %s can not be determined of which type is (DQL, DML etc...).'), $sql));
        }

        if (self::uses_service('QueryCache')) {
            /**
             * @var QueryCache
             */
            $QueryCache = self::get_service('QueryCache');
            if ($this->is_dql_statement()) {
                $exec_start_time = microtime(true);
                $cached_query_data = $QueryCache->get_cached_data($sql, $this->params);
                $exec_end_time = microtime(true);
                if ($cached_query_data) {
                    $this->cached_query_data = $cached_query_data;
                    $this->is_executed_flag = true;
                    $Apm = self::get_service('Apm');
                    $Apm->increment_value('cnt_cached_dql_statements', 1);
                    $Apm->increment_value('time_cached_dql_statements', $exec_end_time - $exec_start_time);
                    return $this;
                } else {
                    //not found in the cache => proceed
                }
            } elseif ($this->is_dml_statement()) {
                //self::$query_cache->update_tables_modification_microtime($sql);
                //this is to be updated after the query is actually executed
                //as the query may be slow and this will mean the update time will be wrong
                //but for best results as the query may not be in transaction but to update multiple records the invalidation time should be updated twice
                //before the query starts and after it ends
                //the best thing is to have all updates in transaction
                //another thing is that at the start of the query the update time can be set into the future (+2 minutes or even more) which in practice will disable caching of any queries that use this table
                //and until the query execution ends to set the time to the current one which will enable the caching
                //self::$query_cache->update_tables_modification_microtime($sql, self::UPDATE_QUERY_CACHE_LOCK_TIMEOUT);
                //the update time that is in future will be immediately overwritten after the query (no matter does it succeeds or fails)


//                if (framework\transactions\classes\transactionManager::getCurrentTransaction(framework\database\classes\transaction2::class) && self::INVALIDATE_SELECT_CACHE_ON_COMMIT) {
//                    //do not invalidate it - the current transaction will see live data as we are in transaction (see config)
//                    //and the rest of the threads can still use the cache as the current transaction is not commited
//                    //no point to invalidate the cache if the transaction is not going to be committed
//                } else {
//                    self::$query_cache->update_tables_modification_microtime($sql, self::UPDATE_QUERY_CACHE_LOCK_TIMEOUT);
//                }
                $QueryCache->update_tables_modification_microtime($sql, self::CONFIG_RUNTIME['update_query_cache_lock_timeout']);
            } else {
                //ignore
            }
        }

        $position_parameters = $this->convert_to_position_parameters($this->params);

        $exec_start_time = microtime(true);
        $ret = $this->NativeStatement->execute($position_parameters);
        $exec_end_time = microtime(true);
        $Apm = self::get_service('Apm');
        $Apm->increment_value('cnt_' . strtolower($statement_group_str) . '_statements', 1);
        $Apm->increment_value('time_' . strtolower($statement_group_str) . '_statements', $exec_end_time - $exec_start_time);
        if ($exec_end_time - $exec_start_time > self::CONFIG_RUNTIME['slow_query_log_msec'] / 1000 ) {
            $message = sprintf(
                t::_('The query took %1$s seconds to execute. The log threshold is %2$s. Query: %3$s . Params %4$s .'),
                $exec_end_time - $exec_start_time,
                self::CONFIG_RUNTIME['slow_query_log_msec'] / 1000,
                $sql,
                print_r($this->get_params(), true)
            );
            Kernel::log($message, LogLevel::WARNING);
        }

//        if ($current_transaction || $current_db_transaction) {
//            $this->disable_sql_cache = TRUE;
//        }


        if (self::uses_service('QueryCache') && !$this->is_sql_cache_disabled()) {
            $QueryCache = self::get_service('QueryCache');
            if ($this->is_dml_statement()) {
//                $current_transaction = framework\transactions\classes\transactionManager::getCurrentTransaction(framework\database\classes\transaction2::class);
//
//                if ($current_transaction && self::INVALIDATE_SELECT_CACHE_ON_COMMIT) {
//                    //add a callback here TODO
//                    //the cache needs to be cleared immediately before the master commit and after it (the actual commit in the DB may take time...)
//                    //in fact before the commit we do the same thing like the update - set the date in future so that the cache is disabled until the commit finishes
//                    //the commit should succeed within that time
//                    //avoid adding callbacks on each transaction - instead add one on the master transaction
//                    //@see http://gitlab.guzaba.org/root/guzaba-framework-v0.7/issues/9
//                    //the below works but creates a ton of callbacks
//                    /*
//                    $master_transaction = $current_transaction->get_master_transaction();
//                    $master_callback_container = $current_transaction->getCallbackContainer();
//                    $master_callback_container->add(
//                        function() use ($sql) {
//                            self::$query_cache->update_tables_modification_microtime($sql, self::UPDATE_QUERY_CACHE_LOCK_TIMEOUT);
//                        },
//                        $master_callback_container::MODE_BEFORE_COMMIT,
//                        FALSE//do not preserve the context
//                    );
//                    $master_callback_container->add(
//                        function() use ($sql) {
//                            self::$query_cache->update_tables_modification_microtime($sql);
//                        },
//                        $master_callback_container::MODE_AFTER_COMMIT,
//                        FALSE//do not preserve the context
//                    );
//                    */
//
//                    //try to reduce the callbacks by keeping only the modified tables and then execute a single callback that resets their modification times
//                    //the callback will be added immediately after a DB transaction is started - @see framework\database\classes\transaction2::__construct()
//                    $master_transaction = $current_transaction->get_master_transaction();
//                    $master_transaction_context = $master_transaction->get_context();
//                    $tables = self::$query_cache->get_tables_from_sql($sql);
//                    $invalidate_tables_for_cache = array_merge($master_transaction_context->invalidate_tables_for_cache ?? [], $tables);
//                    $invalidate_tables_for_cache = array_unique($invalidate_tables_for_cache);
//                    $master_transaction_context->invalidate_tables_for_cache = $invalidate_tables_for_cache;
//                } else {
//
//                    self::$query_cache->update_tables_modification_microtime($sql);
//
//                }
                $QueryCache->update_tables_modification_microtime($sql);
            }
        }
//        $end_time = microtime(true);
//        if ($statement_group_type) {
//            $type_str = statementTypes::STATEMENT_GROUP_MAP[$statement_group_type];
//            self::execution_profile()->increment_value('cnt_'.strtolower($type_str).'_statements', 1);
//            self::execution_profile()->increment_value('time_'.strtolower($type_str).'_statements', $end_time - $start_time);
//        }


        if ($ret === false) {
            $this->handle_error();//will throw exception
        }
//        elseif (is_array($ret)) { //Swoole\Coroutine\Mysql\Statement::execute() returns the data when fetch_mode = FALSE
//            $this->rows = $ret;
//        }
        if (!$this->Connection->get_fetch_mode()) { //IF fetch_mode === FALSE
            $this->rows = $ret;
        }
        $this->is_executed_flag = true;

        return $this;
    }

    public function fetch_all(): array
    {
        return $this->fetchAll();
    }

    public function fetchAll(?bool &$from_cache = false): array
    {
//        $ret = $this->NativeStatement->fetchAll();
//        if ($ret===FALSE) {
//            throw new QueryException(sprintf(t::_('Error executing query %s: [%s] %s.'), $this->get_query(), $this->NativeStatement->errno, $this->NativeStatement->error ));
//        }
        //$this->>execute()
        //return $this->rows;
        //$ret = $this->NativeStatement->fetchAll();//returns nothing...

        if ($this->cached_query_data) {
            $ret = $this->cached_query_data['data'];
            $from_cache = true;
        } else {
            if ($this->Connection->get_fetch_mode()) { //in fetch_mode = TRUE the data needs to be manually fetched
                $Apm = self::get_service('Apm');
                $exec_start_time = microtime(true);
                $ret = $this->NativeStatement->fetchAll();
                $exec_end_time = microtime(true);
                $Apm->increment_value('time_fetching_data', $exec_end_time - $exec_start_time);
            } else { //default is fetch_mode = FALSE
                $ret = $this->rows;
            }

            if ($ret === null) {
                throw new QueryException($this, 0, 0, sprintf(t::_('Error executing query %s: [%s] %s.'), $this->get_query(), $this->NativeStatement->errno, $this->NativeStatement->error), $this->get_query(), $this->get_params());
            }
            if (self::uses_service('QueryCache') && !$this->is_sql_cache_disabled()) {
                /**
                 * @var QueryCache
                 */
                $QueryCache = self::get_service('QueryCache');
                $sql = $this->get_query();
                $QueryCache->add_cached_data($sql, $this->params, $ret);
            }
        }

        return $ret;
    }

}
