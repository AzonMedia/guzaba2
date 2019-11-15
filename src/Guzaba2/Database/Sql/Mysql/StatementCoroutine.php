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
use Guzaba2\Translator\Translator as t;
use Guzaba2\Base\Exceptions\InvalidArgumentException;

class StatementCoroutine extends Statement implements StatementInterface
{

    protected array $rows;

    public function execute(array $parameters = []) : self
    {

        $position_parameters = $this->convert_to_position_parameters($parameters);
        $ret = $this->NativeStatement->execute($position_parameters);

        if ($ret === FALSE) {
            $this->handle_error();//will throw exception
        }
        //in fact Swoole\Coroutine\Mysql\Statement::execute() returns the data (and cant be fetched with fetchAll()...
        elseif (is_array($ret)) {
            $this->rows = $ret;
        }
        $this->is_executed_flag = TRUE;

        return $this;
    }

    public function fetch_all() : array
    {
        return $this->fetchAll();
    }

    public function fetchAll() : array
    {
//        $ret = $this->NativeStatement->fetchAll();
//        if ($ret===FALSE) {
//            throw new QueryException(sprintf(t::_('Error executing query %s: [%s] %s.'), $this->get_query(), $this->NativeStatement->errno, $this->NativeStatement->error ));
//        }
        //$this->>execute()
        //return $this->rows;
        //$ret = $this->NativeStatement->fetchAll();//returns nothing...
        $ret = $this->rows;
        if ($ret===FALSE) {
            throw new QueryException(sprintf(t::_('Error executing query %s: [%s] %s.'), $this->get_query(), $this->NativeStatement->errno, $this->NativeStatement->error ));
        }
        return $ret;
    }

    public function fetch_row(string $column_name = '')
    {
        return $this->fetchRow($column_name);
    }

    public function fetchRow(string $column_name = '') /*mixed*/
    {
        //the data is already fetched on execute()
        //$data = $this->NativeStatement->fetchAll();

        if (count($this->rows)) {
            $row = $this->rows[0];
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

}
