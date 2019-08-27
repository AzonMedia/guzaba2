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
 * Class for working with Portable Data Objects (PDO) extension in PHP
 * @category    Guzaba Framework
 * @package        Database
 * @subpackage    PDO
 * @copyright    Copyright (c) Guzaba Ltd - http://guzaba.com
 * @license        http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author        Vesselin Kenashkov <vesko@webstudiobulgaria.com>
 */

namespace Guzaba2\Database;

use Guzaba2\Kernel\Kernel as k;
use org\guzaba\framework\filesystem\classes\paths as p;
use Guzaba2\Translator\Translator as t;
use org\guzaba\framework\helpers\classes\controllerHelpers as h;
use org\guzaba\framework\transactions\classes\transactionManager as TXM;

/**
 * Class pdo
 * @package org\guzaba\framework\database\classes
 * @property string tprefix Contains the table prefix for every table in the database
 */
abstract class Pdo extends Connection
{

    /**
     * The fetchmode is hardcoded to \PDO::FETCH_ASSOC because all the classes are expecting the data in this format
     */
    const FETCH_MODE = \PDO::FETCH_ASSOC;

    const READ_UNCOMMITED = 1;
    const READ_COMMITED = 2;
    const REPEATABLE_READ = 3;
    const SERIALIZABLE = 4;

    const ISOLATION_MAP = [
        self::READ_UNCOMMITED => 'read uncomitted',
        self::READ_COMMITED => 'read committed',
        self::REPEATABLE_READ => 'repeatable read',
        self::SERIALIZABLE => 'serializable',
    ];

    const TRANSACTION_BEGIN = 'BEGIN';
    const TRANSACTION_COMMIT = 'COMMIT';
    const TRANSACTION_ROLLBACK = 'ROLLBACK';

    /**
     * Should the scope reference be used to automatically rollback the transaction
     */
    const DBG_USE_STACK_BASED_ROLLBACK = TRUE;

    /**
     * Should the Transaction Manager be used for the transactions and use ORMTransaction instead of the database\classes\transaction
     *
     */
    const DBG_USE_TXM = TRUE;

    protected $cache_structures = true;
    protected $cache_path = './cache/sql/';
    protected $sql_rewriting_enabled = true;
    protected $sql_rewriting = [
        0 => '\\org\\guzaba\\framework\\orm\\classes\\properties\\dynamicPropertiesSQLRewriting',
    ];
    protected const ENABLE_DATA_PROCESSOR = true;

    /**
     *
     * @var \PDO
     */
    public $pdo;

    /**
     * Is there an active transaction at the moment
     */
    protected $in_transaction_flag = false;

    /**
     * Transaction nesting level
     */
    protected $transactions_nested = 0;

    /**
     *
     * @var array
     */
    protected $transactions_nesting_data = [];

    /**
     * @var null|transaction
     */
    protected $current_transaction = null;

    /**
     * Put here all callbacks that should be executed when the outermost transaction is commited
     * @var array
     */
    protected $transaction_callbacks = [];

    /**
     * To be used by disableRollback & enableRollBack
     */
    protected $transaction_disabled_nested = 0;

    /**
     * The transactino statements will be stored here so it can be restarted if fails (for example because fo a deadlock)
     */
    //protected $transaction_statements = array();

    /**
     * Array with all SQL queries sent. Kept for debugging purpose.
     * This will not be collected if the execution is in batch mode @see kernel::is_batch_mode()
     * @var array
     */
    protected $sql_history = [];

    /**
     * @var array
     */
    protected $sql_transaction_nesting = [];

    /**
     * @var framework\cache\classes\cache
     */
    private static $cache;

    /**
     * Disable rollback. Even if the rollBackAll() method is called it wont rollback the transactions if this flag is enabled.
     * This is used in some situations to disable the rollback when an exceptions is thrown (by default every exception will rollback, but sometimes some exceptions are expected).
     * @see $transaction_disabled_nested (needs nesting)
     */
    //protected $roll_back_all_disabled = false;

    protected $current_transaction_started_by = [];


    /**
     * The callables in this array will be invoked on each and every fetchAllAsArray to process the data
     * @var array
     */
    protected $fetch_data_processors = [];

    abstract public function getTransactionIsolationLevel(): int;

    abstract public function setTransactionIsolationLevel(int $level): void;

    /**
     * Rework it so that cache is a service and it is declared in pdo_config
     * @since 0.7.1
     * @author vesko@azonmedia.com
     * @created 08.03.2018
     */
    public static function _initialize_class(): void
    {
        self::$cache = framework\cache\classes\cache::get_instance();
    }

    /**
     * Disconnects by (attempting to) destroy the \PDO instance.
     * Please note thaat if there are other references to the \PDO instance this will not work!
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->pdo = null;
    }

    /**
     * Checks is it connected - if the \PDO instance exists it is connected
     *
     * @return bool
     */
    public function is_connected(): bool
    {
        return is_object($this->pdo);
    }

    public function get_pdo_connection(): \PDO
    {
        return $this->pdo;
    }

    /**
     * Collects dynamic properties if any in array collection
     * @param array $data_arr - the array parsed from sql parser
     * @param &array $collection - serialized queries containing dynamic properties
     */
    private static function collect_queries_containing_dynamic_props($data_arr, &$collection): void
    {
        foreach ($data_arr as $key => $value) {
            if (array_key_exists('SELECT', $data_arr) && array_key_exists('dynamic_properties', $data_arr)) {
                $serialized = serialize($data_arr);
                if (!in_array($serialized, $collection)) {
                    $collection[] = $serialized;
                }
            } else {
                if (is_array($value)) {
                    self::collect_queries_containing_dynamic_props($value, $collection);
                }
            }
        }
    }

    /**
     * Adds a processor that will be run on the data that was fetched from the database but before being returned by @param string $processor_name A name for the processor that will be used if the processor needs to be removed with @see self::remove_fetch_data_processor()
     * @param callable $processor The actual processor - it must accept an iterable and must return an iterable
     *
     * @see pdoStatement::fetchAllAsArray()
     * @example Example data processor method
     * $processor = function(iterable $data) : iterable { };
     *
     * @author vesko@azonmedia.com
     * @since 0.7.7.1
     * @created 22.03.2019
     */
    public function add_fetch_data_processor(string $processor_name, callable $processor): void
    {
        if (self::ENABLE_DATA_PROCESSOR) {
            if ($this->exists_fetch_data_processor($processor_name)) {
                throw new framework\base\exceptions\runTimeException(sprintf(t::_('There is already fetch data processor with name "%s" registered.'), $processor_name));
            }
            $this->fetch_data_processors[$processor_name] = $processor;
        }
    }

    /**
     * Removes the specified data processor previously added with @param string $processor_name The processor name that was used when adding the processor with @see self::add_fetch_data_processor()
     *
     * @see self::add_fetch_data_processor()
     * @author vesko@azonmedia.com
     * @since 0.7.7.1
     * @created 22.03.2019
     */
    public function remove_fetch_data_processor(string $processor_name): void
    {
        if (!$this->exists_fetch_data_processor($processor_name)) {
            throw new framework\base\exceptions\runTimeException(sprintf(t::_('There is no fetch data processor with name "%s" registered.'), $processor_name));
        }
        unset($this->fetch_data_processors[$processor_name]);
    }

