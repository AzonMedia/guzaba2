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
 * @package        Database
 * @subpackage    PDO
 * @copyright    Copyright (c) Guzaba Ltd - http://guzaba.com
 * @license        http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author        Vesselin Kenashkov <vesko@webstudiobulgaria.com>
 */

namespace Guzaba2\Database;

use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Database\Exceptions\DeadlockException;
use Guzaba2\Database\Exceptions\DuplicateKeyException;
use Guzaba2\Database\Exceptions\ForeignKeyConstraintException;
use Guzaba2\Database\Exceptions\ParameterException;
use Guzaba2\Database\Exceptions\QueryException;
use Guzaba2\Database\Exceptions\ResultException;
use Guzaba2\Database\Exceptions\TransactionException;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Kernel\Kernel as k;
use Guzaba2\Mvc\ActiveEnvironment;
use Guzaba2\Orm\Exceptions\SingleValidationFailedException;
use Guzaba2\Transaction\TransactionManager;
use Guzaba2\Translator\Translator as t;

/**
 * Supports only named placeholders (:place). It is overloaded and values can be bind to the parameters directly $stmt->param = $value.
 *
 * IMPORTANT!! PHP PDO always returns (php type) string no matter what is the database column type
 * IMPORTANT!! PHP PDO int type internally is limited to 2147483647 (32 bit signed int) even on 64 bit platforms
 * When float is provided this class uses PDO::PARAM_STR
 */
final class PdoStatement extends Statement
{
    public const ENABLE_SELECT_CACHING = true;
    public const ENABLE_SELECT_CACHING_DURING_TRANSACTION = false;
    public const UPDATE_QUERY_CACHE_LOCK_TIMEOUT = 120;
    public const INVALIDATE_SELECT_CACHE_ON_COMMIT = true;

    /**
     * Version information
     * @var array
     */
    protected static $_version_data = array(
        'revision' => '$Rev:: 198                                              $:',
        'author' => '$Author:: vesko                                         $:',
        'date' => '$Date:: 2009-11-24 19:38:52 +0200 (Tue, 24 Nov 2009)    $:',
    );

    /**
     * The wrapped PDOStatement object
     * @var \PDOStatement
     */
    private $statement;

    /**
     * An internal array used to store the binded parameters. It is not required by \PDOStatement, but it is used to check and get the values of already set parameters. No unsetting is allowed.
     * @var array
     */
    private $params = [];

    /**
     * Will be populated if this statement is part of a transaction
     * @var Transaction
     */
    protected $transaction = null;

    /**
     * @var bool
     */
    protected $executed = false;

    /**
     * @var int
     */
    protected static $total_sql_time = 0;//the total time spent executing SQL queries
    //this will be a combination from all connections not just one

    /**
     * @var bool
     */
    public $profile_enabled = false;

    /**
     * @var bool
     */
    public static $profile_enabled_slow_execution = false;

    /**
     * @var bool
     */
    public $profile_log_caller = true;

    /**
     * @var bool
     */
    protected $slow_query_log = true;

    /**
     * @var int
     */
    protected $slow_query_log_time = 5;

    /**
     * The cached data from the query if caching is enabled
     * @var array
     */
    protected $cached_query_data = [];

    /**
     * @todo implement query cache
     * @var QueryCache
     */
    protected static $query_cache;

    /**
     * Runtime flag that allows the caching for a specific query to be switched off.
     * @see self::execute()
     * @var bool
     */
    protected $disable_sql_cache = FALSE;

    /**
     * @var string
     */
    protected $sql;

    public static function _initialize_class(): void
    {
        self::$query_cache = queryCache::get_instance();
    }

    /**
     * Unlike with other methods, PHP will not generate an E_STRICT level error message when __construct() is overridden with different parameters than the parent __construct() method has.
     * http://devzone.zend.com/article/15273
     * In a nutshell, ctors (constructors) are not subject to LSP (liskov sub. principle) because a type does not yet exist; the type is the product of the ctor.
     * @param \PDOStatement $statement
     * @param Connection $connection
     * @param null $transaction
     */
    public function __construct(\PDOStatement $statement, Connection $connection, $transaction = null)
    {
        $this->statement = $statement;
        $this->connection = $connection;
        $this->transaction = $transaction;
        if ($this->transaction) {
            $this->transaction->add_statement($this);
        }
        $this->statement->setFetchMode(Pdo::FETCH_MODE);

        parent::__construct($connection, $statement->get_sql());
    }

    public function __destruct()
    {
        parent::__destruct();
        $this->destroy();
    }

    /**
     * Returns the total time spent executing SQL queries (from all connections) in seconds
     * @return float
     * @created 20.10.2017
     * @author vesko@azonmedia.com
     * @since 0.7.1
     */
    public static function get_total_sql_time(): float
    {
        return self::$total_sql_time;
    }

    public function destroy()
    {
        $this->statement = NULL;
    }

    /**
     * @return mixed
     */
    public function get_transaction()
    {
        return $this->transaction;
    }

    public function get_connection()
    {
        return $this->connection;
    }

    public function is_in_transaction()
    {
        return $this->transaction ? true : false;
    }

    /**
     * Is SQL cache disabled for this specific query (when it was execute()d)
     * @return bool
     */
    public function is_sql_cache_disabled(): bool
    {
        return $this->disable_sql_cache;
    }

    /**
     * Empty function as the statement is prepared when the \PDOStatement object was created. This protected function is required by the abstract class statement and it is called by the constructor to prepare the statement.
     * @param string|null $sql
     * @return \PDOStatement
     */
    public function prepare(?string $sql = NULL): \PDOStatement
    {
        return $this->statement;
    }


    public function is_executed()
    {
        return $this->executed;
    }

