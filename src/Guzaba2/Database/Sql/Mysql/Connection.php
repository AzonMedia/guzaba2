<?php


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

    protected $NativeConnection;

    /**
     * Contains the original query using named paramenters
     * @var string
     */
    protected $original_query = '';

    protected function prepare_statement(string $query, string $statement_class) : StatementInterface
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

        $Statement = new $statement_class($NativeStatement, $query, $expected_parameters);
        return $Statement;
    }

    public abstract function prepare(string $query) : StatementInterface ;

    public abstract function connect() : void;

    public function close() : void
    {
        $this->NativeConnection->close();
    }

    public function is_connected() : bool
    {
        return $this->NativeConnection->connected;
    }

    /**
     * Returns '=' is the value is not null and IS if it is. This function is needed by mysql (and possibly others) because in mysql WHERE column = null is not giving the expected result. Must be used for WHERE clauses on columns that can be null.
     * @param mixed $value
     * @return string '=' or 'IS'
     */
    public static function equals($value) : string
    {
        if (is_null($value)) {
            return 'IS';
        } else {
            return '=';
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

    public function get_last_error() : string
    {
        return $this->NativeConnection->error;
    }

    public function get_last_error_number() : int
    {
        return $this->NativeConnection->errno;
    }
}