    /**
     * Checks is there a data processor with the provided $processor_name registered (with @param string $processor_name
     * @return bool
     *
     * @see self::add_fetch_data_processor())
     * @author vesko@azonmedia.com
     * @since 0.7.7.1
     * @created 22.03.2019
     */
    public function exists_fetch_data_processor(string $processor_name): bool
    {
        return array_key_exists($processor_name, $this->fetch_data_processors);
    }

    /**
     * Returns all currently registered data processors
     * @return array Returns an associative array with processor_name => processor callable
     *
     * @author vesko@azonmedia.com
     * @since 0.7.7.1
     * @created 22.03.2019
     */
    public function get_fetch_data_processors(): array
    {
        return $this->fetch_data_processors;
    }

    /**
     * Executes all registered data processors on the provided data
     * To be used by @param mixed $data
     * @param string $column_name
     * @return mixed
     *
     * @see pdoStatement::fetchAllAsArray()
     * @author vesko@azonmedia.com
     * @since 0.7.7.1
     * @created 22.03.2019
     */
    public function execute_fetch_data_processors(/* mixed */ $data, $column_name = '')
    {
        foreach ($this->get_fetch_data_processors() as $processor_name => $processor) {
            $data = $processor($data, $column_name);
        }
        return $data;
    }

    /**
     * Creates a prepared statement for the provided SQL.
     *
     * @param string $sql The sql query to be passed to \PDO::prepare
     * @param array $driver_options An optional array with driver options to be passed to \PDO::prepare
     * @return \org\guzaba\framework\database\classes\pdoStatement
     * @throws framework\base\exceptions\runTimeException
     * @throws framework\database\exceptions\queryException
     */
    public function prepare(string $sql, array $driver_options = []): framework\database\interfaces\statement
    {


        //the pdoStatement created here is org\guzaba\framework\database\classes\pdoStatement
        //return new pdoStatement($this->pdo->prepare($sql,$driver_options));

        //$statement = new pdoStatement($this->pdo->prepare($sql,$driver_options));

        /*
        $statement = $this->pdo->prepare($sql,$driver_options);
        if ($statement===false) {
            list($sqlstate,$errorcode,$errormsg) = $this->pdo->errorInfo();
            throw new framework\database\exceptions\queryException($sqlstate,$errorcode,$errormsg,$sql,'');
            //throw new framework\database\exceptions\statementException();
        }
        */

        //to address:
        //SQLSTATE[HY000]: General error: 2014 Cannot execute queries while other unbuffered queries are active.  Consider using PDOStatement::fetchAll().  Alternatively, if your code is only ever going to run against mysql, you may enable query buffering by setting the PDO::MYSQL_ATTR_USE_BUFFERED_QUERY attribute.
        //$driver_options = array(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true);


        //here we must parse the SQL for dynamicProperty
        //VUSHEV


        //VESKO
        //would be nice to activate this parsing only when needed
        //but we must not do something like execute() (AKA with parsing) & execute_direct() (no parsing) because someone may use execute_direct and then another person may add dynamic properties in the query and these will still not get processed
        //another way would be to check who is invoking the query - framework or userland but this is not good because we have to always call debug_backtrace() (it is possible to have generated cache for the code structure but this will take long time to implement)
        //but we can do a quick regex on the looking for dot followed by capitals (as all dynamic properties are in caps)


        //SPEED
        //this will fail to catch the case when there is no table/alias before the property - this will be the case only in the simplest selects
        //this is to be fixed when we have a list of all dynamic properties there are in the code and we search against that list
        //TODO - fix the above
        $parsing_sql_enabled = false;
        if (preg_match('/\.([A-Z_0-9]+)/', $sql)) {
            $parsing_sql_enabled = true;
        }

        if ($parsing_sql_enabled) {

            //require_once './external/PHPSQLParser/php-sql-parser.php';//improve this - move it in the kernel or somewhere else or add namespace so it can be autoload()ed
            require_once(p::$ROOTDIR . '/external/PHPSQLParser/php-sql-parser.php');

            //lets first see do we have a cached object (already parsed query)
            $query_hash = md5($sql);

            p::$PATHS['cache']['sqlparser'] = [];
            p::$PATHS['cache']['sqlparser'][p::CURDIR] = p::$PATHS['cache'][p::CURDIR] . 'sqlparser' . p::DIR;
            p::mkdir_recursive(p::$PATHS['cache']['sqlparser'][p::CURDIR]);
            $cached_file_path = p::$PATHS['cache']['sqlparser'][p::CURDIR] . $query_hash . p::FILE . p::PHP;
            if ($serialized_parser = self::$cache->include_cached_file($cached_file_path)) {
                try {
                    $parser = unserialize(base64_decode($serialized_parser));
                } catch (\Throwable $exception) {
                    //ignore it and proceed with parsing the query
                }
            }

            if (!isset($parser)) {
                //no cached parser was found
                try {
                    $parser = new \PHPSQLParser($sql);
                    //if the parsing succeeded - cache it
                    $cached_contents = '<?php return ' . var_export(base64_encode(serialize($parser)), TRUE) . ';';
                    self::$cache->write_cached_file($cached_file_path, $cached_contents);
                } catch (\Throwable $e) {
                    // do nothing
                    k::logtofile('SQL_PARSER_FAILURES', $e->getMessage()); //NOVERIFY
                }
            }

            //needed by the Dynamic Properties
            $to_remove_as_prop_from_sql = [];
            if (isset($parser)) {
                $dynamic_collection = [];
                self::collect_queries_containing_dynamic_props($parser->parsed, $dynamic_collection);

                $tables = [];
                $dynamic_props = [];

                foreach ($dynamic_collection as $record) {
                    $collection_value = unserialize($record);

                    foreach ($collection_value['dynamic_properties'] as $table => $val) {
                        $table = str_replace($this->tprefix, '', $table);

                        foreach ($val as $prop) {
                            if (preg_match('/\)|\(/', str_replace($this->tprefix, '', $table))) {
                                $table = trim(str_replace($this->tprefix, '', $table), ')(');
                                $to_remove_as_prop_from_sql[$table . '.' . $prop] = $prop;
                            }

                            if (!isset($collection_value['FROM'])) {
                                continue;
                            }

                            if ($table == $prop) {
                                $table = str_replace($this->tprefix, '', $collection_value['FROM'][0]['table']);
                            } elseif (isset($collection_value['FROM'][0]['table']) && $collection_value['FROM'][0]['table'] != $table) {
                                $original_table = str_replace($this->tprefix, '', $collection_value['FROM'][0]['table']);
                            }

                            if (!isset($tables[$table])) {
                                foreach ($collection_value['FROM'] as $opt) {
                                    if (!isset($opt['table'])) {
                                        continue;
                                    }

                                    if (str_replace($this->tprefix, '', $opt['table']) == $table) {
                                        $class = k::get_class_by_table_name($table);

                                        $tables[$table] = $class;
                                        if (!$tables[$table]::dynamic_properties_loaded()) {
                                            //$tables[$table]::load_dynamic_properties_list();no longer needed
                                            $tables[$table]::load_dynamic_properties();
                                        }
                                    } elseif ($opt['alias']['name'] == $table) {
                                        if (isset($original_table)) {
                                            $class = k::get_class_by_table_name($original_table);
                                        } else {
                                            $class = k::get_class_by_table_name($table);
                                        }

                                        $tables[$table] = $class;
                                        if (!$tables[$table]::dynamic_properties_loaded()) {
                                            //$tables[$table]::load_dynamic_properties_list();//no longer needed
                                            $tables[$table]::load_dynamic_properties();
                                        }
                                    }
                                }
                            }

                            if (isset($tables[$table])) {
                                if (!isset($dynamic_props[$table . '.' . $prop])) {
                                    $dynamic_props[$table . '.' . $prop] = $tables[$table]::get_dynamic_property_class($prop);
                                }
                            }
                        } //end foreach dynamic prop
                    } //end foreach dynamic_properties
                }

                //do not check the table before the dot as it may be just an alias not the real name correcponding to any object
                //we have to do a lookup through all classes & dynamic properties
            } //end if

            /*
                        // old one - not checking for all dynamic properties
                        //needed by the Dynamic Properties
                        if (isset($parser) && ! empty($parser->parsed['dynamic_properties'])) {
                            $tables = [];
                            $dynamic_props = [];
                            $to_remove_as_prop_from_sql = [];

                            foreach ($parser->parsed['dynamic_properties'] as $table => $val) {
                                $table = str_replace($this->tprefix, '', $table);

                                foreach ($val as $prop) {
                                    if (preg_match('/\)|\(/', str_replace($this->tprefix, '', $table))) {
                                        $table = trim(str_replace($this->tprefix, '', $table), ')(');
                                        $to_remove_as_prop_from_sql[$table . '.' . $prop] = $prop;
                                    }
                                    if (! isset( $parser->parsed['FROM'])) {
                                        continue;
                                    }
                                    if ($table == $prop) {
                                        $table = str_replace($this->tprefix, '', $parser->parsed['FROM'][0]['table']);
                                    } else if (isset($parser->parsed['FROM'][0]['table']) && $parser->parsed['FROM'][0]['table'] != $table) {
                                        $original_table = str_replace($this->tprefix, '', $parser->parsed['FROM'][0]['table']);
                                    }
                                    if (! isset($tables[$table])) {

                                        foreach ($parser->parsed['FROM'] as $opt) {
                                            if (! isset($opt['table'])) {
                                                continue;
                                            }
                                            if (str_replace($this->tprefix, '', $opt['table']) == $table) {

                                                $class = k::get_class_by_table_name($table);

                                                $tables[$table] = $class;
                                                if (! $tables[$table]::dynamic_properties_loaded()) {

                                                    //$tables[$table]::load_dynamic_properties_list();no longer needed
                                                    $tables[$table]::load_dynamic_properties();
                                                }
                                            } else if ($opt['alias']['name'] == $table) {
                                                if (isset($original_table)) {
                                                    $class = k::get_class_by_table_name($original_table);
                                                } else {
                                                    $class = k::get_class_by_table_name($table);
                                                }
                                                $tables[$table] = $class;
                                                if (! $tables[$table]::dynamic_properties_loaded()) {
                                                    //$tables[$table]::load_dynamic_properties_list();//no longer needed
                                                    $tables[$table]::load_dynamic_properties();
                                                }

                                            }
                                        }
                                    }

                                    if (isset($tables[$table])) {
                                        if (! isset($dynamic_props[$table . '.' . $prop])) {
                                            $dynamic_props[$table . '.' . $prop] = $tables[$table]::get_dynamic_property_class($prop);
                                        }
                                    }

                                } //end foreach ($var as $prop)
                            } //end foeach ($parser->parsed['dynamic_properties'])

                            //do not check the table before the dot as it may be just an alias not the real name correcponding to any object
                            //we have to do a lookup through all classes & dynamic properties
                        } //end if
            */
            if (!empty($dynamic_props)) {
                /** @var framework\orm\classes\activeRecordSingle $object */
                $object = $class::get_instance(0, $OBJECT);
                $all_main_partitions = $object->get_main_partitions();
                $sql_wo_comments = preg_replace('/--.*/', '', $sql);
                $sql_wo_subqueries_and_comments = preg_replace('/\(\s+SELECT[\S\s]*\)\s+AS\s+[\w\d_]+/i', '', $sql_wo_comments);

                unset($all_main_partitions['main_table']);
                // Matching table aliases from the quesry with the respective tables from the db
                $table_aliases = [];
                foreach ($all_main_partitions as $key => $table_name) {
                    preg_match_all("/{$this->tprefix}{$table_name} AS ([\w\d_]+)/i", $sql_wo_comments, $matches);
                    if (!isset($matches[1])) {
                        throw new framework\base\exceptions\runTimeException('Cannot extract aliases from query');
                    }

                    $unique_matches = array_unique($matches[1]);
                    if (count($unique_matches) == 1) {
                        $table_aliases[$key] = reset($unique_matches);
                        continue;
                    }

                    preg_match_all("/{$this->tprefix}{$table_name} AS ([\w\d_]+)/i", $sql_wo_subqueries_and_comments, $matches);
                    if (!isset($matches[1]) || empty($matches[1])) {
                        throw new framework\base\exceptions\runTimeException('Cannot extract aliases from query');
                    }

                    $unique_matches = array_unique($matches[1]);
                    if (count($unique_matches) > 1) {
                        throw new framework\base\exceptions\runTimeException('Cannot determine the right alias from query');
                    }

                    $table_aliases[$key] = reset($unique_matches);
                }

                foreach ($dynamic_props as $search => $c) {
                    //var_dump($to_remove_as_prop_from_sql, $search, $c, $sql);
                    if (preg_match("/\b{$search}\b/", $sql)) {
                        if (isset($to_remove_as_prop_from_sql[$search])) {
                            $sql = str_replace($search, str_replace("AS $to_remove_as_prop_from_sql[$search]", "", $c::get_sql(preg_replace('/\..*$/', '', $search), $table_aliases)), $sql);
                        } else {
                            $main_table = preg_replace('/\..*$/', '', $search);
                            $sql = str_replace($search, $c::get_sql($main_table, $table_aliases), $sql);
                        }
                    } else {
                        if (isset($to_remove_as_prop_from_sql[$search])) {
                            $sql = str_replace(preg_replace('/.*\./', '', $search), str_replace("AS $to_remove_as_prop_from_sql[$search]", "", $c::get_sql(preg_replace('/\..*$/', '', $search))), $sql);
                        } else {
                            $sql = str_replace(preg_replace('/.*\./', '', $search), $c::get_sql(preg_replace('/\..*$/', '', $search)), $sql);
                        }
                    }
                }
                $sql = preg_replace('/,\s+\bFROM\b/is', ' FROM', $sql);
            }
        }


        //vesko
        //the best would be not to use the SQL parser but just do a regex
        //but we need to know the alias because the dynamic property uses the main table which is imported with an alias
        //if (preg_match('/\.([A-Z]_[0-9])/', $sql, $matches)) {
        //if (preg_match('/\.([A-Z_0-9]+)/', $sql, $matches)) {

        //}

        if (!k::is_batch_mode()) {
            $this->sql_history[] = $sql;
        }

        try {
            //PHP documentation is wrong - the default value for $params is null, not an empty array
            //$statement = $this->pdo->prepare($sql,$driver_options);//this creates \pdoStatement
            //
            //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            //!!!!!! if we pass by reference objects then reassigning one of the references to another object changes the object !!!!!!
            //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            //$a = new c(); $b =& $a; $b = new z();//changes also $a... in a very unexpected places
            //because of this (and for better clearance) we rename it
            //$php_pdo_statement =& $this->pdo->prepare($sql,$driver_options);//this creates \pdoStatement
            /** @var pdoStatementExtended $php_pdo_statement */
            $php_pdo_statement = $this->pdo->prepare($sql, $driver_options);//this creates \pdoStatement //PHP7.1 (removed reference)
        } catch (\PDOException $exception) {
            /*
            //the exception has to be caught and thrown again as the \PDOException does not extend the baseException
            $sqlstate = isset($exception->errorInfo[0])?$exception->errorInfo[0]:'';
            $errorcode = isset($exception->errorInfo[1])?$exception->errorInfo[1]:'';//driver specific
            $errormessage = isset($exception->errorInfo[2])?$exception->errorInfo[2]:'';
            k::logtofile('sql_errors',$sql);
            //throw new framework\database\exceptions\statementException($sqlstate,$errorcode,$errormessage,$sql,$debugdata='',$exception);
            throw new framework\database\exceptions\queryException($sqlstate,$errorcode,$errormessage,$sql,$debugdata='',$exception);
            */

            $sqlstate = isset($exception->errorInfo[0]) ? $exception->errorInfo[0] : '';
            $errorcode = isset($exception->errorInfo[1]) ? $exception->errorInfo[1] : 0;//driver specific
            $errormessage = isset($exception->errorInfo[2]) ? $exception->errorInfo[2] : $exception->getMessage();
            $query = $sql;
            $params = [];//we dont have the parameters here - we are just preparing it
            //an error can still occur here if for example there is wrong column name
            //$debugdata = $this->debugDumpParams();
            $debugdata = '';

            throw new framework\database\exceptions\queryException(NULL, $sqlstate, $errorcode, $errormessage, $query, $params, $debugdata, $exception);
        }

        $php_pdo_statement->set_sql($sql);

        $statement = new pdoStatement($php_pdo_statement, $this, $this->current_transaction);

        /*
        $statement = $this->pdo->prepare($sql,$driver_options);
        if ($statement===false) {
            list($sqlstate,$errorcode,$errormsg) = $this->statement->errorInfo();
            throw new framework\database\exceptions\queryException($sqlstate,$errorcode,$errormsg,$sql,'');
        }
        $stement = new pdoStatement($statement);
        */
        //$this->transaction_statements[] = $statement;
        return $statement;
    }