    /**
     * @param string $param
     * @return mixed
     * @throws ParameterException
     */
    public function __get(string $param)
    {
        if (isset($this->params[$param])) {
            $ret = $this->params[$param];
        } else {
            $query = $this->getQueryString();
            $params = $this->params;
            throw new ParameterException($param, sprintf(t::_('Trying to get a nonset parameter "%s".'), $param), $query, $params);
        }
        return $ret;
    }

    /**
     * @param string $param
     * @param $value
     * @throws ParameterException
     * @throws RunTimeException
     */
    public function __set(string $param, $value): void
    {
        //print 'set '.$param.'<br />';
        //if ($this->is_executed()) {

        if ($this->is_executed() && count($this->params)) {
            //if ($this->is_executed()) {
            //die(print_r($this->params));
            //k::logtofile_backtrace('zzz');
            throw new RunTimeException(sprintf(t::_('The current statement has been already executed. No more binding of parameters is possible. Only fetching the data is allowed. Trying to bing parameter named "%s". Maybe misspelled fetch method?'), $param));
        }

        //can a parameter be bound more than once?
        /*
        if (isset($this->params[$param])) {
            throw new exception;
        }
         */
        //if (is_int($value)) {
        //if (is_int($value)||is_float($value)) { //there is no \PDO::PARAM_FLOAT ?!
        if (is_int($value)) {
            $type = \PDO::PARAM_INT;
            //} elseif (is_string($value)) {
        } elseif (is_string($value) || is_float($value)) {
            $type = \PDO::PARAM_STR;
        } elseif (is_bool($value)) {
            //$type = \PDO::PARAM_BOOL;//does not work with MySQL
            //the above produces a nasty issue if we provide a bool from PHP to a mysql Bool column
            //the issue is that the statement execution just returns false - not executed
            //internally the mysql bools are tiny INTs... so we better work with ints
            //the issue above may be a bug in the PDO driver which may be resolved in future versions
            $type = \PDO::PARAM_INT;
            $value = (int)$value;
        } elseif (is_null($value)) {
            $type = \PDO::PARAM_NULL;
        } elseif (is_array($value)) {
            if (!isset($value[0])) {
                $query = $this->getQueryString();
                $params = $this->params;
                throw new ParameterException($param, sprintf(t::_('The parameter "%s" is array but is not a numerically indexed array.'), $param), $query, $params);
            }
            for ($aa = 0; $aa < count($value); $aa++) {
                if (!isset($value[$aa])) {
                    throw new RunTimeException(sprintf(t::_('The provided array to the binding is not with secuential integer indexes.')));
                }
                $this->{$param . $aa} = $value[$aa];//calls the __set overloading again with the element value
            }
            return;//it must stop here otherwise it will try to assing the array
        } else {
            $query = $this->getQueryString();
            $params = $this->params;
            throw new ParameterException($param, sprintf(t::_('Trying to bind to "%s" parameter an unsupported value type of "%s".'), $param, gettype($value)), $query, $params);
        }
        //TODO - add support for PDO::PARAM_LOB
        $this->params[$param] = $value;
        try {
            $this->statement->bindValue(':' . $param, $value, $type);
        } catch (\PDOException $exception) {
            //clear the SQL cache and try again
            //but the above will not help because the statement is already prepared
            //it needs to be updated and reprepared
            //k::logtofile('dd',$this->statement->queryString);
            //the exception has to be caught and thrown again as the \PDOException does not extend the baseException
            $message = $exception->getMessage() . ' PLEASE CLEAR THE CACHE AT ./cache/sql/ AND TRY AGAIN.';
            $query = $this->getQueryString();
            $params = $this->params;
            throw new ParameterException($param, $message, $query, $params, $exception);
        }

    }

    public function getParams()
    {
        return $this->params;
    }

    public function clearParams()
    {
        //print '=========Clear params========'.'<br />';
        $this->params = array();
    }

    public function __isset(string $param): bool
    {
        return isset($this->params[$param]);
    }

    /**
     * @param string $param
     * @throws ParameterException
     */
    public function __unset(string $param): void
    {
        $query = $this->getQueryString();
        $params = $this->params;
        throw new ParameterException($param, sprintf(t::_('Trying to unbind value for "%s" parameter. Unbinding is not possible.'), $param), $query, []);
    }

    public function __call(string $method, array $args)
    {

        if (method_exists($this->statement, $method)) {
            return call_user_func_array(array($this->statement, $method), $args);
        } elseif (count($args) === 1) { //permits creating the members (by overloading)
            $this->$method = $args[0];
            return $this;
        } else {
            return parent::__call($method, $args);
        }
    }

    /**
     * @param string $method
     * @param array $args
     * @return object|void
     * @todo fix no static call of statement
     */
    public static function __callStatic(string $method, array $args)
    {
        call_user_func_array(array(get_class($this->statement)), $args);
    }

    /**
     * Because it is not possible to pass variables by reference it is not possible to call bindparam using the __call overloading a dedicated function for binding variables to parameters must be provided
     * @param string $param
     * @param mixed $value
     */
    public function bindParam($param, &$value)
    {
        $this->statement->bindParam($param, $value);
    }

    /**
     * Because it is not possible to pass variables by reference it is not possible to call bindparam using the __call overloading a dedicated function for binding variables to columns must be provided
     * @param string $column
     * @param mixed $value
     */
    public function bindColumn($column, &$value)
    {
        $this->statement->bindColumn($column, $value);
    }

    /**
     * Returns the sql query with replaced placeholders
     * @return string
     */
    public function getQueryString()
    {
        $ret = $this->statement->get_sql();
        return $ret;
    }

    /**
     *
     * @return string Used to get extra debug information about the parameters
     */
    public function debugDumpParams()
    {
        ob_start();
        $this->statement->debugDumpParams();
        $string = ob_get_contents();
        ob_end_clean();
        return $string;
    }

