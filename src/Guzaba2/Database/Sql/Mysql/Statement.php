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

    protected bool $is_executed_flag = FALSE;

    /**
     * StatementCoroutine constructor.
     * @param $NativeStatement
     * @param string $query
     * @param array $expected_parameters Contains the names of the expected parameters as parsed during statement preparation. Swoole\Statement does not support named parameters but only "?".
     */
    public function __construct(object $NativeStatement, string $query, array $expected_parameters = [])
    {
        parent::__construct();
        $this->NativeStatement = $NativeStatement;
        $this->query = $query;

        $this->expected_parameters = $expected_parameters;
    }

    public abstract function execute(array $parameters = []) : Statement ;

    public abstract function fetchAll() : array ;

    public abstract function fetch_all() : array ;

    public abstract function fetchRow(string $column_name = '') /* mixed */ ;

    public abstract function fetch_row(string $column_name = '') /* mixed */ ;

    public function getQuery() : string
    {
        return $this->get_query();
    }

    public function get_query() : string
    {
        return $this->query;
    }

    public function is_executed() : bool
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
    protected function convert_to_position_parameters(array $parameters) : array
    {

//        if (!$parameters) {
//            $parameters = $this->params;
//        }

        //validate the set parameters do they correspond to the expected parameters
        foreach ($this->expected_parameters as $expected_parameter) {
            if (!array_key_exists($expected_parameter, $parameters)) {
                throw new ParameterException($expected_parameter, sprintf(t::_('The prepared statement expects parameter named %s and this is not found in the provided parameters.'), $expected_parameter), $this->query, $parameters);
            }
        }
        foreach ($parameters as $provided_parameter=>$value) {
            if (!in_array($provided_parameter, $this->expected_parameters)) {
                throw new ParameterException($provided_parameter, sprintf(t::_('An unexpected paramtere named %s is provided to the prepared statement.'), $provided_parameter), $this->query, $parameters);
            }
        }
        //form the parameters in the right order
        $position_parameters = [];
        foreach ($this->expected_parameters as $expected_parameter) {
            $position_parameters[] = $parameters[$expected_parameter];
        }

        return $position_parameters;
    }

    protected function handle_error() : void
    {
        $error_code = $this->NativeStatement->errno ?? 0;

        if ($error_code=='40001') { //deadlock TODO need to check
            throw new DeadlockException($this, '', $error_code, sprintf(t::_('Error executing query %s: [%s] %s.'), $this->get_query(), $error_code, $this->NativeStatement->error), $this->get_query(), print_r($this->get_params(),TRUE) );
        } else {
            if ($error_code == '1062') {
                // duplicate entry
                throw new DuplicateKeyException($this, '', $error_code, sprintf(t::_('Error executing query %s: [%s] %s.'), $this->get_query(), $error_code, $this->NativeStatement->error), $this->get_query(), print_r($this->get_params(),TRUE));
            } elseif ($error_code == '1452') {
                // foreign key constraint
                throw new ForeignKeyConstraintException($this, '', $error_code, sprintf(t::_('Error executing query %s: [%s] %s.'), $this->get_query(), $error_code, $this->NativeStatement->error), $this->get_query(), print_r($this->get_params(),TRUE));
            } else {
                throw new QueryException($this, '', $error_code, sprintf(t::_('Error executing query %s: [%s] %s.'), $this->get_query(), $error_code, $this->NativeStatement->error), $this->get_query(), print_r($this->get_params(),TRUE) );
            }
        }
    }

}