    /**
     * A quick way to execute a statement without explicitly creating a statement object and binding values/variables
     * @param string $sql Sql query with placeholders. It can be without placeholders (with values already excaped and put) and in this case there is no need to supply parameters as a second argument
     * @param array $parameters Parameters for the placeholders in the sql
     * @param bool $buffered_query
     * @param bool $disable_sql_cache
     * @param bool $enforce_DQL_statements_only
     * @return \org\guzaba\framework\database\classes\pdoStatement The result from the statement object can be fetched into twodimentional array with fetchAll or fetchRow
     * @throws framework\base\exceptions\runTimeException
     * @throws framework\database\exceptions\deadlockException
     * @throws framework\database\exceptions\duplicateKeyException
     * @throws framework\database\exceptions\foreignKeyConstraintException
     * @throws framework\database\exceptions\parameterException
     * @throws framework\database\exceptions\queryException
     * @throws framework\orm\exceptions\singleValidationFailedException
     * @throws framework\transactions\exceptions\transactionException
     */
    public function execute(string $sql, array $parameters = [], bool $buffered_query = TRUE, bool $disable_sql_cache = FALSE, bool $enforce_DQL_statements_only = TRUE): framework\database\classes\pdoStatement
    {


        //TODO
        //would be nice of possible to avoid the SQL parser and even its caching in the prepare()
        //first and foremost check is this a cached query
        //if it is switch to emulated prepare to avoid hitting the server
        //but if it is not it should never work in emulated as in this case the returned data is all of tring type
        // if (pdoStatement::ENABLE_SELECT_CACHING) {

        //     $statement_group_type = statementTypes::getStatementGroup($sql);

        //     if ($statement_group_type == statementTypes::STATEMENT_GROUP_DQL) {

        //         $cached_query_data = queryCache::get_instance()->get_cached_data($sql, $parameters);

        //         if ($cached_query_data) {
        //             //then set the statement type to emulated prepare to save a query to theserver
        //             $this->setAttribute(\PDO::ATTR_EMULATE_PREPARES, TRUE);
        //         } else {
        //             $this->setAttribute(\PDO::ATTR_EMULATE_PREPARES, FALSE);
        //         }

        //     }

        // }
        //the above doesnt help must because most of the statements are created with $this->prepare() instead of being created and executed immediately with $this->execute()
        //and at the time of the statement preparation we dont know yet is the query cached or not as we dont have the parameters
        //we can cache at this stage the column types


        //if (pdoStatement::ENABLE_SELECT_CACHING) {
        //    $query_cache = queryCache::get_instance();
        //    //ATTR_EMULATE_PREPARES//perhaps emulate the statement so that ... or there is cache
        //} else {
        $statement = $this->prepare($sql);

        //$this->setAttribute(\PDO::ATTR_EMULATE_PREPARES, FALSE);

        $statement->setFetchMode(self::FETCH_MODE);
        //$this->transaction_statements[] = $statement;
        //try {
        //$statement->execute($parameters);
        $statement->execute($parameters, $buffered_query, $disable_sql_cache, $enforce_DQL_statements_only);
        //} catch (\Exception $exception) {
        //} catch (framework\database\exceptions\queryException $exception) {
        //if ($exception->getErrorCode()==1213) { //1213 deadlock for mysql
        //restart the transaction
        //this error occurs when there are multiple INSERT statements (autoincrement is not used)
        //to avoid it lock tables is invoked before any insert operation (when a new object is created)
        //for the anonymous user when loads the first page would be much better not t create immedately a cart (to avoid locking), but instead create a cart when it is needed - deffer it
        //the loggers also require locks... or much better - at least for them use autoincrement (loggers dont use ORM so it is easy to do it)
        //there is a difference between SERIALIZABLE and REPEATABLE_READ - in repeatable read is possible to have the same ID for the inserts, while in SERIALIZABLE dead locks occurs
        //when locking the tables for insert it is enough to lock only the master table (which contains the master index) - main_table - the rest use the generated index
        //is autoincrement is to be used then the problem with the generated indexes remains - for the version index (there can be only one autoincrement column per table). Of course is a much more rare case but the lock on the tables for insertion (for versioned objects means for every update) has to remain
        //}
        //}
        //static $cnt;
        //if (!$cnt) {
        //    $cnt = 0;
        //}
        //$cnt++;
        //k::logtofile('query_count', $cnt);
        //k::logtofile('transaction_debug', $this->inTransaction().' '.$sql);
        //}

        return $statement;
        //return $statement->fetchAll();
    }

