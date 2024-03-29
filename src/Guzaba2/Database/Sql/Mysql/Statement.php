<?php

declare(strict_types=1);

namespace Guzaba2\Database\Sql\Mysql;

use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Database\Exceptions\DeadlockException;
use Guzaba2\Database\Exceptions\DuplicateKeyException;
use Guzaba2\Database\Exceptions\ForeignKeyConstraintException;
use Guzaba2\Database\Exceptions\ParameterException;
use Guzaba2\Database\Exceptions\QueryException;
use Guzaba2\Database\Exceptions\ResultException;
use Guzaba2\Translator\Translator as t;

abstract class Statement extends \Guzaba2\Database\Sql\Statement
{

    /**
     * @var \Swoole\Coroutine\Mysql\Statement | \mysqli_stmt
     */
    protected object $NativeStatement;

    protected Connection $Connection;

    /**
     * The SQL query
     * @var string
     */
    protected string $query;

    /**
     * Contains a list of the expected parameters in the expected order.
     * These are the parameters found in the query and preserved in the order they were found.
     * The order matters as the actual params/values provided to execute() need to be reordered to match the expected order.
     * @var array
     */
    protected array $expected_parameters = [];

    protected bool $is_executed_flag = false;

    /**
     * StatementCoroutine constructor.
     * @param object $NativeStatement
     * @param Connection $Connection
     * @param string $query
     * @param array $expected_parameters Contains the names of the expected parameters as parsed during statement preparation. Swoole\Statement does not support named parameters but only "?".
     */
    public function __construct(object $NativeStatement, Connection $Connection, string $query, array $expected_parameters = [])
    {
        parent::__construct();
        $this->NativeStatement = $NativeStatement;
        $this->Connection = $Connection;
        $this->query = $query;

        $this->expected_parameters = $expected_parameters;
    }

    abstract public function execute(array $parameters = []): Statement;

    abstract public function fetchAll(): array;

    abstract public function fetch_all(): array;

    public function fetch_row(string $column_name = '') /* mixed */
    {
        return $this->fetchRow($column_name);
    }

    public function fetchRow(string $column_name = '') /* mixed */
    {
        //the data is already fetched on execute()
        //$data = $this->NativeStatement->fetchAll();
        $data = $this->fetchAll();
        if (count($data)) {
            $row = $data[0];
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
                $ret = [];
            }
        }

        return $ret;
    }

    public function get_connection(): Connection
    {
        return $this->Connection;
    }
    
    public function getQuery(): string
    {
        return $this->get_query();
    }

    /**
     * Returns the SQL query
     * @return string
     */
    public function get_query(): string
    {
        return $this->query;
    }

    public function is_executed(): bool
    {
        return $this->is_executed_flag;
    }

    /**
     * Converts the associative array with paramters to indexed array
     * @param array $parameters
     * @return array
     * @throws InvalidArgumentException
     * @throws ParameterException
     */
    protected function convert_to_position_parameters(array $parameters): array
    {

//        if (!$parameters) {
//            $parameters = $this->params;
//        }

        //validate the set parameters do they correspond to the expected parameters
        foreach ($this->expected_parameters as $expected_parameter) {
            if (!array_key_exists($expected_parameter, $parameters)) {
                $message = sprintf(
                    t::_('
The prepared statement expects parameter named %s and this is not found in the provided parameters.
The provided parameters are "%2$s".
The query is "%3$s".
                    '),
                    $expected_parameter,
                    print_r($parameters, true),
                    \Guzaba2\Database\Sql\Connection::format_sql($this->get_query()),
                );
                throw new ParameterException($expected_parameter, $message, $this->query, $parameters);
            }
        }
        foreach ($parameters as $provided_parameter => $value) {
            if (!in_array($provided_parameter, $this->expected_parameters)) {
                $message = sprintf(t::_('An unexpected paramter named %1$s is provided to the prepared statement.'), $provided_parameter);
                throw new ParameterException($provided_parameter, $message, $this->query, $parameters);
            }
        }
        //form the parameters in the right order
        $position_parameters = [];
        foreach ($this->expected_parameters as $expected_parameter) {
            $position_parameters[] = $parameters[$expected_parameter];
        }

        return $position_parameters;
    }

    protected function handle_error(): void
    {
        $error_code = (int) $this->NativeStatement->errno ?? 0;

        $exception_class = QueryException::class;
        if ($error_code === 40001) { //deadlock TODO need to check
            $exception_class = DeadlockException::class;
        } elseif ($error_code === 1062) { // duplicate entry
            $exception_class = DuplicateKeyException::class;
        } elseif ($error_code === 1452) { // foreign key constraint
            $exception_class = ForeignKeyConstraintException::class;
        }
        $message = sprintf(
            t::_('Error executing query %1$s with params: %2$s: [%3$s] %4$s.'),
            $this->get_query(),
            print_r($this->get_params(), true),
            $error_code,
            $this->NativeStatement->error
        );
        throw new $exception_class($this, '', $error_code, $message, $this->get_query(), print_r($this->get_params(), true) );
    }
}