    /**
     * @throws RunTimeException
     */
    private function check_is_executed()
    {
        if (!$this->is_executed()) {
            $caller = $this->_get_caller(1);
            $line = '';
            if (isset($caller['file']) && isset($caller['line'])) {
                $line = k::get_line($caller['file'], $caller['line']);
            }
            //die($caller['function']);

            if ($line) {
                //die($line);
                $line = str_replace('->' . $caller['function'], '->execute()->' . $caller['function'], $line);
                //die($line);
                //throw new RunTimeException(sprintf(t::_('%s::%s() can not be used before the statement is executed. Please call <span style="color: red;"></span> first.'), __CLASS__, $caller['function'], str_replace($line, '->' . $caller['function'], '->execute()->' . $caller['function'])));
                throw new RunTimeException(sprintf(t::_('%s::%s() can not be used before the statement is executed. Please call <span style="color: red;">%s</span>.'), __CLASS__, $caller['function'], $line));
            } else {
                throw new RunTimeException(sprintf(t::_('%s::%s() can not be used before the statement is executed. Please call <span style="color: red;">%s->execute()</span> first.'), __CLASS__, $this->_get_caller_method(), __CLASS__));
            }
        }
    }

    /**
     * Fetches a single column from all rows.
     * @param string $column_name
     * @param bool $from_cache Passed by reference - it will be set to TRUE if the data was pulled from the SQL cache
     * @return iterable A singledimensional indexed array containing the column value for each rowCount
     *
     * @throws ResultException if the provided column name is not found in the result
     * @throws RunTimeException
     */
    public function fetchColumn(string $column_name, ?bool &$from_cache = FALSE): iterable
    {
        $this->check_is_executed();
        $from_cache = FALSE;
        $sql = $this->statement->get_sql();

        if ($this->cached_query_data && self::ENABLE_SELECT_CACHING && !$this->is_sql_cache_disabled()) {
            $data = $this->cached_query_data['data'];
            //$found_rows = $this->cached_query_data['found_rows'] ?? NULL;
            $from_cache = TRUE;
        } else {
            $data = $this->statement->fetchAll(pdo::FETCH_MODE);

            if (self::ENABLE_SELECT_CACHING && !$this->is_sql_cache_disabled()) {
                self::$query_cache->add_cached_data($sql, $this->params, $data, NULL);
            }
        }

        //$this->statement->closeCursor();//REMOVE
        $ret = array();
        if (count($data)) {
            foreach ($data as $record) {
                $record = array_change_key_case($record, CASE_LOWER);
                if (!array_key_exists($column_name, $record)) {
                    throw new ResultException(sprintf(t::_('The column named "%s" does not exist in the fetched data.'), $column_name));
                }
                $ret[] = $record[$column_name];
            }
        }

        $ret = $this->connection->execute_fetch_data_processors($ret, $column_name);

        return $ret;
    }

    /**
     * Fetches the first row. If there is an optional $column_name provided will return a single scalar value - the cell from the first row from the provided column.
     * @param string $column_name Optional column name. If it is provided instead to fetch the entire row only the data for the specified column will be returned
     * @param bool $from_cache Passed by reference - it will be set to TRUE if the data was pulled from the SQL cache
     * @return string[]|string Returns a single dimentional array containing all the columns from one row, or if $column_name was provided - the value for that column (or an array with the values of this column in all rows if multiple records are returned instead of one)
     * @throws RunTimeException
     * @throws ResultException
     */
    public function fetchRow(string $column_name = '', ?bool &$from_cache = FALSE) /* mixed*/
    {
        $this->check_is_executed();

        $from_cache = FALSE;

        $sql = $this->statement->get_sql();

        if ($this->cached_query_data && self::ENABLE_SELECT_CACHING && !$this->is_sql_cache_disabled()) {
            $data = $this->cached_query_data['data'];
            //$found_rows = $this->cached_query_data['found_rows'] ?? NULL;
            $from_cache = TRUE;
        } else {
            $data = $this->statement->fetchAll(pdo::FETCH_MODE);

            if (self::ENABLE_SELECT_CACHING && !$this->is_sql_cache_disabled()) {
                self::$query_cache->add_cached_data($sql, $this->params, $data, NULL);
            }

        }

        if (count($data)) {
            $row = array_change_key_case($data[0], CASE_LOWER);
            if ($column_name) {
                if (array_key_exists($column_name, $row)) {
                    $ret = $row[$column_name];
                } else {
                    throw new ResultException(sprintf(t::_('The column named "%s" does not exist in the fetched data.'), $column_name));
                }
            } else {
                $ret = $row;
            }

        } else {
            if ($column_name) {
                $ret = null;
            } else {
                $ret = array();
            }
        }

        $ret = $this->connection->execute_fetch_data_processors($ret);

        return $ret;
    }