    public function executeAny(string $sql, array $parameters = [], bool $buffered_query = TRUE, bool $disable_sql_cache = FALSE): framework\database\classes\pdoStatement
    {
        $ret = $this->execute($sql, $parameters, $buffered_query, $disable_sql_cache, FALSE);

        return $ret;
    }

    /**
     * Returns the PDO driver around which this class is wrapping around
     * @return \PDO
     */
    public function get_driver(): \PDO
    {
        return $this->pdo;
    }

    /**
     * Executes the provided code in a transaction and if no exception is thrown during the execution of the code returns the transaction
     * The thrown exception will have getInterruptedTransaction() method to retrieve the transaction
     * (the other option would be to catch all exceptions here and throw a transactionException with the caught exception provided. Then the transaction will be available with getTransaction but it will be better not to change the expected type of exception - basically replacing the thrown exception)
     * @param callable $code
     * @param string $commit_callback
     * @param string $rollback_callback
     * @return transaction
     * @throws framework\base\exceptions\invalidArgumentException
     * @throws framework\base\exceptions\notImplementedException
     * @throws framework\base\exceptions\runTimeException
     */
    public function &executeInTransaction($code, &$commit_callback = '', &$rollback_callback = '')
    {
        if (!is_callable($code)) {
            throw new framework\base\exceptions\invalidArgumentException(sprintf(t::_('The first argument provided to pdo::executeInTransaction() must be a callable. %s was provided instead.'), gettype($code)));
        }

        if (self::DBG_USE_TXM) {
            throw new framework\base\exceptions\notImplementedException();
        }

        //$transaction =& $this->beginTransaction($TR, $commit_callback, $rollback_callback);
        //$transaction->set_code($code);
        //$code();
        //$transaction->commit();
        //or $this->commit($TR);

        $transaction = new transaction($code, $commit_callback, $rollback_callback);
        return $transaction;
    }

