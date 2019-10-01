<?php


namespace Guzaba2\Database\Sql\Mysql;

use Guzaba2\Base\Base;
use Guzaba2\Database\Exceptions\ParameterException;
use Guzaba2\Database\Exceptions\QueryException;
use Guzaba2\Database\Exceptions\DeadlockException;
use Guzaba2\Database\Exceptions\DuplicateKeyException;
use Guzaba2\Database\Exceptions\ForeignKeyConstraintException;
use Guzaba2\Database\Exceptions\ResultException;
use Guzaba2\Database\Interfaces\StatementInterface;
use Guzaba2\Database\Sql\Statement;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Base\Exceptions\InvalidArgumentException;

class StatementCoroutine extends Statement implements StatementInterface
{

    /**
     * @var \Swoole\Coroutine\Mysql\Statement
     */
    protected $NativeStatement;

    /**
     * The SQL query
     * @var string
     */
    protected $query;

    protected $rows = [];

    /**
     * Contains a list of the expected parameters in the expected order.
     * These are the parameters found in the query and preserved in the order they were found.
     * The order matters as the actual params/values provided to execute() need to be reordered to match the expected order.
     * @var array
     */
    protected $expected_parameters = [];

    /**
     * StatementCoroutine constructor.
     * @param \Swoole\Coroutine\Mysql\Statement $NativeStatement
     * @param string $query
     * @param array $expected_parameters Contains the names of the expected parameters as parsed during statement preparation. Swoole\Statement does not support named parameters but only "?".
     */
    public function __construct(\Swoole\Coroutine\Mysql\Statement $NativeStatement, string $query, array $expected_parameters = [])
    {
        parent::__construct();
        $this->NativeStatement = $NativeStatement;
        $this->query = $query;

        $this->expected_parameters = $expected_parameters;
    }

    public function execute(array $parameters = []) : self
    {
        $this->rows = [];


        if ($parameters && $this->params) {
            //throw new ParameterException('*', sprintf(t::_('It is not allowed to set parameters as properties and provide parameters as an argument to %s.'), __METHOD__), $query, $parameters );
            throw new InvalidArgumentException(sprintf(t::_('It is not allowed to set parameters as properties and provide parameters as an argument to %s.'), __METHOD__));
        }

        if (!$parameters) {
            $parameters = $this->params;
        }

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

        $ret = $this->NativeStatement->execute($position_parameters);

        $error_code = $this->NativeStatement->errno ?? 0;

        if ($ret === FALSE) {
            if ($error_code=='40001') { //deadlock TODO need to check
                throw new DeadlockException($this, '', $error_code, sprintf(t::_('Error executing query %s: [%s] %s.'), $this->get_query(), $error_code, $this->NativeStatement->error), $this->get_query(), $parameters);
            } else {
                if ($error_code == '1062') {
                    // duplicate entry
                    throw new DuplicateKeyException($this, '', $error_code, sprintf(t::_('Error executing query %s: [%s] %s.'), $this->get_query(), $error_code, $this->NativeStatement->error), $this->get_query(), $parameters);
                } elseif ($error_code == '1452') {
                    // foreign key constraint
                    throw new ForeignKeyConstraintException($this, '', $error_code, sprintf(t::_('Error executing query %s: [%s] %s.'), $this->get_query(), $error_code, $this->NativeStatement->error), $this->get_query(), $parameters);
                } else {
                    throw new QueryException($this, '', $error_code, sprintf(t::_('Error executing query %s: [%s] %s.'), $this->get_query(), $error_code, $this->NativeStatement->error), $this->get_query(), $parameters);
                }
            }
        }
        if (is_array($ret)) {
            $this->rows = $ret;
        }
        //print 'StatementCoroutine execute';
        
        return $this;
    }

    public function fetch() : array
    {
    }

    public function fetchAll() : array
    {
//        $ret = $this->NativeStatement->fetchAll();
//        if ($ret===FALSE) {
//            throw new QueryException(sprintf(t::_('Error executing query %s: [%s] %s.'), $this->get_query(), $this->NativeStatement->errno, $this->NativeStatement->error ));
//        }
        //$this->>execute()
        return $this->rows;
    }

    public function fetchRow(string $column_name = '') /*mixed*/
    {
        if (count($this->rows)) {
            $row = array_change_key_case($this->rows[0], CASE_LOWER);
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
        //return $this->rows[0] ?? [];
    }

    public function getQuery() : string
    {
        return $this->get_query();
    }

    public function get_query() : string
    {
        return $this->query;
    }
}