    //use_for_pk is no longer used
    //public function fetchAllAsArray($fetch_mode=pdo::FETCH_MODE, $use_for_pk = null) {
    /**
     * Fetches all the data as array (associative or indexed or both - based on the provided $fetch_mode)
     * @param int $fetch_mode The default fetch mode is pdo::FETCH_MODE which is set to \PDO::FETCH_ASSOC
     * @param int $found_rows Passed by reference and will contain the number of found rows if the query is a SELECT with SQL_CALC_FOUND_ROWS
     * @param bool $from_cache Passed by reference - it will be set to TRUE if the data was pulled from the SQL cache
     * @return iterable A twodimensional array - indexed rows and associative columns
     * @throws RunTimeException
     */
    public function fetchAllAsArray(int $fetch_mode = pdo::FETCH_MODE, ?int &$found_rows = 0, ?bool &$from_cache = FALSE): iterable
    {
        $this->check_is_executed();

        $from_cache = FALSE;

        $sql = $this->statement->get_sql();

        if ($this->cached_query_data && self::ENABLE_SELECT_CACHING && !$this->is_sql_cache_disabled()) {

            $return = $this->cached_query_data['data'];
            $found_rows = $this->cached_query_data['found_rows'] ?? NULL;
            $from_cache = TRUE;
        } else {
            $start_time = microtime(TRUE);
            $data = $this->statement->fetchAll($fetch_mode);
            $end_time = microtime(TRUE);
            self::execution_profile()->increment_value('time_fetching_data', $end_time - $start_time);

            /*
             * Added if 0 == $found_rows to return rows, if anyone initialize the variable to zero
             */
            if (stripos($sql, 'SQL_CALC_FOUND_ROWS') !== FALSE && (is_null($found_rows) || 0 == $found_rows)) {
                $found_rows = $this->connection->get_found_rows();

            }

            $count = count($data);
            $return = array();
            for ($aa = 0; $aa < $count; $aa++) {
                $return[$aa] = is_array($data[$aa]) ? array_change_key_case($data[$aa], CASE_LOWER) : $data[$aa];
            }

            if (self::ENABLE_SELECT_CACHING && !$this->is_sql_cache_disabled()) {
                self::$query_cache->add_cached_data($sql, $this->params, $return, $found_rows);
            }
        }
        //postprocessor
        $return = $this->connection->execute_fetch_data_processors($return);

        return $return;
    }

    /**
     * @param array $params
     * @return $this
     * @throws \Guzaba2\Base\Exceptions\NotImplementedException
     */
    public function execute_unbuffered($params = array())
    {
        throw new \Guzaba2\Base\Exceptions\NotImplementedException();
        return $this;
    }

    /**
     * Will return NULL on SELECT statements
     * @return int|NULL
     *
     * @throws RunTimeException
     * @throws Exceptions\SQLParsingException
     * @author vesko@azonmedia.com
     * @created 04.07.2018
     */
    public function getAffectedRowsCount(): ?int
    {
        $this->check_is_executed();
        $ret = NULL;
        $statement_group = $this->getStatementGroup();
        if ($statement_group === self::STATEMENT_GROUP_DQL) {
            //this is a SELECT and this should return NULL. To get the total rows that would have been returned by the query (SQL_CALC_FOUND_ROWS) see self::fetchAll()
            //this is explicitly put here as some databases may have pdoStatement::rowCount() return nonzero even on SELECT
            $ret = NULL;
        } elseif ($statement_group == self::STATEMENT_GROUP_DML) {
            $ret = $this->statement->rowCount();
        }
        return $ret;
    }

    /**
     * Returns the statement type - SELECT, UPDATE,...
     * @return int|NULL
     *
     * @throws Exceptions\SQLParsingException
     * @author vesko@azonmedia.com
     * @created 04.07.2018
     * @see self::STATEMENT_TYPE_MAP
     * Returns NULL if the statement type is not recognized.
     */
    public function getStatementType(): ?int
    {
        $sql = $this->get_sql();
        return statementTypes::getStatementType($sql);
    }

    /**
     * Returns the statement type group - DDL, DML,...
     * @return int|NULL
     *
     * @throws Exceptions\SQLParsingException
     * @author vesko@azonmedia.com
     * @created 04.07.2018
     * @see self::STATEMENT_TYPE_GROUP_MAP
     * Returns NULL is the statement type or group are not recognized.
     */
    public function getStatementGroup(): ?int
    {
        $sql = $this->get_sql();
        return statementTypes::getStatementGroup($sql);
    }

    /**
     * Returns whether this is a Select statement
     * @return bool
     *
     * @throws Exceptions\SQLParsingException
     * @since 0.7.4
     * @created 26.10.2018
     * @author vesko@azonmedia.com
     */
    public function isSelectStatement(): bool
    {
        $sql = $this->get_sql();
        return statementTypes::isSelectStatement($sql);
    }

    /**
     * Returns whether this is a Insert statement
     * @return bool
     *
     * @throws Exceptions\SQLParsingException
     * @since 0.7.4
     * @created 26.10.2018
     * @author vesko@azonmedia.com
     */
    public function isInsertStatement(): bool
    {
        $sql = $this->get_sql();
        return statementTypes::isInsertStatement($sql);
    }

    /**
     * Returns whether this is a Replace statement
     * @return bool
     *
     * @throws Exceptions\SQLParsingException
     * @since 0.7.4
     * @created 26.10.2018
     * @author vesko@azonmedia.com
     */
    public function isReplaceStatement(): bool
    {
        $sql = $this->get_sql();
        return statementTypes::isReplaceStatement($sql);
    }

    /**
     * Returns whether this is a Update statement
     * @return bool
     *
     * @throws Exceptions\SQLParsingException
     * @since 0.7.4
     * @created 26.10.2018
     * @author vesko@azonmedia.com
     */
    public function isUpdateStatement(): bool
    {
        $sql = $this->get_sql();
        return statementTypes::isUpdateStatement($sql);
    }

    /**
     * Returns whether this is a Delete statement
     * @return bool
     *
     * @throws Exceptions\SQLParsingException
     * @since 0.7.4
     * @created 26.10.2018
     * @author vesko@azonmedia.com
     */
    public function isDeleteStatement(): bool
    {
        $sql = $this->get_sql();
        return statementTypes::isDeleteStatement($sql);
    }

    /**
     * Returns whether this is a DQL statement
     * @return bool
     *
     * @throws Exceptions\SQLParsingException
     * @since 0.7.4
     * @created 26.10.2018
     * @author vesko@azonmedia.com
     */
    public function isDQLStatement(): bool
    {
        $sql = $this->get_sql();
        return statementTypes::isDQLStatement($sql);
    }

    /**
     * Returns whether this is a DML statement
     * @return bool
     *
     * @throws Exceptions\SQLParsingException
     * @since 0.7.4
     * @created 26.10.2018
     * @author vesko@azonmedia.com
     */
    public function isDMLStatement(): bool
    {
        $sql = $this->get_sql();
        return statementTypes::isDMLStatement($sql);
    }