    /**
     * This method is the same like beginTransaction but ensures taht the transaction we are about the begin is the master one.
     * If there is already a running transaction an exception will be thrown.
     * beginMasterTransaction should be used in processing multiple records (example cronjob) when we want to ensure no another master transaction encompasses the whole block of processed records and locks them all.
     * @param string|framework\transactions\classes\scopeReferenceTracker $scope_reference
     * @param string $commit_callback
     * @param string $rollback_callback
     * @return transaction|framework\transactions\classes\transaction|void
     * @throws framework\base\exceptions\invalidArgumentException
     * @throws framework\base\exceptions\runTimeException
     * @throws framework\database\exceptions\transactionException
     * @see beginTransaction()
     */
    public function &beginMasterTransaction(&$scope_reference = '&', &$commit_callback = '', &$rollback_callback = '')
    {
        if (self::DBG_USE_TXM) {
            //return TXM::beginMasterORMTx($scope_reference, $commit_callback, $rollback_callback);
            $callback_container = new framework\transactions\classes\callbackContainer();

            if ($commit_callback && !($commit_callback instanceof framework\transactions\classes\callbackContainer)) {
                $callback_container->add(
                    $commit_callback,
                    //$callback_container::MODE_ON_COMMIT_AFTER_MASTER
                    $callback_container::DEFAULT_COMMIT_MODE
                );
            }
            $commit_callback = $callback_container;//replace the container - this is needed for the reference in the method signature


            //if there is a commit callback provided that is not of type callbackcontainer
            if ($rollback_callback && !($rollback_callback instanceof framework\transactions\classes\callbackContainer)) {
                $callback_container->add(
                    $rollback_callback,
                    $callback_container::DEFAULT_ROLLBACK_MODE
                );
            }
            $rollback_callback = $callback_container;//replace the container - this is needed for the reference in the method signature

            $transaction = TXM::beginTransaction(framework\orm\classes\ORMDBTransaction::class, $scope_reference, $callback_container);
            return $transaction;
        } else {
            if ($this->current_transaction) {
                //TODO - track where the transaction was started and report this in this exception
                throw new framework\database\exceptions\transactionException($this->current_transaction, sprintf(t::_('A new master transaction can not be started because there is already running transaction.')));
            }
            return $this->beginTransaction($scope_reference, $commit_callback, $rollback_callback);
        }
    }

