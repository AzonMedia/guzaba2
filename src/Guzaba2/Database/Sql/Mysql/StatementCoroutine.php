<?php


namespace Guzaba2\Database\Sql\Mysql;


use Guzaba2\Base\Base;
use Guzaba2\Database\Exceptions\QueryException;
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
        if ($ret === FALSE) {
            throw new QueryException(sprintf(t::_('Error executing query %s: [%s] %s.'), $this->get_query(), $this->NativeStatement->errno, $this->NativeStatement->error ));
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

    public function get_query() : string
    {
        return $this->query;
    }


}