    /**
     * Returns whether this is a DDL statement
     * @return bool
     *
     * @throws Exceptions\SQLParsingException
     * @since 0.7.4
     * @created 26.10.2018
     * @author vesko@azonmedia.com
     */
    public function isDDLStatement(): bool
    {
        $sql = $this->get_sql();
        return statementTypes::isDDLStatement($sql);
    }

    /**
     * Returns whether this is a DCL statement
     * @return bool
     *
     * @throws Exceptions\SQLParsingException
     * @since 0.7.4
     * @created 26.10.2018
     * @author vesko@azonmedia.com
     */
    public function isDCLStatement(): bool
    {
        $sql = $this->get_sql();
        return statementTypes::isDCLStatement($sql);
    }

    /**
     * Returns whether this is a DAL statement
     * @return bool
     *
     * @throws Exceptions\SQLParsingException
     * @since 0.7.4
     * @created 26.10.2018
     * @author vesko@azonmedia.com
     */
    public function isDALStatement(): bool
    {
        $sql = $this->get_sql();
        return statementTypes::isDALStatement($sql);
    }

    /**
     * Returns whether this is a TCL statement
     * @return bool
     *
     * @throws Exceptions\SQLParsingException
     * @since 0.7.4
     * @created 26.10.2018
     * @author vesko@azonmedia.com
     */
    public function isTCLStatement(): bool
    {
        $sql = $this->get_sql();
        return statementTypes::isTCLStatement($sql);
    }

    /**
     * @see http://php.net/manual/en/mysqli.reap-async-query.php
     * We need to open a second connection (we cant have two async queries in the same connection - we cant send a second query while the first one is still executing)
     */
    public function execute_in_thread()
    {

    }

    /**
     * @param array $params
     * @param bool $buffered_query
     * @param bool $disable_sql_cache
     * @return PdoStatement
     * @throws Exceptions\SQLParsingException
     * @throws ParameterException
     * @throws QueryException
     * @throws RunTimeException
     * @throws TransactionException
     */
    public function executeAny($params = array(), bool $buffered_query = TRUE, bool $disable_sql_cache = FALSE): self
    {
        $ret = $this->execute($params, $buffered_query, $disable_sql_cache, FALSE);
        return $ret;
    }

    /**
     * A wrapper for the execute method. It is needed because if a PHP error occurs (because of a wrong query) it will be turned into an exception.
     * Works even if the keys of the parameters do nto start with :
     * Allows the execution only of DQL statements
     * @param array $params If the variables are not explicitly bound beforehand here an array with the parameters must be supplied.
     * @param bool $buffered_query
     * @param bool $disable_sql_cache Will disable the SQL cache for this query only (if the SQL caching is enabled in pdoStatement_config.xml.php)
     * @param bool $enforce_DQL_statements_only
     * @return PdoStatement Returns the object pdoStatement so it can be used for method chaining
     * @throws Exceptions\SQLParsingException
     * @throws ParameterException
     * @throws RunTimeException
     * @throws TransactionException
     * @throws QueryException
     * @throws SingleValidationFailedException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     */
    public function execute($params = array(), bool $buffered_query = TRUE, bool $disable_sql_cache = FALSE, bool $enforce_DQL_statements_only = TRUE): self
    {

        $statement_group_type = $this->getStatementGroup();
        $sql = $this->statement->get_sql();


        if (!$statement_group_type) {
            $message = sprintf(t::_('The group type of SQL statement "%s" could not be determined.'), $sql);
            if (Kernel::is_production()) {
                logger::get_instance()->warning($message);
            } else {
                throw new RunTimeException($message);
            }
        }

        $current_transaction = $this->connection->get_current_transaction();
        //we also need to explicitly check for running DB transaction - this is the correct way...
        $current_db_transaction = TransactionManager::getCurrentTransaction(Transaction::class);
        if ($current_transaction || $current_db_transaction) {
            if ($statement_group_type == statementTypes::STATEMENT_GROUP_DDL) {
                $message = sprintf(t::_('There is currently running transaction and a DDL statement was attepted to be executed. This will interrupt the transaction. The DDL statement is: %s'), $sql);
                //throw new framework\transactions\exceptions\transactionException($current_transaction, $message );
                throw new TransactionException($current_transaction, $message);
            }
        }

        $this->connection->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $buffered_query);

        if (count($this->params) && count($params)) {
            $query = $this->getQueryString();
            $params = $this->params;
            //throw new ParameterException('pdoStatement::execute()',sprintf(t::_('Trying to execute a statement with parameters set in both ways - by binding values and by providing parameters array to the execute method. Please use only one of the possible methods!')));
            $param_name = t::_('Wrong way to set params');//not real parameter name
            $message = sprintf(t::_('Trying to execute a statement with parameters set in both ways - by binding values and by providing parameters array to the execute method. Please use only one of the possible methods!'));
            throw new ParameterException($param_name, $message, $query, $params);
        }

        $this->executed = false;

        if (is_array($params) && count($params)) {
            foreach ($params as $key => $value) {
                $this->{$key} = $value;
            }
        }

        $start_time = microtime(true);

        if ($this->profile_enabled || (self::$profile_enabled_slow_execution)) {
            static $cnt_q = 0;
            static $DML_cnt_q = 0;
            static $update_cnt_q = 0;
            if ($this->isDMLStatement()) {
                $DML_cnt_q++;
            }
            if ($this->isUpdateStatement()) {
                $update_cnt_q++;
            }
            $cnt_q++;
        }

        //WORKINPROGRESS
        //queries like UPDATE FROM SELECT do not need special handling as this query type will be recognized as DML query which is not cached but invalidates cache

        //while in transaction DO not use cache - if the caching is enabled the ongoing transaction may/will poison the cache and at the end to get rolled back
        if ($current_transaction || $current_db_transaction) {
            $this->disable_sql_cache = TRUE;
        }