    /**
     * Begins a transaction
     *
     * @param framework\patterns\classes\scopeReference|null $scope_reference it is vital this to be passed by reference - do not remove the &
     * @param callback $commit_callback
     * @param callback $rollback_callback
     * @return \org\guzaba\framework\database\classes\transaction
     * @throws framework\base\exceptions\deprecatedException
     * @throws framework\base\exceptions\invalidArgumentException
     * @throws framework\base\exceptions\runTimeException
     * @throws framework\database\exceptions\transactionException
     */
    public function beginTransaction(?framework\patterns\classes\scopeReference &$scope_reference = NULL, &$commit_callback = null, &$rollback_callback = null)
    {
        //the below is the correct one when the TXM is used
        //public function beginTransaction(?framework\patterns\classes\scopeReference &$scope_reference = NULL, ?callbackContainer &$commit_callback = NULL, ?callbackContainer &$rollback_callback = NULL) {

        if ($this->getOptionValue('disable_transactions')) {
            //throw new framework\base\exceptions\runTimeException(sprintf(t::_('The connection %s does not allow starting a transction as these are disabled in its config file.'), get_class($this) ));
        }

        if (self::DBG_USE_TXM) {

            //if there is a commit callback provided that is not of type callbackcontainer
            $callback_container = new framework\transactions\classes\callbackContainer();

            if ($commit_callback && !($commit_callback instanceof framework\transactions\classes\callbackContainer)) {
                $callback_container->add(
                    $commit_callback,
                    //$callback_container::MODE_ON_COMMIT_AFTER_MASTER
                    $callback_container::DEFAULT_COMMIT_MODE
                );
            }
            $commit_callback = $callback_container;//replace the container - this is needed for the reference in the method signature


            //if there is a commit callback provided that is not of type callbackcontainer
            if ($rollback_callback && !($rollback_callback instanceof framework\transactions\classes\callbackContainer)) {
                $callback_container->add(
                    $rollback_callback,
                    //$callback_container::MODE_ON_ROLLBACK_AFTER_MASTER
                    $callback_container::DEFAULT_ROLLBACK_MODE
                );
            }
            $rollback_callback = $callback_container;//replace the container - this is needed for the reference in the method signature

            //$transaction = TXM::beginORMTx($scope_reference, $commit_callback, $rollback_callback);
            $options['connection'] = $this;
            $transaction = TXM::beginTransaction(framework\orm\classes\ORMDBTransaction::class, $scope_reference, $callback_container, $options);
        } else {
            if ($commit_callback === '') {
                $commit_callback = null;
            } elseif ($commit_callback === null) { //means an empty varialbe was passed - we want a reference to the callback comtainer
                $commit_callback = new framework\database\classes\callbackContainer([], framework\database\classes\callbackContainer::TYPE_COMMIT);
            //$commit_callback->set_container_type($commit_callback::TYPE_COMMIT);
            } else {
                //this may be a valid callback - this will be checked by transaction::__construct()
                //throw new framework\base\exceptions\runTimeException(sprintf(t::_('An unsupported type %s was passed as $commit_callback to pdo::beginTransaction().'), gettype($commit_callback) ));
            }


            if ($rollback_callback === '') {
                $rollback_callback = null;
            } elseif ($rollback_callback === null) { //means an empty varialbe was passed - we want a reference to the callback comtainer
                $rollback_callback = new framework\database\classes\callbackContainer([], framework\database\classes\callbackContainer::TYPE_ROLLBACK);
            //$rollback_callback->set_container_type($rollback_callback::TYPE_ROLLBACK);
            } else {
                //this may be a valid callback - this will be checked by transaction::__construct()
                //throw new framework\base\exceptions\runTimeException(sprintf(t::_('An unsupported type %s was passed as $rollback_callback to pdo::beginTransaction().'), gettype($rollback_callback)  ));
            }


            /*
            $this->sql_history[] = "START TRANSACTION";
            if ($this->transactions_nested==0) {
                //$backtrace = k::get_backtrace();
                //$this->current_transaction_started_by = $backtrace;
                $frame = k::get_stack_frame_by(self::_class, __FUNCTION__);
                if (isset($frame['file']) && isset($frame['line'])) {
                    $this->current_transaction_started_by = $frame['file'].'#'.$frame['line'];
                } else {
                    $this->current_transaction_started_by =  ' Unknown';
                }
                //$this->pdo->beginTransaction();//this is hidden in the transaction class
            } else {
                //$this->pdo->createSavepoint();//we need to extend the PHP PDO class... but unfortunately we already have a class named pdo here
                //so instead this will be implemented here
                //$q = "SAVEPOINT";//this is hidden in the transaction class
                //$this->pdo->execute();
            }
            */

            /*
            $caller = $this->_get_caller();
            $this->sql_transaction_nesting[] = array(
                'nesting'=>$this->transactions_nested,
                'type'=>self::TRANSACTION_BEGIN,
                'caller'=>array(
                    'file'=>isset($caller['file'])?$caller['file']:'',
                    'line'=>isset($caller['line'])?$caller['line']:'',
                    'class'=>isset($caller['class'])?$caller['class']:'',
                    'function'=>isset($caller['function'])?$caller['function']:''
                )
            );

            if (!isset($this->transactions_nesting_data[$this->transactions_nested])) {
                $this->transactions_nesting_data[$this->transactions_nested] = array();
            }
            */

            if ($scope_reference !== null && !($scope_reference instanceof framework\database\classes\scopeReferenceTransactionTracker) && $scope_reference != '&') {
                $type = is_object($scope_reference) ? get_class($scope_reference) : gettype($scope_reference);
                throw new framework\base\exceptions\runTimeException(sprintf(t::_('%s() expects for first argument scopeReferenceTransactionTracker or NULL. Instead "%s" was provided.'), __METHOD__, $type));
            }


            if ($scope_reference instanceof framework\database\classes\scopeReferenceTransactionTracker) {


                //$message = sprintf(t::_('Transaction was started again within the same scope without explicitly rolling back or commiting the previous one. This is not allowed by the framework.'));
                //throw new framework\database\exceptions\transactionException($scope_reference->get_transaction(), $message);

                if (framework\database\classes\pdo::DBG_USE_STACK_BASED_ROLLBACK) {
                    $scope_reference->set_destruction_reason($scope_reference::DESTRUCTION_REASON_OVERWRITING);
                    $scope_reference = null;//trigger rollback (and actually destroy the transaction object)
                } else {
                    $message = sprintf(t::_('Transaction was started again within the same scope without explicitly rolling back or commiting the previous one. This is not allowed by the framework.'));
                    throw new framework\database\exceptions\transactionException($scope_reference->get_transaction(), $message);
                }

                /*
                //if on a begintransaction() is provided an existing reference this means calling begin without first calling rollback or commit in a cycle (in the same scope)
                //this will also protect us from calling twice transactionBegin in the same scope without having a cycle - just by accident

                //before we trigger the rollback we clone the objcet for debug purpose (AKA throwing the exception - we need to provide the transaction there)
                //we clone the transaction before it gets rolled back - in its original status
                $cloned_transaction = clone $scope_reference->get_transaction();

                $scope_reference = null;//trigger rollback (and actually destroy the transaction object)
                //k::logtofile('TRANSACTION_PROBLEM','Transaction was started again within the same scope without explicitly rolling back or commiting the previous one. This is not allowed by the framework.');
                $message = sprintf(t::_('Transaction was started again within the same scope without explicitly rolling back or commiting the previous one. This is not allowed by the framework.'));

                throw new framework\database\exceptions\transactionException($cloned_transaction, $message);
                 */
            }

            //Lets allow new transactions in the callbacks (uncomment to disable)
            //if ($this->current_transaction && $this->current_transaction->is_in_callback() ) {
            //throw new framework\database\exceptions\transactionException(sprintf(t::_('You can not start a new transaction while in a callback (rolblackCallback or commitCallback).')));
            //}


            //$transaction = new transaction($this->pdo, $this->current_transaction, $commit_callback, $rollback_callback);
            //$transaction = new transaction($this, $this->current_transaction, $commit_callback, $rollback_callback);
            //changed constructor construct - the first argument is the code that we want to run
            $options = [];
            $transaction = new transaction(null, $commit_callback, $rollback_callback, $options, $this, $this->current_transaction);
            //$this->current_transaction =& $transaction;//the transaction itself registers in current_transaction

            $this->current_transaction =& $transaction;//the transaction itself registers in current_transaction
            //k::logtofile('transaction_nesting',transaction::get_transactions_nesting($this->current_transaction));

            if ($scope_reference == '&') {
                //dont do anything - no argument was provided
            } elseif ($scope_reference === null) {
                //a new reference is requested
                $scope_reference = new framework\database\classes\scopeReferenceTransactionTracker($transaction);
            } else {
                //shouldnt be possible
            }

            /*


            $this->transactions_nesting_data[$this->transactions_nested][] = array('scope_reference'=>$scope_reference, 'callback'=>&$callback);

            if ($callback && is_callable($callback)) {
                $this->transaction_callbacks[] =& $callback;//this will get destroyed if there is rollback and only a null reference will remain
            }

            $this->transactions_nested++;
            */
        }

        return $transaction;
    }

