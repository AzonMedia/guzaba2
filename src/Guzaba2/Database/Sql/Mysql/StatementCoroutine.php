<?php


namespace Guzaba2\Database\Sql\Mysql;


use Guzaba2\Base\Base;
use Guzaba2\Database\Exceptions\QueryException;
use Guzaba2\Database\Exceptions\DeadlockException;
use Guzaba2\Database\Exceptions\DuplicateKeyException;
use Guzaba2\Database\Exceptions\ForeignKeyConstraintException;
use Guzaba2\Database\Interfaces\StatementInterface;
use Guzaba2\Translator\Translator as t;

class StatementCoroutine extends Base
implements StatementInterface
{

    /**
     * @var \Swoole\Coroutine\Mysql\Statement
     */
    protected $NativeStatement;

    /**
     * @var string
     */
    protected $query;

    protected $rows = [];

    public function __construct(\Swoole\Coroutine\Mysql\Statement $NativeStatement, string $query)
    {
        parent::__construct();
        $this->NativeStatement = $NativeStatement;
        $this->query = $query;
    }

    public function execute( array $parameters = []) : self
    {
        $this->rows = [];
        $ret = $this->NativeStatement->execute($parameters);

        $error_code = $this->NativeStatement->errno ?? 0;
        if ($ret === FALSE) {
            if ($error_code=='40001') { //deadlock TODO need to check
                throw new DeadlockException(sprintf(t::_('Error executing query %s: [%s] %s.'), $this->get_query(), $error_code, $this->NativeStatement->error));
            } else {
                if ($error_code == '1062') {
                    // duplicate entry
                    throw new DuplicateKeyException(sprintf(t::_('Error executing query %s: [%s] %s.'), $this->get_query(), $error_code, $this->NativeStatement->error));
                } else if ($error_code == '1452') {
                    // foreign key constraint
                    throw new ForeignKeyConstraintException(sprintf(t::_('Error executing query %s: [%s] %s.'), $this->get_query(), $error_code, $this->NativeStatement->error));
                } else {
                    throw new QueryException(sprintf(t::_('Error executing query %s: [%s] %s.'), $this->get_query(), $this->NativeStatement->errno, $this->NativeStatement->error ));
                }
            }
        }
        if (is_array($ret)) {
            $this->rows = $ret;
        }
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

    public function fetchRow() : array
    {
        return $this->rows[0] ?? [];
    }

    public function get_query() : string
    {
        return $this->query;
    }


}