        if ($disable_sql_cache) {
            $this->disable_sql_cache = TRUE;
        }

        if (self::ENABLE_SELECT_CACHING && !$disable_sql_cache) {

            $statement_group_type = $this->getStatementGroup();

            if ($statement_group_type == statementTypes::STATEMENT_GROUP_DQL) {

                $cached_query_data = self::$query_cache->get_cached_data($sql, $this->params);

                if ($cached_query_data) {

                    $this->cached_query_data = $cached_query_data;
                    $this->executed = true;

                    $end_time = microtime(true);
                    if ($statement_group_type) {
                        $type_str = statementTypes::STATEMENT_GROUP_MAP[$statement_group_type];
                        self::execution_profile()->increment_value('cnt_cached_dql_statements', 1);
                        self::execution_profile()->increment_value('time_cached_dql_statements', $end_time - $start_time);
                    }

                    return $this;
                } else {
                    //not found in the cache => proceed
                }

            } elseif ($statement_group_type == statementTypes::STATEMENT_GROUP_DML) {

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

                if (TransactionManager::getCurrentTransaction(Transaction::class) && self::INVALIDATE_SELECT_CACHE_ON_COMMIT) {
                    //do not invalidate it - the current transaction will see live data as we are in transaction (see config) and the rest of the threads can still use the cache as the current transaction is not commited
                    //no point to invalidate the cache if the transaction is not going to be committed
                } else {
                    self::$query_cache->update_tables_modification_microtime($sql, self::UPDATE_QUERY_CACHE_LOCK_TIMEOUT);
                }
            }
        }