    /**
     *
     * @param framework\patterns\classes\scopeReference $scope_reference
     * @return bool
     * @throws framework\base\exceptions\runTimeException
     * @throws framework\database\exceptions\transactionException
     * @throws framework\transactions\exceptions\transactionException
     */
    public function rollBack(scopeReference &$scope_reference)
    {
        if (self::DBG_USE_TXM) {
            TXM::rollback($scope_reference);
        } else {

            /*
            $last_nested_data = array_pop($this->transactions_nesting_data[$this->transactions_nested]);
            if ($scope_reference && $scope_reference instanceof framework\database\classes\scopeReferenceTransactionTracker) {
                //look for the matching reference
                //this must be the reference from the last started begin
                if ($last_nested_data['scope_reference'] && $last_nested_data['scope_reference']===$scope_reference) { //When using the identity operator (===), object variables are identical if and only if they refer to the same instance of the same class.
                    //this is OK
                    //but we need to remove the registered callback
                    $last_nested_data['callback'] = null;//there is only one reference to this callback and this should destroy the reference kept in the transaction_callbacks array
                } else {
                    throw new framework\database\exceptions\transactionException(sprintf(t::_('There is rollBack() invoked without beginTransaction() being called before that from the same scope.')));
                }
            }
            $this->current_transaction = $this->current_transaction->get_parent_transaction();

            $this->sql_history[] = "ROLLBACK";
            if ($this->transactions_nested==1) {

                k::logtofile_backtrace('roll_bt');
                $this->pdo->rollBack();

                //flush these - this is the end of the transaction
                $this->transactions_nesting_data = array();
                $this->transaction_callbacks = array();
            }




            $this->transactions_nested--;

            $caller = $this->_get_caller();
            $this->sql_transaction_nesting[] = array(
                'nesting'=>$this->transactions_nested,
                'type'=>self::TRANSACTION_ROLLBACK,
                'caller'=>array(
                    'file'=>isset($caller['file'])?$caller['file']:'',
                    'line'=>isset($caller['line'])?$caller['line']:'',
                    'class'=>isset($caller['class'])?$caller['class']:'',
                    'function'=>isset($caller['function'])?$caller['function']:''
                )
            );
            */

            if ($scope_reference !== null && !($scope_reference instanceof framework\database\classes\scopeReferenceTransactionTracker) && $scope_reference != '&') {
                throw new framework\base\exceptions\runTimeException(sprintf(t::_('%s::%s expects for first argument scopeReferenceTransactionTracker or NULL.'), __CLASS__, __METHOD__));
            }

            if ($scope_reference instanceof framework\database\classes\scopeReferenceTransactionTracker) {
                $scope_transaction = $scope_reference->get_transaction();
                if ($scope_transaction != $this->current_transaction) {
                    $transaction_info_object = $scope_transaction->get_transaction_start_bt_info();
                    $current_transaction_info_object = $this->current_transaction->get_transaction_start_bt_info();
                    if ($transaction_info_object && $current_transaction_info_object) {
                        k::logtofile('TRANSACTION_ERRORS', 'scope reference transaction started at: ' . PHP_EOL . print_r($transaction_info_object->getTrace(), TRUE) . PHP_EOL . PHP_EOL . 'current transaction started at: ' . PHP_EOL . print_r($transaction_info_object->getTrace(), TRUE));//NOVERIFY
                    } else {
                        k::logtofile('TRANSACTION_ERRORS', 'no backtrace info object available for the transactions');//NOVERIFY
                    }
                    $message = sprintf(t::_('It appears that you are trying to commit a transaction that is a different from the current one. To see more details about this abnormal condition please see TRANSACTION_ERRORS.txt log.'));

                    throw new framework\database\exceptions\transactionException($scope_transaction, $message);
                } else {
                    //the scope reference will be destroyed further down after the explicit rollback has taken place
                }
            }

            if (!$this->current_transaction) {
                //$message = sprintf(t::_('Trying to rollback without having currently running transaction.'));
                //throw new framework\database\exceptions\transactionException();
                //we dont have a  transaction here
                //throw new framework\base\exceptions\runTimeException($message);
                //silently ignore this... 9as it may be called from anothe exception


                $message = sprintf(t::_('It appears you are trying to commit a transaction that was never started or it was all rolled back. There is no transaction running currently.'));
                throw new framework\database\exceptions\transactionException($transaction = null, $message);
            } else {
                $this->current_transaction->rollback();
            }

            /*
            //this must be done in transaction::rollback() because this code mustb e executed immediately after the rolblack in DB but BEFORE the callbacks
            if ($this->current_transaction && $this->current_transaction->has_parent()) {
                $this->current_transaction =& $this->current_transaction->get_parent();
            } else {
                $this->current_transaction->destroy();//this is just in case - destroys at least the nested ones... but as of the time of adding the transaction class the references are correct and the line below destroyes everything as expected
                $this->current_transaction = null;
            }
            */

            if ($scope_reference instanceof framework\database\classes\scopeReferenceTransactionTracker) {
                $scope_reference->set_destruction_reason($scope_reference::DESTRUCTION_REASON_EXPLICIT);
                $scope_reference->rollback_on_destory = false;//everything is OK with the transaction... do not roll it back (again...)
                $scope_reference = null;
            } elseif ($scope_reference) {
                k::logtofile_backtrace('scope_ref_not_correct');
            }
        }

        return true;
    }

