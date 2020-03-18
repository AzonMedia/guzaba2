<?php
declare(strict_types=1);


namespace Guzaba2\Database\Sql\Mysql;


use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Database\Exceptions\QueryException;
use Guzaba2\Database\Interfaces\StatementInterface;
use Guzaba2\Database\Sql\TransactionalConnection;
use Guzaba2\Translator\Translator as t;
use Psr\Log\LogLevel;

abstract class Connection extends TransactionalConnection
{

    protected const CONFIG_DEFAULTS = [
        'host'      => 'localhost',
        'port'      => 3306,
        'user'      => 'root',
        'password'  => '',
        'database'  => '',
        'socket'    => '',
    ];

    protected const CONFIG_RUNTIME = [];

    protected object $NativeConnection;

    /**
     * Contains the original query using named parameters
     * @var string
     */
    protected string $original_query = '';

    protected function prepare_statement(string $query, string $statement_class, Connection $Connection) : StatementInterface
    {
        if (!class_exists($statement_class)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided $statement_class does not exist.'), $statement_class));
        }
        $this->original_query = $query;

        $expected_parameters = [];
        $query = self::convert_query_for_binding($query, $expected_parameters);

        $NativeStatement = $this->NativeConnection->prepare($query);
        if (!$NativeStatement) {
            $error_code = $this->NativeConnection->errno ?? 0;

            // With Swoole 4.4.0 and next if the connection is lost, prepare will NOT throw an exception, but errno will be set
            if ($this->NativeConnection->errno === 2006 || $this->NativeConnection->errno === 2013) {
                // If connection is lost, try to reconnect and prepare the query again
                Kernel::log(sprintf(t::_("MySQL Connection is Lost with Error No %s. Trying to reconnect ...\n"), $this->NativeConnection->errno), LogLevel::DEBUG);
                $this->connect();
                return $this->prepare($this->original_query);
            } else {
                throw new QueryException(null, '', $error_code, sprintf(t::_('Preparing query "%s" failed with error: [%s] %s .'), $query, $this->NativeConnection->errno, $this->NativeConnection->error), $query, $expected_parameters);
            }
        }

        $Statement = new $statement_class($NativeStatement, $Connection, $query, $expected_parameters);
        return $Statement;
    }

    public abstract function prepare(string $query) : StatementInterface ;

    public function close() : void
    {
        parent::close();
        $this->NativeConnection->close();
    }

    public function is_connected() : bool
    {
        return $this->NativeConnection->connected;
    }

    /**
     * Returns '=' is the value is not null and IS if it is. This function is needed by mysql (and possibly others) because in mysql WHERE column = null is not giving the expected result. Must be used for WHERE clauses on columns that can be null.
     * @param mixed $value
     * @param bool $use_like (whether to use = or LIKE)
     * @return string '=' or 'IS'
     */
    public static function equals($value, bool $use_like = FALSE) : string
    {
        if (is_null($value)) {
            return 'IS';
        } elseif (!$use_like) {
            return '=';
        } else {
            return 'LIKE';
        }
    }

    public function ping()
    {
        return $this->NativeConnection->query("SELECT 1");
    }

    /**
     * Returns the ID of the last insert.
     * Must be executed immediately after the insert query
     *
     * @return int
     */
    public function get_last_insert_id() : int
    {
        return $this->NativeConnection->insert_id;
    }

    /**
     * @return int
     */
    public function get_affected_rows() : int
    {
        return $this->NativeConnection->affected_rows;
    }

    /**
     * Returns the rows found by the previous query
     * This should be called only by pdoStatement::fetchAllAsArray() and the similar methods
     *
     * @return int
     */
    public function get_found_rows() : int
    {
        $q = "SELECT FOUND_ROWS() AS found_rows";
        return (int) $this->prepare($q)->execute()->fetchRow('found_rows');
    }

    public function get_last_error() : string
    {
        return $this->NativeConnection->error;
    }


    public function get_last_error_number() : int
    {
        return $this->NativeConnection->errno;
    }

    public function get_connection_id_from_db() : string
    {
        $q = "SELECT CONNECTION_ID() AS connection_id";
        return (string) $this->prepare($q)->execute()->fetchRow('connection_id');
    }

    public function begin_transaction() : void
    {
        $q = "START TRANSACTION";
        //$this->prepare($q)->execute();// Error: [1295] SQLSTATE[HY000] [1295] This command is not supported in the prepared statement protocol yet
        $this->NativeConnection->query($q);
    }

    public function commit_transaction() : void
    {
        $q = "COMMIT";
        //$this->prepare($q)->execute();
        $this->NativeConnection->query($q);
    }

    public function rollback_transaction() : void
    {
        $q = "ROLLBACK";
        //$this->prepare($q)->execute();
        $this->NativeConnection->query($q);
    }

    public function create_savepoint(string $savepoint_name) : void
    {
        if (!ctype_alnum($savepoint_name)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided savepoint name %1s is not alpha-numeric.'), $savepoint_name));
        }
        $q = "SAVEPOINT {$savepoint_name}";
        //$this->prepare($q)->execute();
        $this->NativeConnection->query($q);
    }

    public function rollback_to_savepoint(string $savepoint_name) : void
    {
        if (!ctype_alnum($savepoint_name)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided savepoint name %1s is not alpha-numeric.'), $savepoint_name));
        }
        $q = "ROLLBACK TO SAVEPOINT {$savepoint_name}";
        //$this->prepare($q)->execute();
        $this->NativeConnection->query($q);
    }

    public function release_savepoint(string $savepoint_name) : void
    {
        if (!ctype_alnum($savepoint_name)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided savepoint name %1s is not alpha-numeric.'), $savepoint_name));
        }
        $q = "RELEASE SAVEPOINT {$savepoint_name}";
        //$this->prepare($q)->execute();
        $this->NativeConnection->query($q);
    }
}