        try {
            //PHP documentation is wrong - the default value for $params is null, not an empty array
            $start_time = microtime(true);
            $ret = $this->statement->execute();


            if ($ret === false) {
                $errorInfo = $this->connection->pdo->errorInfo();
                //$str = sprintf(t::_('The following query did not execute correctly:<br /> %s <br /><span class="red">The most probable reason is that invalid type was bound to a variable (like binding boolean to an int).</span>.'), $this->getQueryString());
                $str = t::_('The query did not execute correctly pdoStatement::execute() returned FALSE. <span style="color:red">The most probable reason is that invalid type was bound to a variable (like binding boolean to an int).</span>');
                $debug_params = $this->debugDumpParams();


                $sqlstate = isset($exception->errorInfo[0]) ? $exception->errorInfo[0] : '';
                $errorcode = isset($exception->errorInfo[1]) ? $exception->errorInfo[1] : 0;//driver specific
                $errormessage = isset($exception->errorInfo[2]) ? $exception->errorInfo[2] : $exception->getMessage();
                $query = $this->getQueryString();
                $params = $this->params;
                $debugdata = $this->debugDumpParams();
                throw new QueryException($this, $sqlstate, $errorcode, $errormessage, $query, $params, $debugdata, $exception);
                //throw new QueryException($errorInfo[0], $errorInfo[1], $str, $this->getQueryString(), $debug_params);
            } else {
                //it is OK
            }

        } catch (\PDOException $exception) {


            $sqlstate = isset($exception->errorInfo[0]) ? $exception->errorInfo[0] : '';
            $errorcode = isset($exception->errorInfo[1]) ? $exception->errorInfo[1] : 0;//driver specific
            $errormessage = isset($exception->errorInfo[2]) ? $exception->errorInfo[2] : $exception->getMessage();
            $query = $this->getQueryString();
            $params = $this->params;
            $debugdata = $this->debugDumpParams();

            //$debugdata['params'] = $this->params;

            //k::logtofile('PDOEXCEPTIONS', $exception->getMessage());

            //ERROR 1205 (HY000): Lock wait timeout exceeded; try restarting transaction
            if ($sqlstate == 'HY000') { //Prepared statement needs to be re-prepared
                if ($errorcode == 1205) {
                    //ERROR 1205 (HY000): Lock wait timeout exceeded; try restarting transaction

                    $current_transaction = TransactionManager::getCurrentTransaction(Transaction::class);
                    if ($current_transaction) {
                        //LOG if needed $current_transaction->get_transaction_start_bt_info()

                    }

                    k::logtofile('SQL_QUERY_TAKING_TOO_LONG', $this->getQueryString());
                    k::logtofile_backtrace('SQL_QUERY_TAKING_TOO_LONG');

                    throw new SingleValidationFailedException('transaction', 1, sprintf(t::_('The transaction is taking too much time. Please try to rerun it (just click Save/Submit again).')));
                } elseif ($errorcode == 1615) {
                    //SQLSTATE[HY000]: General error: 1615 Prepared statement needs to be re-prepared 
                    //we can try to execute it again
                    if (!$this->connection->is_repeated_execution) {

                        logger::get_instance()->notice(sprintf(t::_('Mysql needs a statement to be reprepared. The query is "%s".'), $query));

                        $this->connection->is_repeated_execution = true;
                        $query = $this->getQueryString();
                        $this->statement = $this->connection->prepare($query);//replace the contained object \PDOstatement
                        //first get the parameters that were previously bound
                        $params = $this->getParams();
                        //then clear them here as the execute method of this class has a check and the params shouldnt be set both ways (__set() and as array argument)
                        $this->clearParams();
                        //then again execute the statement with the params provided
                        $ret = $this->execute($params);//execute again

                    } else {
                        //throw new framework\database\exceptions\mysql\queryException(sprintf(t::_('The statement needed to be reprepared but failed')));
                        $errormessage .= sprintf(t::_('This is the second execution of the statement.'));
                        throw new QueryException($this, $sqlstate, $errorcode, $errormessage, $query, $params, $debugdata, $exception);
                    }
                } else {
                    throw new QueryException($this, $sqlstate, $errorcode, $errormessage, $query, $params, $debugdata, $exception);
                }
            } elseif ($sqlstate == '40001') { //deadlock
                //TODO in future we can try to rerun the whole transaction
                //throw new framework\orm\exceptions\singleValidationFailedException('transaction',1,sprintf(t::_('The transaction is taking too much time. Please try to rerun it (just click Save/Submit again).')));
                //instead of throwing an exception try to rerun the transaction
                //throw new QueryException($sqlstate,$errorcode,$errormessage,$query,$debugdata,$exception);
                throw new DeadlockException($this, $sqlstate, $errorcode, $errormessage, $query, $params, $debugdata, $exception);
            } else {
                if ($errorcode == '1062') {
                    // duplicate entry
                    //throw new framework\orm\exceptions\duplicateKeyException($errormessage, $errorcode);
                    //throw new framework\database\exceptions\duplicateKeyException($errormessage, $errorcode);
                    throw new DuplicateKeyException($this, $sqlstate, $errorcode, $errormessage, $query, $params, $debugdata, $exception);
                } else if ($errorcode == '1452') {
                    // foreign key constraint
                    //throw new framework\orm\exceptions\foreignKeyConstraintException($errormessage, $errorcode);
                    //throw new framework\database\exceptions\foreignKeyConstraintException($errormessage, $errorcode);
                    throw new ForeignKeyConstraintException($this, $sqlstate, $errorcode, $errormessage, $query, $params, $debugdata, $exception);
                } else {
                    //die(print_r($exception->errorInfo));
                    throw new QueryException($this, $sqlstate, $errorcode, $errormessage, $query, $params, $debugdata, $exception);
                }
            }
        } finally {
            if (self::ENABLE_SELECT_CACHING) {
                if ($statement_group_type == statementTypes::STATEMENT_GROUP_DML) {

                    $current_transaction = TransactionManager::getCurrentTransaction(Transaction::class);

                    if ($current_transaction && self::INVALIDATE_SELECT_CACHE_ON_COMMIT) {
                        //add a callback here TODO
                        //the cache needs to be cleared immediately before the master commit and after it (the actual commit in the DB may take time...)
                        //in fact before the commit we do the same thing like the update - set the date in future so that the cache is disabled until the commit finishes
                        //the commit should succeed within that time
                        //avoid adding calblacks on each transaction - instead add one on the master transaction
                        //@see http://gitlab.guzaba.org/root/guzaba-framework-v0.7/issues/9
                        //the below works but creates a ton of callbacks
                        /*
                        $master_transaction = $current_transaction->get_master_transaction();
                        $master_callback_container = $current_transaction->getCallbackContainer();
                        $master_callback_container->add(
                            function() use ($sql) {
                                self::$query_cache->update_tables_modification_microtime($sql, self::UPDATE_QUERY_CACHE_LOCK_TIMEOUT);
                            },
                            $master_callback_container::MODE_BEFORE_COMMIT,
                            FALSE//do not preserve the context
                        );
                        $master_callback_container->add(
                            function() use ($sql) {
                                self::$query_cache->update_tables_modification_microtime($sql);
                            },
                            $master_callback_container::MODE_AFTER_COMMIT,
                            FALSE//do not preserve the context
                        );
                        */

                        //try to reduce the callbacks by keeping only the modified tables and then execute a single callback that resets their modification times
                        //the callback will be added immediately after a DB transaction is started - @see Transaction::__construct()
                        $master_transaction = $current_transaction->get_master_transaction();
                        $master_transaction_context = $master_transaction->get_context();
                        $tables = self::$query_cache->get_tables_from_sql($sql);
                        $invalidate_tables_for_cache = array_merge($master_transaction_context->invalidate_tables_for_cache ?? [], $tables);
                        $invalidate_tables_for_cache = array_unique($invalidate_tables_for_cache);
                        $master_transaction_context->invalidate_tables_for_cache = $invalidate_tables_for_cache;
                    } else {

                        self::$query_cache->update_tables_modification_microtime($sql);

                    }
                }
            }

            $end_time = microtime(true);
            if ($statement_group_type) {
                $type_str = statementTypes::STATEMENT_GROUP_MAP[$statement_group_type];
                self::execution_profile()->increment_value('cnt_' . strtolower($type_str) . '_statements', 1);
                self::execution_profile()->increment_value('time_' . strtolower($type_str) . '_statements', $end_time - $start_time);
            }
        }

        $end_time = microtime(true);

        if ($this->profile_enabled) {
            $query = $this->getQueryString();
            $params = $this->getParams();
            foreach ($params as $key => $value) {
                $query = str_replace(':' . $key, "'" . $value . "'", $query);//this is not 100% correct!!!
            }

            //k::logtofile('sql_cnt',$cnt_q);
            if ($cnt_q == 1) {
                k::logtofile('sql_profile', ['========' . PHP_EOL . 'unixtime: ' . time() . ' date:' . date('d-m-Y H:i:s') . PHP_EOL . 'request: ' . print_r($_REQUEST, true) . PHP_EOL . 'req URI: ' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'console exec') . PHP_EOL . '========']);
            }
            if ($this->profile_log_caller) {
                $trace = k::simplify_trace(debug_backtrace());
                $trace_str = '';
                foreach ($trace as $frame) {
                    if (isset($frame['file'])) {
                        $trace_str .= $frame['file'] . '#' . $frame['line'];
                    }
                    if (isset($frame['class'])) {
                        $trace_str .= ' ' . $frame['class'] . '::' . $frame['function'] . '()' . PHP_EOL;
                    }
                    //$trace_str .= PHP_EOL;

                }
                k::logtofile('sql_profile', [$trace_str]);
            }
            k::logtofile('sql_profile', [round(round($end_time, 4) - round($start_time, 4), 4) . PHP_EOL . $query]);
            k::logtofile('sql_cnt', [$cnt_q]);