    /**
     *
     * @param framework\database\classes\scopeReferenceTransactionTracker|null $scope_reference
     * @param callback|null $callback
     * @return bool
     * @throws framework\base\exceptions\runTimeException
     * @throws framework\database\exceptions\transactionException
     */
    //public function commit(&$scope_reference = '&', $callback = null) {
    public function commit(scopeReference &$scope_reference)
    {
        if (self::DBG_USE_TXM) {
            TXM::commit($scope_reference);
        } else {

            /*
            $caller = $this->_get_caller();
            $this->sql_history[] = "COMMIT";
            if ($this->transactions_nested==1) {
                $this->pdo->commit();
            }

            $this->transactions_nested--;

            $this->sql_transaction_nesting[] = array(
                'nesting'=>$this->transactions_nested,
                'type'=>self::TRANSACTION_COMMIT,
                'caller'=>array(
                    'file'=>isset($caller['file'])?$caller['file']:'',
                    'line'=>isset($caller['line'])?$caller['line']:'',
                    'class'=>isset($caller['class'])?$caller['class']:'',
                    'function'=>isset($caller['function'])?$caller['function']:''
                )
            );
            */

            if ($scope_reference !== null && !($scope_reference instanceof framework\database\classes\scopeReferenceTransactionTracker) && $scope_reference != '&') {
                throw new framework\base\exceptions\runTimeException(sprintf(t::_('%s::%s expects for first argument scopeReferenceTransactionTracker or NULL.'), __CLASS__, __METHOD__));
            }

            if ($scope_reference instanceof framework\database\classes\scopeReferenceTransactionTracker) {
                $scope_transaction = $scope_reference->get_transaction();
                if ($scope_transaction != $this->current_transaction) {
                    $transaction_info_object = $scope_transaction->get_transaction_start_bt_info();
                    $current_transaction_info_object = $this->current_transaction->get_transaction_start_bt_info();
                    if ($transaction_info_object && $current_transaction_info_object) {
                        k::logtofile('TRANSACTION_ERRORS', 'scope reference transaction started at: ' . PHP_EOL . print_r($transaction_info_object->getTrace(), TRUE) . PHP_EOL . PHP_EOL . 'current transaction started at: ' . PHP_EOL . print_r($transaction_info_object->getTrace(), TRUE));//NOVERIFY
                    } else {
                        k::logtofile('TRANSACTION_ERRORS', 'no backtrace info object available for the transactions');//NOVERIFY
                    }
                    $message = sprintf(t::_('It appears that you are trying to commit a transaction that is a different from the current one. To see more details about this abnormal condition please see TRANSACTION_ERRORS.txt log.'));
                    throw new framework\database\exceptions\transactionException($scope_transaction, $message);
                } else {
                    //the scope reference will be destroyed further down after the explicit commit has taken place
                }
            }

            if ($scope_reference === null) {
            }

            //k::logtofile_indent('transactions.txt',gettype($this->current_transaction));

            if (!$this->current_transaction) {
                $message = sprintf(t::_('It appears you are trying to commit a transaction that was never started or it was all rolled back. There is no transaction running currently.'));
                $transaction = null;
                throw new framework\database\exceptions\transactionException($transaction, $message);
            }


            $this->current_transaction->commit();


            //this cant be here - it must be AFTER the commit in the DB but BEFORE
            /*
            if ($this->current_transaction->has_parent()) {
                $this->current_transaction =& $this->current_transaction->get_parent();
            } else {
                //print 'DESTROYING TRANSACTION'.PHP_EOL;
                $this->current_transaction->destroy();//this is just in case - destroys at least the nested ones... but as of the time of adding the transaction class the references are correct and the line below destroyes everything as expected
                $this->current_transaction = null;

            }
            */


            if ($scope_reference instanceof framework\database\classes\scopeReferenceTransactionTracker) {
                $scope_reference->set_destruction_reason($scope_reference::DESTRUCTION_REASON_EXPLICIT);
                $scope_reference->rollback_on_destory = false;//everything is OK with the transaction... do not roll it back
                $scope_reference = null;
            } elseif ($scope_reference) {
                k::logtofile_backtrace('scope_ref_not_correct');
            }
        }


        return true;
    }

    public function get_sql_history()
    {
        if (k::is_batch_mode()) {
            throw new framework\base\exceptions\runTimeException(sprintf(t::_('When running in Batch Mode the SQL history is not being collected thus is not available from pdo::get_sql_history().')));
        }
        return $this->sql_history;
    }

    public function get_current_transaction()
    {
        if (self::DBG_USE_TXM) {
            $ret = TXM::getCurrentTransaction(framework\orm\classes\ORMDBTransaction::class);
        } else {
            $ret = $this->current_transaction;
        }
        return $ret;
    }

    /**
     * Returns an array with the individual SQL queries.
     * This is a debug method
     * @return array
     */
    public function get_sql_transaction_nesting()
    {
        return $this->sql_transaction_nesting;
    }

    /**
     * This is a debug method
     * @return string
     */
    public function get_sql_transaction_nesting_formatted()
    {
        $arr = $this->get_sql_transaction_nesting();
        $str = '';
        foreach ($arr as $tr) {
            $caller = $tr['caller'];
            $str .= str_repeat("\t", $tr['nesting']) . $tr['type'] . ' ' . $caller['file'] . ':' . $caller['line'] . ' ' . $caller['class'] . '::' . $caller['function'] . '()' . PHP_EOL;
        }

        //check is there an error
        if (count($arr) >= 2 && $arr[0]['nesting'] != $arr[count($arr) - 1]['nesting']) {
            //try to figure out where is the error
            //TODO - check the pairs of nesting
        }

        return $str;
    }


    /**
     * @return bool
     */
    public function inTransaction()
    {
        //return $this->transactions_nested>=1;
        return $this->current_transaction ? true : false;
    }

    //public function executeWithDisabledRollBackAll($callback) {
    public function execute_with_disabled_rollback_all($callback)
    {
        $this->disableRollBackAll();
        $ret = $callback();
        $this->enableRollBackAll();
        return $ret;
    }

    /**
     * @return int
     */
    public function getTransactionsNesting()
    {
        //return $this->transactions_nested;
        $nesting = 0;
        if ($this->current_transaction) {
            $nesting = transaction::get_transactions_nesting($this->current_transaction);
        }
        return $nesting;
    }

    public function &getCurrentTransaction()
    {
        return $this->current_transaction;
    }

    /**
     * Used by the transaction object to register itself (because this is no longer done by the begin() & commit() methods here
     * //used by the stack_based_rollback mode
     * @param transaction $transaction
     */
    public function setCurrentTransaction(transaction &$transaction = null)
    {
        $this->current_transaction =& $transaction;
    }

    /**
     * Used by the transaction object to register itself (because this is no longer done by the begin() & commit() methods here
     * //used by the stack_based_rollback mode
     * @param transaction $transaction
     */
    public function set_current_transaction(transaction &$transaction = null)
    {
        $this->current_transaction =& $transaction;
    }

    public function disableRollBackAll()
    {
        //$this->roll_back_all_disabled = true;
        $this->transaction_disabled_nested++;
    }

    public function enableRollBackAll()
    {
        //$this->roll_back_all_disabled = false;
        $this->transaction_disabled_nested--;
    }

    public function rollBackAll(\Exception &$exception = null)
    {
        //if (!$this->roll_back_all_disabled) {
        if (!$this->transaction_disabled_nested) {
            if ($this->inTransaction()) { //this shouldnt happen any more.. now the frameowrk tracks the transactions using the $TR reference. This reference will rolblack the problematic transaction and then the thrown exception will all roll it back


                $activeEnvironment = framework\mvc\classes\activeEnvironment::get_instance();
                k::logtofile('TRANSACTION_ROLLED_BACK', 'Transaction rolled back' . PHP_EOL . $activeEnvironment . PHP_EOL . 'Started by ' . PHP_EOL . print_r($this->current_transaction_started_by, TRUE));//NOVERIFY activeEnvironemnt has __tostring method
                k::logtofile_backtrace('TRANSACTION_ROLLED_BACK');

                /*
                $nesting = $this->getTransactionsNesting();
                for ($aa=0;$aa<$nesting;$aa++) {
                    $this->rollBack();
                }
                 */
                //$this->current_transaction->rollback();
                $master_transaction = transaction::get_master_transaction($this->current_transaction);
                if ($master_transaction) {
                    $master_transaction->rollback();
                    $master_transaction = null;//this should trigger the destruction of all nested transactions;
                    $this->current_transaction = null;//just in case...
                    //$this->master_transaction_rolled_by_exception = $exception
                }

                $ret = true;
            } else {
                $ret = false;//there is no active transaction, but still no error should ne thrown
            }
        } else {
            $ret = false;
        }
        return $ret;
    }

    abstract public function lock_tables($table);

    abstract public function unlock_tables();

    public function __call(string $method, array $args)
    {
        if (method_exists($this->pdo, $method)) {
            return call_user_func_array([$this->pdo, $method], $args);
        } else {
            return parent::__call($method, $args);
        }
    }
}