            if ($this->isDMLStatement()) {
                k::logtofile('sql_DML_profile', [round(round($end_time, 4) - round($start_time, 4), 4) . PHP_EOL . $query]);
                k::logtofile('sql_DML_cnt', [$DML_cnt_q]);
            }
            if ($this->isUpdateStatement()) {
                k::logtofile('sql_update_profile', [round(round($end_time, 4) - round($start_time, 4), 4) . PHP_EOL . $query]);
                k::logtofile('sql_update_cnt', [$update_cnt_q]);
            }


            //k::logtofile('sql_profile',round(round($end_time,4)-round($start_time,4),4).PHP_EOL.$this->getQueryString());
        }
        self::$total_sql_time += round($end_time, 4) - round($start_time, 4);


        if ($end_time - $start_time > $this->slow_query_log_time && $this->slow_query_log) {

            $env = ActiveEnvironment::get_instance()->get();
            $should_be_logged = TRUE;
            if ($env) {
                if (framework\operations\classes\operations::is_operation_a_report($env->{c\APP}, $env->{c\P}, $env->{c\C}, $env->{c\A})) {
                    $should_be_logged = FALSE;
                }
            } else {
                //it means it is not yet initialized... log the query in this case
            }

            if ($should_be_logged) {
                $query = $this->getQueryString();
                //k::logtofile('SLOW_QUERY_LOG', ($end_time - $start_time).' '.$query);
                $message = sprintf(t::_('Slow query %s secs (threshold is %s secs): %s'), $end_time - $start_time, $this->slow_query_log_time, $query);
                framework\logger2\classes\logger::get_instance()->debug($message, 'SLOW_QUERY_LOG');
            }

        }


        //this is to be reached only if the mode is PDO::ERRMODE_SILENT which is the default one
        /*
        if ($ret===false) {
            //die(print_r($this->statement->errorInfo()));
            list($sqlstate,$errorcode,$errormsg) = $this->statement->errorInfo();
            //file_put_contents('/home/local/PROJECTS/cms6/db_errors.txt',$errormsg,FILE_APPEND);
            $query = $this->getQueryString();
            $debugdata = $this->debugDumpParams();

            throw new QueryException($sqlstate,$errorcode,$errormsg,$query,$debugdata);
        }
        */

        /*
        $ret = $this->statement->execute($params);

        if (!$ret) {
            list($sqlstate,$errorcode,$errormsg) = $this->statement->errorInfo();
            file_put_contents('/home/local/PROJECTS/cms6/db_errors.txt',$errormsg,FILE_APPEND);
            $query = $this->getQueryString();
            $debugdata = $this->debugDumpParams();
            throw new QueryException($sqlstate,$errorcode,$errormsg,$query,$debugdata);
        }
         */

        $this->executed = true;
        if ($this->profile_enabled || self::$profile_enabled_slow_execution) {
            $this->optimize(round($end_time - $start_time, 4));
        }

        return $this;
    }

    /**
     * Writes the EXPLAIN logs
     *
     */
    protected function optimize($time_delta = 0)
    {
        static $total_sql_time;//this is the total time that the script spent in waiting for SQL execution
        if (is_null($total_sql_time)) {
            $total_sql_time = 0;
        }
        $query = $this->getQueryString();
        $params = $this->getParams();
        foreach ($params as $key => $value) {
            //$query = str_replace(':'.$key,"'".mysql_real_escape_string($value)."'", $query);
            $query = str_replace(':' . $key, "'" . $value . "'", $query);//this is not 100% correct!!!
            //if ($value=='cms\cards\models\card') {
            //if (strstr($query,'main_table.card_id = \'\'')) {
            //    die(debug_print_backtrace());
            //}
        }
        $query = "EXPLAIN (" . $query . ")";
        $db = Connection::get_instance();
        $con = mysqli_connect($db->host, $db->username, $db->password);
        mysqli_select_db($con, $db->database);
        $res = mysqli_query($con, $query);

        $log_to_problems = false;
        if ($res) {
            $str = '<pre>' . $query . '</pre>';
            $str .= '<p>time: ' . $time_delta . '</p>';
            $str .= '<table border="1" cellpadding="3" cellspacing="0"><tr><td>id</td><td>select_type</td><td>table</td><td>type</td><td>possible_keys</td><td>key</td><td>key_len</td><td>ref</td><td>rows</td><td>Extra</td></tr>';
            while ($row = mysqli_fetch_assoc($res)) {

                $str .= '<tr>';
                foreach ($row as $key => $value) {
                    $str .= '<td>' . $value . '</td>';
                    if ($key == 'key' && !$value) {
                        $log_to_problems = true;
                    }
                }
                $str .= '</tr>';
            }
            $str .= '</table><br/><br/>';

            if ($this->profile_enabled) {
                k::logtofile('optimizer_data', $str, 'html');
                if ($log_to_problems) {
                    k::logtofile('optimizer_problems', $str, 'html');
                }
            } else {
                k::get_execution()->add_to_logger($str);
            }

        } else {
            //k::logtofile('optimizer','no explain');
            //die(mysql_error());
            k::logtofile('opt_errors', $query);
            k::logtofile('opt_errors', mysqli_error($con));
        }
    }

    public function fetchAll()
    {
        // TODO: Implement fetchAll() method.
    }

    /**
     * Due to the nature of the pdoStatement class we can not use the constructor to pass as an argument the SQL so instead after the pdoStatement is created we will set the SQL.
     * Another option would be in the constructor to reference a new singleton class that holds the current SQL
     *
     * Used to set the SQL that is bound to this statement when it is being prepared by @see pdo::prepare()
     * @param string
     * @return void
     */
    public function set_sql(string $sql) : void
    {
        $this->sql = $sql;
    }

    /**
     * Returns the SQL of this statement
     * @return string
     */
    public function get_sql() : string
    {
        return $this->sql;
    }
}